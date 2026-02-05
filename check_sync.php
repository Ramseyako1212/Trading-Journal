<?php
/**
 * Check Sync Status
 */
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <title>Sync Status | Trading Journal</title>
    <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap' rel='stylesheet'>
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css'>
    <link rel='stylesheet' href='assets/css/style.css'>
    <style>
        body { padding: 40px; }
        .status-card { margin-bottom: 2rem; }
        .success { color: #10B981; }
        .warning { color: #F59E0B; }
        .gold { color: #FFD700; }
        table { width: 100%; margin-top: 1rem; }
        th, td { padding: 1rem; border-bottom: 1px solid var(--border-glass); }
        th { color: var(--gold); text-transform: uppercase; font-size: 0.75rem; }
        pre { background: #000; padding: 1.5rem; border-radius: 12px; font-size: 13px; color: #0f0; max-height: 400px; overflow: auto; border: 1px solid #333; }
    </style>
</head>
<body class='bg-animated'>";

try {
    $pdo = getConnection();
    
    echo "<div class='container' style='max-width: 900px;'>";
    echo "<div class='d-flex align-items-center mb-5'>
            <a href='dashboard.php' class='btn btn-outline-luxury me-3'><i class='bi bi-arrow-left'></i></a>
            <div>
                <h1 class='mb-1'>Sync Health Monitor</h1>
                <p class='text-muted-custom mb-0'>Diagnostic data for MetaTrader 5 Integration</p>
            </div>
          </div>";

    // 1. Check API Key
    $userStmt = $pdo->prepare("SELECT name, api_key FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();

    echo "<div class='glass-card status-card'>";
    echo "<h5 class='text-gold'><i class='bi bi-key me-2'></i>API Configuration</h5>";
    if (!empty($user['api_key'])) {
        echo "<p class='text-green mb-2'><i class='bi bi-check-circle-fill me-2'></i>API Key is active.</p>";
        echo "<p class='mb-0'>Hashed ID: <code class='gold'>" . substr($user['api_key'], 0, 12) . "...</code></p>";
    } else {
        echo "<p class='text-red mb-0'><i class='bi bi-exclamation-triangle-fill me-2'></i>No API Key found. Generate one in <a href='settings.php' class='text-cyan'>Settings</a>.</p>";
    }
    echo "</div>";

    // 2. Instrument Coverage
    $instCount = $pdo->query("SELECT COUNT(*) FROM instruments")->fetchColumn();
    echo "<div class='glass-card status-card'>";
    echo "<h5 class='text-gold'><i class='bi bi-layers me-2'></i>Database Assets</h5>";
    echo "<p class='mb-2'>Total instruments mapped: <b class='gold'>$instCount</b></p>";
    if ($instCount < 30) {
        echo "<p class='text-warning mb-0 small'><i class='bi bi-info-circle me-1'></i>Tip: Some pairs might be missing. <a href='api/update_instruments.php' target='_blank' class='text-cyan'>Force Refresh Assets</a></p>";
    } else {
        echo "<p class='text-green mb-0'><i class='bi bi-check-circle-fill me-2'></i>Asset database is sufficient.</p>";
    }
    echo "</div>";

    // 3. Check Recent Synced Trades
    $tradeStmt = $pdo->prepare("
        SELECT t.*, i.code as symbol 
        FROM trades t 
        JOIN instruments i ON t.instrument_id = i.id 
        WHERE t.user_id = ? 
        ORDER BY t.created_at DESC LIMIT 5
    ");
    $tradeStmt->execute([$userId]);
    $trades = $tradeStmt->fetchAll();

    echo "<div class='glass-card status-card'>";
    echo "<h5 class='text-gold'><i class='bi bi-broadcast me-2'></i>Last Transmitted Data</h5>";
    if (empty($trades)) {
        echo "<div class='py-4 text-center'>
                <p class='text-muted-custom mb-2'>Waiting for incoming webhook events...</p>
                <small class='d-block opacity-50'>Trigger a trade close in MT5 to see activity here.</small>
              </div>";
    } else {
        echo "<div class='table-responsive'><table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Asset</th>
                        <th>Type</th>
                        <th>P&L</th>
                        <th>Outcome</th>
                    </tr>
                </thead>
                <tbody>";
        foreach ($trades as $t) {
            $pnlClass = $t['net_pnl'] >= 0 ? 'text-green' : 'text-red';
            echo "<tr>
                    <td><small>{$t['entry_time']}</small></td>
                    <td class='gold fw-bold'>{$t['symbol']}</td>
                    <td><span class='badge' style='background: rgba(255,255,255,0.05)'>{$t['direction']}</span></td>
                    <td class='$pnlClass font-monospace'>" . ($t['net_pnl'] >= 0 ? '+' : '') . "{$t['net_pnl']}</td>
                    <td><span class='badge bg-success opacity-75'>SYNCED</span></td>
                </tr>";
        }
        echo "</tbody></table></div>";
    }
    echo "</div>";

    // 4. Debug Logs
    echo "<div class='glass-card status-card'>";
    echo "<h5 class='text-gold'><i class='bi bi-terminal me-2'></i>Raw Webhook Stream</h5>";
    $logFile = 'webhook_debug.log';
    if (file_exists($logFile)) {
        echo "<pre>";
        $logs = file($logFile);
        $lastLogs = array_slice($logs, -15);
        echo htmlspecialchars(implode("", $lastLogs));
        echo "</pre>";
    } else {
        echo "<p class='text-muted-custom italic'>No raw logs found. The backend hasn't received any signals yet.</p>";
    }
    echo "</div>";

    echo "<div class='text-center mt-5 mb-5 opacity-75'>
            <a href='check_sync.php' class='btn btn-luxury'><i class='bi bi-arrow-clockwise me-2'></i>Run Fresh Diagnostic</a>
          </div>";
    
    echo "</div></body></html>";

} catch (Exception $e) {
    echo "<div class='container mt-5'><div class='glass-card text-red'>Critical Fault: " . $e->getMessage() . "</div></div>";
}
}
