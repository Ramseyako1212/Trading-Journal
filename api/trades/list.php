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
$search = trim($_GET['search'] ?? '') ?: null;
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

    if ($search) {
        $where[] = "(i.code LIKE ? OR i.name LIKE ? OR s.name LIKE ? OR t.entry_reason LIKE ? OR t.exit_reason LIKE ? OR t.notes LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam; // code
        $params[] = $searchParam; // instrument name
        $params[] = $searchParam; // strategy name
        $params[] = $searchParam; // entry_reason
        $params[] = $searchParam; // exit_reason
        $params[] = $searchParam; // notes
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Get total count and stats for the filtered set
    $statsStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_trades,
            SUM(CASE WHEN t.status = 'CLOSED' THEN 1 ELSE 0 END) as closed_trades,
            SUM(CASE WHEN t.status = 'CLOSED' AND t.net_pnl > 0 THEN 1 ELSE 0 END) as winning_trades,
            SUM(CASE WHEN t.status = 'CLOSED' THEN t.net_pnl ELSE 0 END) as total_net_pnl,
            AVG(CASE WHEN t.status = 'CLOSED' THEN t.r_multiple ELSE NULL END) as avg_r
        FROM trades t 
        LEFT JOIN instruments i ON t.instrument_id = i.id
        LEFT JOIN strategies s ON t.strategy_id = s.id
        WHERE $whereClause
    ");
    $statsStmt->execute($params);
    $stats = $statsStmt->fetch();
    $totalCount = $stats['total_trades'];

    // Get currency-specific P&L
    $currencyStatsStmt = $pdo->prepare("
        SELECT 
            a.currency,
            SUM(t.net_pnl) as total_pnl
        FROM trades t
        LEFT JOIN accounts a ON t.account_id = a.id
        LEFT JOIN instruments i ON t.instrument_id = i.id
        LEFT JOIN strategies s ON t.strategy_id = s.id
        WHERE $whereClause AND t.status = 'CLOSED'
        GROUP BY a.currency
    ");
    $currencyStatsStmt->execute($params);
    $currencyPnL = $currencyStatsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Get trades
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
        LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $trades = $stmt->fetchAll();
    
    // Get attachments for all trades in one go
    if (!empty($trades)) {
        $tradeIds = array_column($trades, 'id');
        $placeholders = implode(',', array_fill(0, count($tradeIds), '?'));
        $attachStmt = $pdo->prepare("SELECT trade_id, id, filename, original_name, file_path, file_type FROM attachments WHERE trade_id IN ($placeholders)");
        $attachStmt->execute($tradeIds);
        $allAttachments = $attachStmt->fetchAll(PDO::FETCH_GROUP);
        
        foreach ($trades as &$trade) {
            $trade['attachments'] = $allAttachments[$trade['id']] ?? [];
        }
    }
    
    echo json_encode([
        'success' => true,
        'trades' => $trades,
        'summary' => [
            'total' => (int)$totalCount,
            'closed' => (int)$stats['closed_trades'],
            'winners' => (int)$stats['winning_trades'],
            'win_rate' => $stats['closed_trades'] > 0 ? ($stats['winning_trades'] / $stats['closed_trades'] * 100) : 0,
            'avg_r' => (float)$stats['avg_r'],
            'pnl_by_currency' => $currencyPnL
        ],
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
