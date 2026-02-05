<?php
/**
 * Update Trade API
 */

header('Content-Type: application/json');

require_once '../../config/database.php';
require_once '../../includes/notifications.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$userId = $_SESSION['user_id'];
$tradeId = filter_input(INPUT_POST, 'trade_id', FILTER_SANITIZE_NUMBER_INT);

if (!$tradeId) {
    echo json_encode(['success' => false, 'message' => 'Trade ID is required']);
    exit;
}

try {
    $pdo = getConnection();
    
    // Verify trade belongs to user
    $checkStmt = $pdo->prepare("SELECT id, instrument_id, status FROM trades WHERE id = ? AND user_id = ?");
    $checkStmt->execute([$tradeId, $userId]);
    $trade = $checkStmt->fetch();
    
    if (!$trade) {
        echo json_encode(['success' => false, 'message' => 'Trade not found']);
        exit;
    }
    
    $oldStatus = $trade['status'];
    
    // Get form data
    $accountId = filter_input(INPUT_POST, 'account_id', FILTER_SANITIZE_NUMBER_INT);
    $instrumentId = filter_input(INPUT_POST, 'instrument_id', FILTER_SANITIZE_NUMBER_INT) ?: $trade['instrument_id'];
    $strategyId = filter_input(INPUT_POST, 'strategy_id', FILTER_SANITIZE_NUMBER_INT) ?: null;
    $direction = $_POST['direction'] ?? 'LONG';
    $entryPrice = filter_input(INPUT_POST, 'entry_price', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $exitPrice = filter_input(INPUT_POST, 'exit_price', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) ?: null;
    $stopLoss = filter_input(INPUT_POST, 'stop_loss', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) ?: null;
    $takeProfit = filter_input(INPUT_POST, 'take_profit', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) ?: null;
    $positionSize = filter_input(INPUT_POST, 'position_size', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) ?: 1;
    $fees = filter_input(INPUT_POST, 'fees', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) ?: 0;
    $entryTime = $_POST['entry_time'] ?? date('Y-m-d H:i:s');
    $exitTime = !empty($_POST['exit_time']) ? $_POST['exit_time'] : null;
    $setupQuality = filter_input(INPUT_POST, 'setup_quality', FILTER_SANITIZE_NUMBER_INT) ?: 3;
    $executionQuality = filter_input(INPUT_POST, 'execution_quality', FILTER_SANITIZE_NUMBER_INT) ?: 3;
    $entryReason = $_POST['entry_reason'] ?? '';
    $exitReason = $_POST['exit_reason'] ?? '';
    $lessonsLearned = $_POST['lessons_learned'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $followedRules = isset($_POST['followed_rules']) ? 1 : 0;
    $emotionalState = $_POST['emotional_state'] ?? '';
    
    // Get instrument details
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
        
        $priceDiff = $direction === 'LONG' 
            ? ($exitPrice - $entryPrice) 
            : ($entryPrice - $exitPrice);
        
        $ticks = $priceDiff / $tickSize;
        $grossPnl = $ticks * $tickValue * $positionSize;
        $netPnl = $grossPnl - $fees;
        
        if ($stopLoss !== null && $stopLoss != $entryPrice) {
            $riskPerContract = abs($entryPrice - $stopLoss) / $tickSize * $tickValue;
            $totalRisk = $riskPerContract * $positionSize;
            
            if ($totalRisk > 0) {
                $rMultiple = $netPnl / $totalRisk;
            }
        }
    }
    
    // Update trade
    $stmt = $pdo->prepare("
        UPDATE trades SET
            account_id = ?, instrument_id = ?, strategy_id = ?, direction = ?,
            entry_price = ?, exit_price = ?, stop_loss = ?, take_profit = ?,
            position_size = ?, fees = ?, entry_time = ?, exit_time = ?,
            gross_pnl = ?, net_pnl = ?, r_multiple = ?,
            setup_quality = ?, execution_quality = ?, followed_rules = ?,
            entry_reason = ?, exit_reason = ?, lessons_learned = ?, notes = ?,
            emotional_state = ?, status = ?, updated_at = NOW()
        WHERE id = ? AND user_id = ?
    ");
    
    $stmt->execute([
        $accountId, $instrumentId, $strategyId, $direction,
        $entryPrice, $exitPrice, $stopLoss, $takeProfit,
        $positionSize, $fees, $entryTime, $exitTime,
        $grossPnl, $netPnl, $rMultiple,
        $setupQuality, $executionQuality, $followedRules,
        $entryReason, $exitReason, $lessonsLearned, $notes,
        $emotionalState, $status, $tradeId, $userId
    ]);
    
    // Send notification if trade just closed
    if ($status === 'CLOSED' && $oldStatus !== 'CLOSED') {
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

    // Handle new file uploads
    if (!empty($_FILES['screenshots']['name'][0])) {
        $uploadDir = UPLOAD_PATH . 'trades/' . $userId . '/';
        
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
                
                if (!in_array($fileType, ALLOWED_TYPES) || $fileSize > MAX_FILE_SIZE) {
                    continue;
                }
                
                $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                $filename = uniqid('trade_' . $tradeId . '_') . '.' . $extension;
                $filePath = $uploadDir . $filename;
                
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
        'message' => 'Trade updated successfully',
        'pnl' => $netPnl,
        'r_multiple' => $rMultiple
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
