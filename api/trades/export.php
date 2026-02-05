<?php
/**
 * Export Trades to CSV API
 */

require_once '../../config/database.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('HTTP/1.1 401 Unauthorized');
    exit;
}

$userId = $_SESSION['user_id'];

// Filters
$instrumentId = filter_input(INPUT_GET, 'instrument_id', FILTER_SANITIZE_NUMBER_INT);
$strategyId = filter_input(INPUT_GET, 'strategy_id', FILTER_SANITIZE_NUMBER_INT);
$direction = trim($_GET['direction'] ?? '') ?: null;
$status = trim($_GET['status'] ?? '') ?: null;
$dateFrom = trim($_GET['date_from'] ?? '') ?: null;
$dateTo = trim($_GET['date_to'] ?? '') ?: null;

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
    
    $whereClause = implode(' AND ', $where);
    
    $query = "
        SELECT t.entry_time,
               t.exit_time,
               i.code as instrument,
               t.direction,
               t.entry_price,
               t.exit_price,
               t.position_size,
               t.gross_pnl,
               t.fees,
               t.net_pnl,
               t.r_multiple,
               s.name as strategy,
               t.status,
               t.entry_reason,
               t.exit_reason,
               t.lessons_learned
        FROM trades t
        LEFT JOIN instruments i ON t.instrument_id = i.id
        LEFT JOIN strategies s ON t.strategy_id = s.id
        WHERE $whereClause
        ORDER BY t.entry_time DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $trades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Set headers for download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="trading_journal_export_' . date('Y-m-d') . '.csv"');
    
    // Create CSV
    $output = fopen('php://output', 'w');
    
    // Header row
    if (!empty($trades)) {
        fputcsv($output, array_keys($trades[0]));
        
        foreach ($trades as $trade) {
            fputcsv($output, $trade);
        }
    } else {
        // Just headers if no data
        fputcsv($output, [
            'entry_time', 'exit_time', 'instrument', 'direction', 'entry_price', 'exit_price', 
            'position_size', 'gross_pnl', 'fees', 'net_pnl', 'r_multiple', 'strategy', 'status',
            'entry_reason', 'exit_reason', 'lessons_learned'
        ]);
    }
    
    fclose($output);
    exit;
    
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo "Export failed: " . $e->getMessage();
    exit;
}
