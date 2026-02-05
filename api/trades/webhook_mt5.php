<?php
/**
 * MT5 Webhook Handler
 * Receives trade data from MetaTrader 5 Expert Advisor
 */

header('Content-Type: application/json');

require_once '../../config/database.php';
require_once '../../includes/notifications.php';

// Debug Logging
function debugLog($msg) {
    file_put_contents('../../webhook_debug.log', date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
}

// Get JSON input
$input = file_get_contents('php://input');
$method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';

debugLog("Request received [Method: $method]");
if (empty($input)) {
    debugLog("Error: Empty payload body.");
} else {
    debugLog("Payload: " . $input);
}

$data = json_decode($input, true);

if (!$data || !isset($data['api_key']) || !isset($data['trade'])) {
    debugLog("Error: Invalid JSON payload structure.");
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid payload',
        'details' => json_last_error_msg()
    ]);
    exit;
}

$apiKey = $data['api_key'];
$tradeData = $data['trade'];

try {
    $pdo = getConnection();
    
    // Authenticate user via API Key
    $userStmt = $pdo->prepare("SELECT id FROM users WHERE api_key = ?");
    $userStmt->execute([$apiKey]);
    $user = $userStmt->fetch();
    
    if (!$user) {
        debugLog("Error: Unauthorized API Key: " . $apiKey);
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    $userId = $user['id'];
    debugLog("User authenticated: ID " . $userId);
    
    // NEW: Check for duplicates before anything else
    $ticket = $tradeData['ticket'] ?? null;
    if ($ticket) {
        $dupStmt = $pdo->prepare("SELECT id FROM trades WHERE user_id = ? AND external_id = ?");
        $dupStmt->execute([$userId, (string)$ticket]);
        if ($dupStmt->fetch()) {
            debugLog("Duplicate trade ignored. Ticket: " . $ticket);
            echo json_encode(['success' => true, 'message' => 'Trade already synced']);
            exit;
        }
    }

    // NEW: Check daily trade limit
    $userFullStmt = $pdo->prepare("SELECT daily_trade_limit FROM users WHERE id = ?");
    $userFullStmt->execute([$userId]);
    $userFull = $userFullStmt->fetch();
    $limit = $userFull['daily_trade_limit'] ?? 2;

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM trades WHERE user_id = ? AND DATE(entry_time) = CURDATE() AND status != 'CANCELLED'");
    $countStmt->execute([$userId]);
    $todayCount = $countStmt->fetchColumn();

    if ($todayCount >= $limit) {
        debugLog("Error: Trade limit reached for User ID $userId. Limit: $limit, Today: $todayCount");
        notifyOvertrading($userId, $todayCount, $limit);
        echo json_encode(['success' => false, 'message' => "Daily trade limit reached ($limit). Trade rejected."]);
        exit;
    }

    // Find or validate account
    $accountId = $data['account_id'] ?? null;
    $brokerOffset = 0;

    try {
        if ($accountId) {
            $accStmt = $pdo->prepare("SELECT id, broker_time_offset FROM accounts WHERE id = ? AND user_id = ?");
            $accStmt->execute([$accountId, $userId]);
        } else {
            // Find default account for user
            $accStmt = $pdo->prepare("SELECT id, broker_time_offset FROM accounts WHERE user_id = ? ORDER BY id ASC LIMIT 1");
            $accStmt->execute([$userId]);
        }
        
        $acc = $accStmt->fetch();
        if ($acc) {
            $accountId = $acc['id'];
            $brokerOffset = isset($acc['broker_time_offset']) ? (int)$acc['broker_time_offset'] : 0;
        }
    } catch (PDOException $e) {
        // If column doesn't exist yet, just find the account ID without offset
        if ($accountId) {
            $accStmt = $pdo->prepare("SELECT id FROM accounts WHERE id = ? AND user_id = ?");
            $accStmt->execute([$accountId, $userId]);
        } else {
            $accStmt = $pdo->prepare("SELECT id FROM accounts WHERE user_id = ? ORDER BY id ASC LIMIT 1");
            $accStmt->execute([$userId]);
        }
        $acc = $accStmt->fetch();
        if ($acc) {
            $accountId = $acc['id'];
        }
        debugLog("Note: broker_time_offset column not found. Skipping offset.");
    }
    
    // Find instrument by code (symbol)
    $symbol = $tradeData['symbol'];
    debugLog("Searching for symbol: " . $symbol);

    // Improved fuzzy matching: Strip common suffixes like .m, _i, #
    $cleanSymbol = preg_replace('/[^A-Z]/', '', strtoupper($symbol));
    $baseSymbol = substr($cleanSymbol, 0, 6); // standard forex is 6 chars
    
    $instStmt = $pdo->prepare("
        SELECT id, code, tick_size, tick_value FROM instruments 
        WHERE code = ? 
        OR code = ?
        OR code = ?
        OR ? LIKE CONCAT(code, '%') 
        OR code LIKE CONCAT(?, '%')
        LIMIT 1
    ");
    $instStmt->execute([$symbol, $cleanSymbol, $baseSymbol, $symbol, $baseSymbol]);
    $instrument = $instStmt->fetch();
    
    if (!$instrument) {
        debugLog("Error: Instrument $symbol not found (Clean: $cleanSymbol, Base: $baseSymbol)");
        echo json_encode(['success' => false, 'message' => "Instrument $symbol not found in journal database"]);
        exit;
    }
    
    $instrumentId = $instrument['id'];
    debugLog("Instrument found: " . $instrument['code'] . " (ID: $instrumentId)");

    
    // Map MT5 data to Journal schema
    // Note: MT5 sends DEAL_ENTRY_OUT (exit deals). 
    // If the exit deal is BUY, the original position was SHORT.
    // If the exit deal is SELL, the original position was LONG.
    $mt5Type = $tradeData['type'];
    $isLong = false;
    
    if (is_numeric($mt5Type)) {
        $typeInt = (int)$mt5Type;
        // Invert: 1 (Sell) is LONG exit, 0 (Buy) is SHORT exit
        $isLong = ($typeInt % 2 !== 0);
    } else {
        $typeStr = strtoupper((string)$mt5Type);
        // Use strpos for compatibility with older PHP versions
        $isLong = (strpos($typeStr, 'SELL') !== false);
    }
    
    $direction = $isLong ? 'LONG' : 'SHORT';
    debugLog("Direction determined: $direction (MT5 Type: $mt5Type)");
    
    $entryPrice = $tradeData['entry_price'];
    $exitPrice = $tradeData['exit_price'];
    $positionSize = $tradeData['volume'];
    $commission = $tradeData['commission'] ?? 0;
    $swap = $tradeData['swap'] ?? 0;
    $profit = $tradeData['profit']; // Gross profit from MT5
    
    $grossPnl = $profit;
    $netPnl = $profit + $swap + $commission; // Commission in MT5 is usually negative already
    $fees = abs($commission) + abs($swap < 0 ? $swap : 0);
    
    // MT5 often sends dates with dots (2024.01.01), MySQL needs dashes (2024-01-01)
    $entryTimeStr = str_replace('.', '-', $tradeData['entry_time']);
    $exitTimeStr = !empty($tradeData['exit_time']) ? str_replace('.', '-', $tradeData['exit_time']) : null;
    
    // Apply Broker Time Offset (convert broker server time to PHT/Local)
    if ($brokerOffset !== 0) {
        $entryTime = date('Y-m-d H:i:s', strtotime($entryTimeStr) + ($brokerOffset * 3600));
        $exitTime = $exitTimeStr ? date('Y-m-d H:i:s', strtotime($exitTimeStr) + ($brokerOffset * 3600)) : null;
    } else {
        $entryTime = $entryTimeStr;
        $exitTime = $exitTimeStr;
    }
    
    // Calculate R-Multiple if SL is provided
    $rMultiple = 0;
    if (isset($tradeData['stop_loss']) && $tradeData['stop_loss'] > 0) {
        $sl = $tradeData['stop_loss'];
        $tickSize = $instrument['tick_size'] ?? 0.0001;
        $tickValue = $instrument['tick_value'] ?? 10;
        
        $riskPerContract = abs($entryPrice - $sl) / $tickSize * $tickValue;
        $totalRisk = $riskPerContract * $positionSize;
        
        if ($totalRisk > 0) {
            $rMultiple = $netPnl / $totalRisk;
        }
    }

    // CONTENT-BASED DUPLICATE CHECK (Fallback for missing tickets)
    if (!$ticket) {
        $dupCheck = $pdo->prepare("
            SELECT id FROM trades 
            WHERE user_id = ? 
            AND instrument_id = ? 
            AND entry_price = ? 
            AND direction = ? 
            AND entry_time = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ");
        $dupCheck->execute([$userId, $instrumentId, $entryPrice, $direction, $entryTime]);
        if ($dupCheck->fetch()) {
            debugLog("Fuzzy duplicate trade ignored (No Ticket). Symbol: " . $tradeData['symbol']);
            echo json_encode(['success' => true, 'message' => 'Duplicate ignored']);
            exit;
        }
    }

    // Insert trade
    $stmt = $pdo->prepare("
        INSERT INTO trades (
            user_id, account_id, instrument_id, direction,
            entry_price, exit_price, position_size, fees, 
            entry_time, exit_time, gross_pnl, net_pnl, r_multiple,
            status, stop_loss, take_profit, external_id, created_at
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            'CLOSED', ?, ?, ?, NOW()
        )
    ");
    
    $stmt->execute([
        $userId, $accountId, $instrumentId, $direction,
        $entryPrice, $exitPrice, $positionSize, $fees,
        $entryTime, $exitTime, $grossPnl, $netPnl, $rMultiple,
        $tradeData['stop_loss'] ?? null,
        $tradeData['take_profit'] ?? null,
        $ticket ? (string)$ticket : null
    ]);
    
    $tradeId = $pdo->lastInsertId();
    debugLog("Trade inserted successfully. ID: $tradeId");
    
    // Send email notification for closed trade
    notifyTradeClosed($userId, [
        'instrument' => $instrument['code'],
        'direction' => $direction,
        'net_pnl' => $netPnl,
        'exit_time' => $exitTime
    ]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Trade synced successfully',
        'trade_id' => $tradeId
    ]);

} catch (PDOException $e) {
    debugLog("Database Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
