<?php
/**
 * Get Trades API (with filtering and pagination)
 */

header('Content-Type: application/json');

require_once '../../config/database.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];

// Pagination
$page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_NUMBER_INT) ?: 1;
$limit = filter_input(INPUT_GET, 'limit', FILTER_SANITIZE_NUMBER_INT) ?: 20;
$offset = ($page - 1) * $limit;

// Filters
$instrumentId = filter_input(INPUT_GET, 'instrument_id', FILTER_SANITIZE_NUMBER_INT);
$strategyId = filter_input(INPUT_GET, 'strategy_id', FILTER_SANITIZE_NUMBER_INT);
$direction = trim($_GET['direction'] ?? '') ?: null;
$status = trim($_GET['status'] ?? '') ?: null;
$dateFrom = trim($_GET['date_from'] ?? '') ?: null;
$dateTo = trim($_GET['date_to'] ?? '') ?: null;
$minPnl = filter_input(INPUT_GET, 'min_pnl', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
$maxPnl = filter_input(INPUT_GET, 'max_pnl', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

try {
    $pdo = getConnection();
    
    // Build query
    $where = ["t.user_id = ?"];
    $params = [$userId];
    
    if ($instrumentId) {
        $where[] = "t.instrument_id = ?";
        $params[] = $instrumentId;
    }
    
    if ($strategyId) {
        $where[] = "t.strategy_id = ?";
        $params[] = $strategyId;
    }
    
    if ($direction && in_array($direction, ['LONG', 'SHORT'])) {
        $where[] = "t.direction = ?";
        $params[] = $direction;
    }
    
    if ($status && in_array($status, ['OPEN', 'CLOSED', 'CANCELLED'])) {
        $where[] = "t.status = ?";
        $params[] = $status;
    }
    
    if ($dateFrom) {
        $where[] = "t.entry_time >= ?";
        $params[] = $dateFrom;
    }
    
    if ($dateTo) {
        $where[] = "t.entry_time <= ?";
        $params[] = $dateTo . ' 23:59:59';
    }
    
    if ($minPnl !== null) {
        $where[] = "t.net_pnl >= ?";
        $params[] = $minPnl;
    }
    
    if ($maxPnl !== null) {
        $where[] = "t.net_pnl <= ?";
        $params[] = $maxPnl;
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Get total count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM trades t WHERE $whereClause");
    $countStmt->execute($params);
    $totalCount = $countStmt->fetchColumn();
    
    // Get trades
    $params[] = $limit;
    $params[] = $offset;
    
    $query = "
        SELECT t.*, 
               i.code as instrument_code, 
               i.name as instrument_name,
               s.name as strategy_name,
               s.color as strategy_color,
               a.currency as account_currency
        FROM trades t
        LEFT JOIN instruments i ON t.instrument_id = i.id
        LEFT JOIN strategies s ON t.strategy_id = s.id
        LEFT JOIN accounts a ON t.account_id = a.id
        WHERE $whereClause
        ORDER BY t.entry_time DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $trades = $stmt->fetchAll();
    
    // Get attachments for each trade
    foreach ($trades as &$trade) {
        $attachQuery = $pdo->prepare("SELECT id, filename, original_name, file_path, file_type FROM attachments WHERE trade_id = ?");
        $attachQuery->execute([$trade['id']]);
        $trade['attachments'] = $attachQuery->fetchAll();
    }
    
    echo json_encode([
        'success' => true,
        'trades' => $trades,
        'pagination' => [
            'page' => (int)$page,
            'limit' => (int)$limit,
            'total' => (int)$totalCount,
            'pages' => ceil($totalCount / $limit)
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
