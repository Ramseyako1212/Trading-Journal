<?php
/**
 * Create Trade API
 */

header('Content-Type: application/json');

require_once '../../config/database.php';
require_once '../../includes/notifications.php';

// Check authentication
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $pdo = getConnection();
    
    // STRICT ENFORCEMENT: Check if today's checklist was passed
    $checkDate = date('Y-m-d');
    $checkStmt = $pdo->prepare("SELECT passed FROM daily_checklists WHERE user_id = ? AND check_date = ?");
    $checkStmt->execute([$userId, $checkDate]);
    $checklist = $checkStmt->fetch();

    if (!$checklist || !$checklist['passed']) {
        echo json_encode(['success' => false, 'message' => 'Trading restricted. Please complete your daily readiness check first.']);
        exit;
    }

    // NEW: Check daily trade limit
    $userStmt = $pdo->prepare("SELECT daily_trade_limit FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();
    $limit = $user['daily_trade_limit'] ?? 2;

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM trades WHERE user_id = ? AND DATE(entry_time) = CURDATE() AND status != 'CANCELLED'");
    $countStmt->execute([$userId]);
    $todayCount = $countStmt->fetchColumn();

    if ($todayCount >= $limit) {
        notifyOvertrading($userId, $todayCount, $limit);
        echo json_encode(['success' => false, 'message' => "Daily trade limit reached ($limit). Protect your capital and stop for today."]);
        exit;
    }

    // Get form data
    $accountId = filter_input(INPUT_POST, 'account_id', FILTER_SANITIZE_NUMBER_INT);
$instrumentId = filter_input(INPUT_POST, 'instrument_id', FILTER_SANITIZE_NUMBER_INT);
$strategyId = filter_input(INPUT_POST, 'strategy_id', FILTER_SANITIZE_NUMBER_INT) ?: null;
$direction = $_POST['direction'] ?? 'LONG';
$entryPrice = filter_input(INPUT_POST, 'entry_price', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
$exitPrice = filter_input(INPUT_POST, 'exit_price', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) ?: null;
$stopLoss = filter_input(INPUT_POST, 'stop_loss', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) ?: null;
$takeProfit = filter_input(INPUT_POST, 'take_profit', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) ?: null;
$positionSize = filter_input(INPUT_POST, 'position_size', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) ?: 1;
$fees = filter_input(INPUT_POST, 'fees', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) ?: 0;
$entryTime = !empty($_POST['entry_time']) ? str_replace('T', ' ', $_POST['entry_time']) : date('Y-m-d H:i:s');
$exitTime = !empty($_POST['exit_time']) ? str_replace('T', ' ', $_POST['exit_time']) : null;
$setupQuality = filter_input(INPUT_POST, 'setup_quality', FILTER_SANITIZE_NUMBER_INT) ?: 3;
$executionQuality = filter_input(INPUT_POST, 'execution_quality', FILTER_SANITIZE_NUMBER_INT) ?: 3;
$emotionalState = $_POST['emotional_state'] ?? 'Neutral';
$entryReason = $_POST['entry_reason'] ?? '';
$exitReason = $_POST['exit_reason'] ?? '';
$lessonsLearned = $_POST['lessons_learned'] ?? '';
$followedRules = isset($_POST['followed_rules']) ? 1 : 0;

// Validation
if (empty($entryPrice)) {
    echo json_encode(['success' => false, 'message' => 'Entry price is required']);
    exit;
}

if (!in_array($direction, ['LONG', 'SHORT'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid direction']);
    exit;
}

// Check for duplicates (Search for identical trade content created in the last 10 seconds)
$dupCheck = $pdo->prepare("
    SELECT id FROM trades 
    WHERE user_id = ? 
    AND instrument_id = ? 
    AND entry_price = ? 
    AND direction = ? 
    AND entry_time = ?
    AND created_at >= DATE_SUB(NOW(), INTERVAL 10 SECOND)
");
$dupCheck->execute([$userId, $instrumentId, $entryPrice, $direction, $entryTime]);
if ($dupCheck->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Duplicate trade detected. Please wait a moment.']);
    exit;
}

// Get instrument details for P&L calculation
$instQuery = $pdo->prepare("SELECT tick_size, tick_value FROM instruments WHERE id = ?");
$instQuery->execute([$instrumentId]);
$instrument = $instQuery->fetch();
    
    $tickSize = $instrument['tick_size'] ?? 0.01;
    $tickValue = $instrument['tick_value'] ?? 10;
    
    // Calculate P&L and R-Multiple
    $grossPnl = 0;
    $netPnl = 0;
    $rMultiple = 0;
    $status = 'OPEN';
    
    if ($exitPrice !== null) {
        $status = 'CLOSED';
        
        // Calculate tick difference
        $priceDiff = $direction === 'LONG' 
            ? ($exitPrice - $entryPrice) 
            : ($entryPrice - $exitPrice);
        
        $ticks = $priceDiff / $tickSize;
        $grossPnl = $ticks * $tickValue * $positionSize;
        $netPnl = $grossPnl - $fees;
        
        // Calculate R-Multiple (risk was from entry to stop loss)
        if ($stopLoss !== null && $stopLoss != $entryPrice) {
            $riskPerContract = abs($entryPrice - $stopLoss) / $tickSize * $tickValue;
            $totalRisk = $riskPerContract * $positionSize;
            
            if ($totalRisk > 0) {
                $rMultiple = $netPnl / $totalRisk;
            }
        }
    }
    
    // Insert trade
    $stmt = $pdo->prepare("
        INSERT INTO trades (
            user_id, account_id, instrument_id, strategy_id, direction,
            entry_price, exit_price, stop_loss, take_profit,
            position_size, fees, entry_time, exit_time,
            gross_pnl, net_pnl, r_multiple,
            setup_quality, execution_quality, emotional_state, followed_rules,
            entry_reason, exit_reason, lessons_learned,
            status, created_at
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?,
            ?, NOW()
        )
    ");
    
    $stmt->execute([
        $userId, $accountId, $instrumentId, $strategyId, $direction,
        $entryPrice, $exitPrice, $stopLoss, $takeProfit,
        $positionSize, $fees, $entryTime, $exitTime,
        $grossPnl, $netPnl, $rMultiple,
        $setupQuality, $executionQuality, $emotionalState, $followedRules,
        $entryReason, $exitReason, $lessonsLearned,
        $status
    ]);
    
    $tradeId = $pdo->lastInsertId();

    // Send notification if trade is created as closed
    if ($status === 'CLOSED') {
        $instName = 'Trade';
        $instSearch = $pdo->prepare("SELECT code FROM instruments WHERE id = ?");
        $instSearch->execute([$instrumentId]);
        $inst = $instSearch->fetch();
        if ($inst) $instName = $inst['code'];

        notifyTradeClosed($userId, [
            'instrument' => $instName,
            'direction' => $direction,
            'net_pnl' => $netPnl,
            'exit_time' => $exitTime ?: date('Y-m-d H:i:s')
        ]);
    }
    
    // Handle file uploads
    if (!empty($_FILES['screenshots']['name'][0])) {
        $uploadDir = UPLOAD_PATH . 'trades/' . $userId . '/';
        
        // Create directory if not exists
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $attachmentStmt = $pdo->prepare("
            INSERT INTO attachments (trade_id, user_id, filename, original_name, file_path, file_type, file_size)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($_FILES['screenshots']['tmp_name'] as $key => $tmpName) {
            if ($_FILES['screenshots']['error'][$key] === UPLOAD_ERR_OK) {
                $originalName = $_FILES['screenshots']['name'][$key];
                $fileType = $_FILES['screenshots']['type'][$key];
                $fileSize = $_FILES['screenshots']['size'][$key];
                
                // Validate file type
                if (!in_array($fileType, ALLOWED_TYPES)) {
                    continue;
                }
                
                // Validate file size
                if ($fileSize > MAX_FILE_SIZE) {
                    continue;
                }
                
                // Generate unique filename
                $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                $filename = uniqid('trade_' . $tradeId . '_') . '.' . $extension;
                $filePath = $uploadDir . $filename;
                
                // Move uploaded file
                if (move_uploaded_file($tmpName, $filePath)) {
                    $attachmentStmt->execute([
                        $tradeId, $userId, $filename, $originalName,
                        'uploads/trades/' . $userId . '/' . $filename,
                        $fileType, $fileSize
                    ]);
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Trade saved successfully',
        'trade_id' => $tradeId,
        'pnl' => $netPnl,
        'r_multiple' => $rMultiple
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
