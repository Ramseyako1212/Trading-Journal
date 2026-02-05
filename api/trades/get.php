<?php
/**
 * Get Single Trade API
 */

header('Content-Type: application/json');

require_once '../../config/database.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$tradeId = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

if (!$tradeId) {
    echo json_encode(['success' => false, 'message' => 'Trade ID is required']);
    exit;
}

try {
    $pdo = getConnection();
    
    $query = "
        SELECT t.*, 
               i.code as instrument_code, 
               i.name as instrument_name,
               i.tick_size,
               i.tick_value,
               s.name as strategy_name,
               s.color as strategy_color,
               a.name as account_name,
               a.currency as account_currency
        FROM trades t
        LEFT JOIN instruments i ON t.instrument_id = i.id
        LEFT JOIN strategies s ON t.strategy_id = s.id
        LEFT JOIN accounts a ON t.account_id = a.id
        WHERE t.id = ? AND t.user_id = ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$tradeId, $userId]);
    $trade = $stmt->fetch();
    
    if (!$trade) {
        echo json_encode(['success' => false, 'message' => 'Trade not found']);
        exit;
    }
    
    // Get attachments
    $attachQuery = $pdo->prepare("SELECT id, filename, original_name, file_path, file_type FROM attachments WHERE trade_id = ? AND user_id = ?");
    $attachQuery->execute([$tradeId, $userId]);
    $trade['attachments'] = $attachQuery->fetchAll();
    
    echo json_encode([
        'success' => true,
        'trade' => $trade
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
