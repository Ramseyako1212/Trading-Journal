<?php
/**
 * Dashboard - Main Trading Journal Interface
 */

require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];

try {
    $pdo = getConnection();
    
    // Get account balances
    $accountsQuery = $pdo->prepare("SELECT * FROM accounts WHERE user_id = ? ORDER BY name");
    $accountsQuery->execute([$userId]);
    $accounts = $accountsQuery->fetchAll();
    
    // Get user's trading stats
    $statsQuery = $pdo->prepare("
        SELECT 
            COUNT(*) as total_trades,
            SUM(CASE WHEN net_pnl > 0 THEN 1 ELSE 0 END) as winning_trades,
            SUM(CASE WHEN net_pnl < 0 THEN 1 ELSE 0 END) as losing_trades,
            COALESCE(SUM(net_pnl), 0) as total_pnl,
            COALESCE(AVG(r_multiple), 0) as avg_r
        FROM trades 
        WHERE user_id = ? AND status = 'CLOSED'
    ");
    $statsQuery->execute([$userId]);
    $stats = $statsQuery->fetch();

    // Group P&L by currency for more accurate display
    $pnlByCurrencyQuery = $pdo->prepare("
        SELECT a.currency, SUM(t.net_pnl) as total_pnl
        FROM trades t
        JOIN accounts a ON t.account_id = a.id
        WHERE t.user_id = ? AND t.status = 'CLOSED'
        GROUP BY a.currency
    ");
    $pnlByCurrencyQuery->execute([$userId]);
    $pnlByCurrency = $pnlByCurrencyQuery->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Calculate win rate
    $winRate = $stats['total_trades'] > 0 
        ? round(($stats['winning_trades'] / $stats['total_trades']) * 100, 1) 
        : 0;
    
    // Get recent trades
    $tradesQuery = $pdo->prepare("
        SELECT t.*, i.code as instrument_code, i.name as instrument_name, 
               s.name as strategy_name, a.currency as account_currency
        FROM trades t
        LEFT JOIN instruments i ON t.instrument_id = i.id
        LEFT JOIN strategies s ON t.strategy_id = s.id
        LEFT JOIN accounts a ON t.account_id = a.id
        WHERE t.user_id = ?
        ORDER BY t.entry_time DESC
        LIMIT 10
    ");
    $tradesQuery->execute([$userId]);
    $recentTrades = $tradesQuery->fetchAll();
    
    // Get instruments for dropdown (with tick data)
    $instrumentsQuery = $pdo->query("SELECT id, code, name, tick_size, tick_value FROM instruments ORDER BY code");
    $instruments = $instrumentsQuery->fetchAll();
    
    // Create JSON map of instrument tick data for JavaScript
    $instrumentTickData = [];
    foreach ($instruments as $inst) {
        $instrumentTickData[$inst['id']] = [
            'tick_size' => floatval($inst['tick_size']),
            'tick_value' => floatval($inst['tick_value'])
        ];
    }
    $instrumentTickDataJson = json_encode($instrumentTickData);
    
    // Get user strategies
    $strategiesQuery = $pdo->prepare("SELECT * FROM strategies WHERE user_id = ? ORDER BY name");
    $strategiesQuery->execute([$userId]);
    $strategies = $strategiesQuery->fetchAll();
    
    // Get user tags
    $tagsQuery = $pdo->prepare("SELECT * FROM tags WHERE user_id = ? ORDER BY name");
    $tagsQuery->execute([$userId]);
    $tags = $tagsQuery->fetchAll();

    // NEW: Check for daily trade limit alert
    $userLimitQuery = $pdo->prepare("SELECT daily_trade_limit FROM users WHERE id = ?");
    $userLimitQuery->execute([$userId]);
    $dailyLimit = $userLimitQuery->fetchColumn();

    $todayCountQuery = $pdo->prepare("SELECT COUNT(*) FROM trades WHERE user_id = ? AND DATE(entry_time) = CURDATE() AND status != 'CANCELLED'");
    $todayCountQuery->execute([$userId]);
    $todayTradeCount = $todayCountQuery->fetchColumn();
    
    $isLimitReached = $todayTradeCount >= $dailyLimit;

    // Fetch in-app notifications
    $notifQuery = $pdo->prepare("SELECT * FROM system_notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $notifQuery->execute([$userId]);
    $notifications = $notifQuery->fetchAll();

    $unreadCountQuery = $pdo->prepare("SELECT COUNT(*) FROM system_notifications WHERE user_id = ? AND is_read = 0");
    $unreadCountQuery->execute([$userId]);
    $unreadCount = $unreadCountQuery->fetchColumn();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Trading Journal</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Instrument Tick Data for P&L Preview -->
    <script>
        const instrumentTickData = <?php echo $instrumentTickDataJson; ?>;
    </script>
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-animated"></div>
    <div class="grid-overlay"></div>

    <!-- 2026 Background Blobs -->
    <div class="spatial-blob blob-gold"></div>
    <div class="spatial-blob blob-cyan"></div>
    
    <?php include 'includes/checklist_modal.php'; ?>

    <?php include "includes/sidebar.php"; ?>

    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1">Welcome back, <span class="text-gold"><?php echo htmlspecialchars($userName); ?></span></h4>
                <p class="text-muted-custom mb-0">Here's your trading overview</p>
            </div>
            <div class="d-flex gap-3 align-items-center">
                <!-- Notification Bell -->
                <div class="dropdown me-2">
                    <button class="btn btn-icon-luxury position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-bell"></i>
                        <?php if ($unreadCount > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-dark" style="font-size: 0.5rem; padding: 0.35em 0.5em;">
                                <?php echo $unreadCount; ?>
                            </span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end dropdown-menu-dark bg-glass border-glass shadow-lg p-0" style="width: 300px;">
                        <div class="p-3 border-bottom border-glass d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 text-gold smaller text-uppercase letter-spacing-1">Notifications</h6>
                            <?php if ($unreadCount > 0): ?>
                                <span class="badge bg-danger rounded-pill smaller"><?php echo $unreadCount; ?> new</span>
                            <?php endif; ?>
                        </div>
                        <div class="notification-dropdown-list" style="max-height: 350px; overflow-y: auto;">
                            <?php if (empty($notifications)): ?>
                                <div class="p-4 text-center text-muted-custom">
                                    <i class="bi bi-bell-slash d-block mb-2 h4 opacity-25"></i>
                                    <p class="smaller mb-0">No new notifications</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($notifications as $notif): ?>
                                    <div class="p-3 border-bottom border-glass-light notification-item-dropdown <?php echo !$notif['is_read'] ? 'bg-glass-active' : 'opacity-75'; ?>">
                                        <div class="d-flex justify-content-between mb-1">
                                            <small class="fw-bold text-<?php echo $notif['type'] === 'danger' ? 'red' : ($notif['type'] === 'success' ? 'green' : ($notif['type'] === 'warning' ? 'gold' : 'cyan')); ?>">
                                                <?php echo htmlspecialchars($notif['title']); ?>
                                            </small>
                                            <span class="smaller text-muted-custom"><?php echo date('H:i', strtotime($notif['created_at'])); ?></span>
                                        </div>
                                        <p class="mb-0 smaller text-light-custom opacity-75 line-clamp-2"><?php echo htmlspecialchars($notif['message']); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="p-2 text-center border-top border-glass">
                            <a href="#notificationsCard" class="smaller text-gold text-decoration-none">View All Alerts</a>
                        </div>
                    </div>
                </div>

                <button class="btn btn-outline-luxury" data-bs-toggle="modal" data-bs-target="#newTradeModal">
                    <i class="bi bi-plus-lg me-2"></i>Quick Add
                </button>
                <button class="btn btn-luxury" data-bs-toggle="modal" data-bs-target="#newTradeModal">
                    <i class="bi bi-plus-circle me-2"></i>New Trade
                </button>
            </div>
        </div>

        <?php if ($isLimitReached): ?>
        <div class="alert alert-soft-warning border-warning border-glass mb-4 animate__animated animate__fadeIn">
            <div class="d-flex align-items-center">
                <div class="alert-icon-circle bg-warning text-dark me-3">
                    <i class="bi bi-hand-index-fill"></i>
                </div>
                <div>
                    <h6 class="alert-heading mb-1 fw-bold">Daily Protection Active</h6>
                    <p class="mb-0 opacity-75">You've reached your <strong><?php echo $dailyLimit; ?> trade limit</strong> for today. Trading is temporarily disabled to help you maintain discipline and protect your capital.</p>
                    <div class="mt-2 small d-flex align-items-center gap-2">
                        <span class="opacity-50">Limit resets in:</span>
                        <span id="dashboardResetTimer" class="font-monospace fw-bold text-dark">--:--:--</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Stats Grid -->
        <div class="row g-4 mb-4">
            <div class="col-6 col-lg-3">
                <div class="metric-card">
                    <div class="metric-value text-gold"><?php echo number_format($stats['total_trades']); ?></div>
                    <div class="metric-label">Total Trades</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="metric-card cyan">
                    <div class="metric-value"><?php echo $winRate; ?>%</div>
                    <div class="metric-label">Win Rate</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="metric-card <?php echo $stats['total_pnl'] >= 0 ? 'green' : 'red'; ?>">
                    <div class="metric-value <?php echo $stats['total_pnl'] >= 0 ? 'text-green' : 'text-red'; ?>">
                        <?php 
                        if (empty($pnlByCurrency)) {
                            echo '$0.00';
                        } else {
                            $parts = [];
                            foreach ($pnlByCurrency as $curr => $val) {
                                $parts[] = ($val >= 0 ? '+' : '') . number_format($val, 2) . ' <small style="font-size: 0.6em">' . $curr . '</small>';
                            }
                            echo implode('<div style="font-size: 0.5em; line-height: 1"></div>', $parts);
                        }
                        ?>
                    </div>
                    <div class="metric-label">Total P&L</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="metric-card">
                    <div class="metric-value"><?php echo number_format($stats['avg_r'], 2); ?>R</div>
                    <div class="metric-label">Avg R-Multiple</div>
                </div>
            </div>
        </div>
        
        <!-- Main Content Grid -->
        <div class="row g-4">
            <!-- Recent Trades -->
            <div class="col-lg-8">
                <div class="dashboard-card">
                    <div class="card-header-luxury">
                        <h5 class="card-title-luxury">
                            <i class="bi bi-clock-history"></i>
                            Recent Trades
                        </h5>
                        <a href="journal.php" class="btn btn-outline-luxury btn-sm">View All</a>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table-luxury">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Instrument</th>
                                    <th>Direction</th>
                                    <th>Entry</th>
                                    <th>Exit</th>
                                    <th>P&L</th>
                                    <th>R</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="recentTradesBody">
                                <!-- Loaded via JS -->
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <div class="spinner-border text-gold spinner-border-sm me-2"></div>
                                        Loading recent trades...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
            
            <!-- Quick Stats Sidebar -->
            <div class="col-lg-4">
                <!-- System Notifications -->
                <div class="dashboard-card mb-4" id="notificationsCard">
                    <div class="card-header-luxury">
                        <h5 class="card-title-luxury">
                            <i class="bi bi-bell"></i>
                            Recent Alerts
                            <?php if ($unreadCount > 0): ?>
                                <span class="badge bg-danger rounded-pill ms-2" style="font-size: 0.6em;"><?php echo $unreadCount; ?></span>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="notification-list p-3">
                        <?php if (empty($notifications)): ?>
                            <div class="text-center text-muted-custom py-3">
                                <i class="bi bi-bell-slash d-block mb-2" style="font-size: 1.5rem;"></i>
                                No recent alerts
                            </div>
                        <?php else: ?>
                            <?php foreach ($notifications as $notif): ?>
                                <div class="notification-item mb-3 p-2 rounded <?php echo $notif['is_read'] ? 'opacity-75' : 'bg-glass-active'; ?>" style="border-left: 3px solid var(--tj-<?php echo $notif['type']; ?>);">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <h6 class="mb-0 small fw-bold text-<?php echo $notif['type'] === 'danger' ? 'red' : ($notif['type'] === 'success' ? 'green' : ($notif['type'] === 'warning' ? 'gold' : 'cyan')); ?>">
                                            <?php echo htmlspecialchars($notif['title']); ?>
                                        </h6>
                                        <span class="text-muted-custom ms-2" style="font-size: 0.7rem;"><?php echo date('H:i', strtotime($notif['created_at'])); ?></span>
                                    </div>
                                    <p class="mb-0 small text-light opacity-75" style="font-size: 0.8rem;"><?php echo htmlspecialchars($notif['message']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Performance Chart -->
                <div class="dashboard-card mb-4">
                    <div class="card-header-luxury">
                        <h5 class="card-title-luxury">
                            <i class="bi bi-graph-up"></i>
                            Weekly Performance
                        </h5>
                    </div>
                    <div class="chart-container" id="weeklyChart" style="height: 200px;">
                        <!-- Chart will be rendered here -->
                        <div class="d-flex align-items-end justify-content-between h-100 px-2 pb-3">
                            <div class="text-center flex-fill">
                                <div style="height: 60%; background: linear-gradient(to top, #10B981, #34D399); border-radius: 6px; margin: 0 4px;"></div>
                                <small class="text-muted-custom mt-2 d-block">Mon</small>
                            </div>
                            <div class="text-center flex-fill">
                                <div style="height: 40%; background: linear-gradient(to top, #EF4444, #F87171); border-radius: 6px; margin: 0 4px;"></div>
                                <small class="text-muted-custom mt-2 d-block">Tue</small>
                            </div>
                            <div class="text-center flex-fill">
                                <div style="height: 80%; background: linear-gradient(to top, #10B981, #34D399); border-radius: 6px; margin: 0 4px;"></div>
                                <small class="text-muted-custom mt-2 d-block">Wed</small>
                            </div>
                            <div class="text-center flex-fill">
                                <div style="height: 55%; background: linear-gradient(to top, #10B981, #34D399); border-radius: 6px; margin: 0 4px;"></div>
                                <small class="text-muted-custom mt-2 d-block">Thu</small>
                            </div>
                            <div class="text-center flex-fill">
                                <div style="height: 30%; background: linear-gradient(to top, #EF4444, #F87171); border-radius: 6px; margin: 0 4px;"></div>
                                <small class="text-muted-custom mt-2 d-block">Fri</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Win/Loss Distribution -->
                <div class="dashboard-card mb-4">
                    <div class="card-header-luxury">
                        <h5 class="card-title-luxury">
                            <i class="bi bi-pie-chart"></i>
                            Win/Loss Ratio
                        </h5>
                    </div>
                    <div class="d-flex align-items-center justify-content-center py-3">
                        <div class="position-relative" style="width: 120px; height: 120px;">
                            <svg viewBox="0 0 36 36" style="transform: rotate(-90deg);">
                                <circle cx="18" cy="18" r="15.9" fill="none" stroke="rgba(239, 68, 68, 0.3)" stroke-width="3"/>
                                <circle cx="18" cy="18" r="15.9" fill="none" stroke="#10B981" stroke-width="3"
                                        stroke-dasharray="<?php echo $winRate; ?>, 100"/>
                            </svg>
                            <div class="position-absolute top-50 start-50 translate-middle text-center">
                                <div class="fs-4 fw-bold text-gold"><?php echo $winRate; ?>%</div>
                                <small class="text-muted-custom">Win Rate</small>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-around mt-2">
                        <div class="text-center">
                            <div class="d-flex align-items-center gap-2">
                                <div style="width: 12px; height: 12px; background: #10B981; border-radius: 3px;"></div>
                                <span class="text-secondary"><?php echo $stats['winning_trades']; ?> Wins</span>
                            </div>
                        </div>
                        <div class="text-center">
                            <div class="d-flex align-items-center gap-2">
                                <div style="width: 12px; height: 12px; background: #EF4444; border-radius: 3px;"></div>
                                <span class="text-secondary"><?php echo $stats['losing_trades']; ?> Losses</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Notes -->
                <div class="dashboard-card">
                    <div class="card-header-luxury">
                        <h5 class="card-title-luxury">
                            <i class="bi bi-sticky"></i>
                            Quick Notes
                        </h5>
                        <button class="btn btn-sm text-gold"><i class="bi bi-plus-lg"></i></button>
                    </div>
                    <div class="text-center py-4">
                        <i class="bi bi-journal-text display-4 text-muted-custom"></i>
                        <p class="text-muted-custom mt-2 mb-0">No notes yet</p>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- View Trade Modal -->
    <div class="modal fade" id="viewTradeModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content modal-luxury">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Trade Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="tradeDetailContent">
                    <!-- Trade details loaded via JS -->
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-luxury" onclick="editTrade()">
                        <i class="bi bi-pencil me-1"></i>Edit
                    </button>
                    <button type="button" class="btn btn-danger" onclick="deleteTrade()">
                        <i class="bi bi-trash me-1"></i>Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Trade Modal -->
    <div class="modal fade" id="editTradeModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content modal-luxury">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Trade</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editTradeForm" action="api/trades/update.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="trade_id" id="editTradeId">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <h6 class="text-gold mb-3"><i class="bi bi-info-circle me-2"></i>Trade Details</h6>
                                
                                <div class="form-group">
                                    <label class="form-label-custom">Trading Account</label>
                                    <select name="account_id" id="edit_account_id" class="form-luxury form-select-luxury" required>
                                        <?php foreach ($accounts as $acc): ?>
                                        <option value="<?php echo $acc['id']; ?>"><?php echo htmlspecialchars($acc['name']) . ' (' . $acc['currency'] . ')'; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="form-label-luxury">Instrument</label>
                                    <select name="instrument_id" id="edit_instrument_id" class="form-luxury form-select-luxury" required>
                                        <?php foreach ($instruments as $inst): ?>
                                        <option value="<?php echo $inst['id']; ?>"><?php echo $inst['code'] . ' - ' . $inst['name']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label-luxury">Direction</label>
                                    <div class="d-flex gap-3">
                                        <label class="flex-fill">
                                            <input type="radio" name="direction" id="edit_direction_long" value="LONG" class="d-none">
                                            <div class="btn btn-outline-luxury w-100 edit-direction-btn" data-direction="LONG">
                                                <i class="bi bi-arrow-up-circle me-2"></i>LONG
                                            </div>
                                        </label>
                                        <label class="flex-fill">
                                            <input type="radio" name="direction" id="edit_direction_short" value="SHORT" class="d-none">
                                            <div class="btn btn-outline-luxury w-100 edit-direction-btn" data-direction="SHORT">
                                                <i class="bi bi-arrow-down-circle me-2"></i>SHORT
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="form-label-luxury">Entry Price</label>
                                            <input type="number" name="entry_price" id="edit_entry_price" class="form-luxury" step="0.00001" required>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="form-label-luxury">Exit Price</label>
                                            <input type="number" name="exit_price" id="edit_exit_price" class="form-luxury" step="0.00001">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="form-label-luxury">Stop Loss</label>
                                            <input type="number" name="stop_loss" id="edit_stop_loss" class="form-luxury" step="0.00001">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="form-label-luxury">Take Profit</label>
                                            <input type="number" name="take_profit" id="edit_take_profit" class="form-luxury" step="0.00001">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="form-label-luxury">Position Size</label>
                                            <input type="number" name="position_size" id="edit_position_size" class="form-luxury" step="0.01">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="form-label-luxury">Fees</label>
                                            <input type="number" name="fees" id="edit_fees" class="form-luxury" step="0.01">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h6 class="text-gold mb-3"><i class="bi bi-clock me-2"></i>Timing & Strategy</h6>
                                
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="form-label-luxury">Entry Time</label>
                                            <input type="datetime-local" name="entry_time" id="edit_entry_time" class="form-luxury" required>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="form-label-luxury">Exit Time</label>
                                            <input type="datetime-local" name="exit_time" id="edit_exit_time" class="form-luxury">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label class="form-label-luxury">Emotional State</label>
                                    <select name="emotional_state" id="edit_emotional_state" class="form-luxury form-select-luxury">
                                        <option value="Neutral">Neutral</option>
                                        <option value="Confident">Confident</option>
                                        <option value="Anxious">Anxious</option>
                                        <option value="Greedy">Greedy</option>
                                        <option value="Fearful">Fearful</option>
                                        <option value="Frustrated">Frustrated</option>
                                        <option value="Disciplined">Disciplined</option>
                                    </select>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="form-group mb-3">
                                            <label class="form-label-luxury">Setup Quality (1-5)</label>
                                            <select name="setup_quality" id="edit_setup_quality" class="form-luxury form-select-luxury">
                                                <option value="1">1</option>
                                                <option value="2">2</option>
                                                <option value="3">3</option>
                                                <option value="4">4</option>
                                                <option value="5">5</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group mb-3">
                                            <label class="form-label-luxury">Execution Quality (1-5)</label>
                                            <select name="execution_quality" id="edit_execution_quality" class="form-luxury form-select-luxury">
                                                <option value="1">1</option>
                                                <option value="2">2</option>
                                                <option value="3">3</option>
                                                <option value="4">4</option>
                                                <option value="5">5</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="form-label-luxury">Strategy</label>
                                    <select name="strategy_id" id="edit_strategy_id" class="form-luxury form-select-luxury">
                                        <option value="">Select Strategy</option>
                                        <?php foreach ($strategies as $strategy): ?>
                                        <option value="<?php echo $strategy['id']; ?>"><?php echo htmlspecialchars($strategy['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label-luxury">New Screenshots</label>
                                    <div class="upload-zone" id="editUploadZone">
                                        <i class="bi bi-cloud-arrow-up"></i>
                                        <p>Drag & drop to add more images</p>
                                        <input type="file" name="screenshots[]" id="editScreenshotInput" accept="image/*" multiple class="d-none">
                                    </div>
                                    <div class="image-preview-grid" id="editImagePreview"></div>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <h6 class="text-gold mb-3"><i class="bi bi-journal-text me-2"></i>Notes</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label-luxury">Entry Reason</label>
                                            <textarea name="entry_reason" id="edit_entry_reason" class="form-luxury" rows="2"></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label-luxury">Exit Reason</label>
                                            <textarea name="exit_reason" id="edit_exit_reason" class="form-luxury" rows="2"></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label-luxury">Lessons Learned</label>
                                    <textarea name="lessons_learned" id="edit_lessons_learned" class="form-luxury" rows="2"></textarea>
                                </div>
                                <div class="form-group mb-0 mt-3">
                                    <div class="form-check form-switch-luxury">
                                        <input type="checkbox" name="followed_rules" value="1" class="form-check-input" id="edit_followed_rules">
                                        <label class="form-check-label text-gold small" for="edit_followed_rules">I strictly followed my trading plan</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-luxury" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="editTradeForm" class="btn btn-luxury">
                        <i class="bi bi-check-lg me-2"></i>Update Trade
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- New Trade Modal -->

    <div class="modal fade" id="newTradeModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content modal-luxury">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add New Trade</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="newTradeForm" action="api/trades/create.php" method="POST" enctype="multipart/form-data">
                        <div class="row g-4">
                            <!-- Left Column -->
                            <div class="col-md-6">
                                <h6 class="text-gold mb-3"><i class="bi bi-info-circle me-2"></i>Trade Details</h6>
                                
                                <div class="form-group">
                                    <label class="form-label-luxury">Trading Account</label>
                                    <select name="account_id" class="form-luxury form-select-luxury" required>
                                        <?php foreach ($accounts as $acc): ?>
                                        <option value="<?php echo $acc['id']; ?>"><?php echo htmlspecialchars($acc['name']) . ' (' . $acc['currency'] . ')'; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label-luxury">Instrument</label>
                                    <select name="instrument_id" class="form-luxury form-select-luxury" required>
                                        <?php foreach ($instruments as $inst): ?>
                                        <option value="<?php echo $inst['id']; ?>"><?php echo $inst['code'] . ' - ' . $inst['name']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label-luxury">Direction</label>
                                    <div class="d-flex gap-3">
                                        <label class="flex-fill">
                                            <input type="radio" name="direction" value="LONG" class="d-none" checked>
                                            <div class="btn btn-outline-luxury w-100 direction-btn" data-direction="LONG">
                                                <i class="bi bi-arrow-up-circle me-2"></i>LONG
                                            </div>
                                        </label>
                                        <label class="flex-fill">
                                            <input type="radio" name="direction" value="SHORT" class="d-none">
                                            <div class="btn btn-outline-luxury w-100 direction-btn" data-direction="SHORT">
                                                <i class="bi bi-arrow-down-circle me-2"></i>SHORT
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- P&L Preview -->
                                <div class="glass-card p-3 bg-dark mb-3" style="border: 1px solid rgba(255, 215, 0, 0.2);">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-muted-custom" style="font-size: 0.85rem;">P&L Preview</span>
                                        <span id="pnlPreview" class="text-gold fw-bold">-</span>
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">
                                        <div>Entry: <span id="previewEntry">-</span></div>
                                        <div>Exit: <span id="previewExit">-</span></div>
                                        <div>Ticks: <span id="previewTicks">-</span></div>
                                    </div>
                                </div>
                                
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="form-label-luxury">Entry Price</label>
                                            <input type="number" name="entry_price" class="form-luxury" step="0.00001" required>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="form-label-luxury">Exit Price</label>
                                            <input type="number" name="exit_price" class="form-luxury" step="0.00001">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="form-label-luxury">Stop Loss</label>
                                            <input type="number" name="stop_loss" class="form-luxury" step="0.00001">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="form-label-luxury">Take Profit</label>
                                            <input type="number" name="take_profit" class="form-luxury" step="0.00001">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="form-label-luxury">Position Size</label>
                                            <input type="number" name="position_size" class="form-luxury" value="1" step="0.01" min="0.01">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="form-label-luxury">Fees</label>
                                            <div class="input-group-luxury">
                                                <input type="number" name="fees" class="form-luxury" value="0" step="0.01">
                                                <span class="input-suffix account-currency-label">USD</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Right Column -->
                            <div class="col-md-6">
                                <h6 class="text-gold mb-3"><i class="bi bi-clock me-2"></i>Timing & Strategy</h6>
                                
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="form-label-luxury">Entry Time</label>
                                            <input type="datetime-local" name="entry_time" class="form-luxury" required>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="form-label-luxury">Exit Time</label>
                                            <input type="datetime-local" name="exit_time" class="form-luxury">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label-luxury">Strategy</label>
                                    <select name="strategy_id" class="form-luxury form-select-luxury">
                                        <option value="">Select Strategy</option>
                                        <?php foreach ($strategies as $strategy): ?>
                                        <option value="<?php echo $strategy['id']; ?>"><?php echo htmlspecialchars($strategy['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label-luxury">Emotional State</label>
                                    <select name="emotional_state" class="form-luxury form-select-luxury">
                                        <option value="Neutral" selected>Neutral</option>
                                        <option value="Confident">Confident</option>
                                        <option value="Anxious">Anxious</option>
                                        <option value="Greedy">Greedy</option>
                                        <option value="Fearful">Fearful</option>
                                        <option value="Frustrated">Frustrated</option>
                                        <option value="Disciplined">Disciplined</option>
                                    </select>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="form-label-luxury">Setup quality (1-5)</label>
                                            <select name="setup_quality" class="form-luxury form-select-luxury">
                                                <option value="1">1 - Poor</option>
                                                <option value="2">2 - Below Avg</option>
                                                <option value="3" selected>3 - Average</option>
                                                <option value="4">4 - Good</option>
                                                <option value="5">5 - Perfect</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="form-label-luxury">Execution quality (1-5)</label>
                                            <select name="execution_quality" class="form-luxury form-select-luxury">
                                                <option value="1">1 - Poor</option>
                                                <option value="2">2 - Below Avg</option>
                                                <option value="3" selected>3 - Average</option>
                                                <option value="4">4 - Good</option>
                                                <option value="5">5 - Perfect</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label-luxury">Screenshots</label>
                                    <div class="upload-zone" id="uploadZone">
                                        <i class="bi bi-cloud-arrow-up"></i>
                                        <p>Drag & drop images or click to browse</p>
                                        <small>PNG, JPG, WebP up to 5MB each</small>
                                        <input type="file" name="screenshots[]" id="screenshotInput" 
                                               accept="image/*" multiple class="d-none">
                                    </div>
                                    <div class="image-preview-grid" id="imagePreview"></div>
                                </div>
                            </div>
                            
                            <!-- Full Width Notes -->
                            <div class="col-12">
                                <h6 class="text-gold mb-3"><i class="bi bi-journal-text me-2"></i>Notes & Analysis</h6>
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label-luxury">Entry Reason</label>
                                            <textarea name="entry_reason" class="form-luxury" rows="2" 
                                                      placeholder="Why did you enter this trade?"></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label-luxury">Exit Reason</label>
                                            <textarea name="exit_reason" class="form-luxury" rows="2" 
                                                      placeholder="Why did you exit?"></textarea>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label-luxury">Lessons Learned</label>
                                    <textarea name="lessons_learned" class="form-luxury" rows="2" 
                                              placeholder="What did you learn from this trade?"></textarea>
                                </div>
                                
                                <div class="form-check">
                                    <input type="checkbox" name="followed_rules" value="1" class="form-check-input" id="followedRules" checked>
                                    <label class="form-check-label text-secondary" for="followedRules">
                                        I followed my trading rules
                                    </label>
                                </div>
                                <div class="form-group mb-0 mt-3">
                                    <div class="form-check form-switch-luxury">
                                        <input type="checkbox" name="followed_rules" value="1" class="form-check-input" id="new_followed_rules" checked>
                                        <label class="form-check-label text-gold small" for="new_followed_rules">I strictly followed my trading plan</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-luxury" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="newTradeForm" class="btn btn-luxury">
                        <i class="bi bi-check-lg me-2"></i>Save Trade
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Mobile Menu Toggle -->
    <button class="btn btn-luxury d-lg-none position-fixed" 
            style="bottom: 2rem; right: 2rem; z-index: 1001; width: 56px; height: 56px; border-radius: 50%; padding: 0;"
            onclick="toggleSidebar()">
        <i class="bi bi-list fs-4"></i>
    </button>
    
    <!-- Bootstrap JS -->
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content modal-luxury">
                <div class="modal-header border-0">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <p class="mb-0 text-white">Are you sure you want to delete this trade?</p>
                    <small class="text-muted-custom">This action cannot be undone.</small>
                </div>
                <div class="modal-footer border-0 pt-0 justify-content-center">
                    <button type="button" class="btn btn-outline-luxury px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger px-4" id="confirmDeleteBtn">Delete</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle sidebar on mobile
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }
        
        // Direction toggle buttons
        document.querySelectorAll('.direction-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.direction-btn').forEach(b => b.classList.remove('active', 'btn-luxury'));
                document.querySelectorAll('.direction-btn').forEach(b => b.classList.add('btn-outline-luxury'));
                this.classList.remove('btn-outline-luxury');
                this.classList.add('btn-luxury', 'active');
                
                const direction = this.dataset.direction;
                document.querySelector(`input[value="${direction}"]`).checked = true;
            });
        });
        
        // Set initial active direction
        document.querySelector('.direction-btn[data-direction="LONG"]').classList.add('active', 'btn-luxury');
        document.querySelector('.direction-btn[data-direction="LONG"]').classList.remove('btn-outline-luxury');
        
        // Initialize rating display
        // Removed legacy star rating listeners
        
        // P&L Preview Calculator
        function calculatePnL() {
            const entryPrice = parseFloat(document.querySelector('#newTradeForm input[name="entry_price"]')?.value) || 0;
            const exitPrice = parseFloat(document.querySelector('#newTradeForm input[name="exit_price"]')?.value) || 0;
            const positionSize = parseFloat(document.querySelector('#newTradeForm input[name="position_size"]')?.value) || 0;
            const instrumentId = parseInt(document.querySelector('#newTradeForm select[name="instrument_id"]')?.value) || 0;
            const direction = document.querySelector('#newTradeForm input[name="direction"]:checked')?.value || 'LONG';
            
            if (!entryPrice || !exitPrice || !positionSize || !instrumentId || !instrumentTickData[instrumentId]) {
                document.getElementById('pnlPreview').textContent = '-';
                document.getElementById('previewEntry').textContent = '-';
                document.getElementById('previewExit').textContent = '-';
                document.getElementById('previewTicks').textContent = '-';
                return;
            }
            
            const tickData = instrumentTickData[instrumentId];
            const tickSize = tickData.tick_size;
            const tickValue = tickData.tick_value;
            
            // Calculate price difference in ticks
            const priceDiff = Math.abs(exitPrice - entryPrice);
            const ticks = Math.round(priceDiff / tickSize);
            
            // Calculate P&L
            const grossPnl = ticks * tickValue * positionSize;
            
            // Format and display
            document.getElementById('previewEntry').textContent = entryPrice.toFixed(5);
            document.getElementById('previewExit').textContent = exitPrice.toFixed(5);
            document.getElementById('previewTicks').textContent = ticks;
            
            const pnlClass = grossPnl >= 0 ? 'text-green' : 'text-red';
            const pnlSign = grossPnl >= 0 ? '+' : '';
            document.getElementById('pnlPreview').textContent = pnlSign + grossPnl.toFixed(2);
            document.getElementById('pnlPreview').className = pnlClass + ' fw-bold';
        }
        
        // Attach listeners to P&L calculation
        document.querySelector('#newTradeForm input[name="entry_price"]')?.addEventListener('input', calculatePnL);
        document.querySelector('#newTradeForm input[name="exit_price"]')?.addEventListener('input', calculatePnL);
        document.querySelector('#newTradeForm input[name="position_size"]')?.addEventListener('input', calculatePnL);
        document.querySelector('#newTradeForm select[name="instrument_id"]')?.addEventListener('change', calculatePnL);
        document.querySelectorAll('#newTradeForm input[name="direction"]')?.forEach(radio => radio.addEventListener('change', calculatePnL));
        
        // File upload zone
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('screenshotInput');
        const imagePreview = document.getElementById('imagePreview');
        let uploadedFiles = [];
        
        uploadZone.addEventListener('click', () => fileInput.click());
        
        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });
        
        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('dragover');
        });
        
        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            handleFiles(e.dataTransfer.files);
        });
        
        fileInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });
        
        function handleFiles(files) {
            Array.from(files).forEach(file => {
                if (file.type.startsWith('image/') && file.size <= 5 * 1024 * 1024) {
                    uploadedFiles.push(file);
                    
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const div = document.createElement('div');
                        div.className = 'image-preview-item';
                        div.innerHTML = `
                            <img src="${e.target.result}" alt="Preview">
                            <button type="button" class="remove-btn" onclick="removeImage(this, ${uploadedFiles.length - 1})">
                                <i class="bi bi-x"></i>
                            </button>
                        `;
                        imagePreview.appendChild(div);
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
        
        function removeImage(btn, index) {
            btn.closest('.image-preview-item').remove();
            uploadedFiles.splice(index, 1);
        }
        
        // Update currency labels on account change
        const accountSelect = document.querySelector('select[name="account_id"]');
        if (accountSelect) {
            accountSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex].text;
                const currency = selectedOption.includes('(USC)') ? 'USC' : 'USD';
                document.querySelectorAll('.account-currency-label').forEach(label => {
                    label.textContent = currency;
                });
            });
            // Trigger once on load
            accountSelect.dispatchEvent(new Event('change'));
        }

        // Form submission
        document.getElementById('newTradeForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            // Add uploaded files
            uploadedFiles.forEach(file => {
                formData.append('screenshots[]', file);
            });
            
            try {
                const response = await fetch(this.action, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Close modal and refresh
                    bootstrap.Modal.getInstance(document.getElementById('newTradeModal')).hide();
                    window.location.reload();
                } else {
                    alert(data.message || 'Error saving trade');
                }
            } catch (error) {
                alert('An error occurred. Please try again.');
            }
        });
        
        // Set current datetime as default
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        const entryTimeInput = document.querySelector('#newTradeForm input[name="entry_time"]');
        if (entryTimeInput) entryTimeInput.value = now.toISOString().slice(0, 16);

        // --- DASHBOARD TRADE MANAGEMENT ---
        let currentTradeId = null;
        let currentTradeScreenshots = [];

        function formatDate(dateStr) {
            if (!dateStr) return '—';
            const date = new Date(dateStr.replace(' ', 'T'));
            if (isNaN(date.getTime())) return dateStr;

            const d = date.toLocaleDateString('en-US', { month: 'numeric', day: 'numeric', year: 'numeric' });
            const t = date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
            
            return `<div class="date-stacked">
                        <span class="date-main">${d}</span>
                        <span class="time-sub">${t}</span>
                    </div>`;
        }

        async function loadRecentTrades() {
            try {
                const response = await fetch('api/trades/list.php?limit=10');
                const data = await response.json();
                
                const tbody = document.getElementById('recentTradesBody');
                if (!data.success || data.trades.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-5 text-muted-custom"><i class="bi bi-journal-x d-block fs-2 mb-2 opacity-50"></i>No recent execution history found.</td></tr>';
                    return;
                }

                tbody.innerHTML = data.trades.map(trade => {
                    const pnl = parseFloat(trade.net_pnl || trade.pnl || 0);
                    const pnlClass = pnl >= 0 ? 'text-green' : 'text-red';
                    const pnlPrefix = pnl >= 0 ? '+' : '';
                    const currency = trade.account_currency || 'USD';
                    
                    return `
                        <tr>
                            <td>${formatDate(trade.entry_time)}</td>
                            <td>
                                <div class="fw-bold text-gold">${trade.instrument_code}</div>
                                <small class="text-muted-custom smaller opacity-75">${trade.instrument_name || ''}</small>
                            </td>
                            <td>
                                <span class="${trade.direction === 'LONG' ? 'badge-long' : 'badge-short'}">
                                    ${trade.direction}
                                </span>
                            </td>
                            <td class="font-monospace">${parseFloat(trade.entry_price).toFixed(trade.entry_price < 1 ? 5 : 2)}</td>
                            <td class="font-monospace">${trade.exit_price ? parseFloat(trade.exit_price).toFixed(trade.exit_price < 1 ? 5 : 2) : '—'}</td>
                            <td class="font-monospace fw-bold ${pnlClass}">
                                ${pnlPrefix}${pnl.toFixed(2)} <small class="text-muted-custom">${currency}</small>
                            </td>
                            <td>
                                <span class="badge bg-glass text-gold border border-glass" style="font-size: 0.7rem;">${parseFloat(trade.r_multiple || 0).toFixed(2)}R</span>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-action" onclick="viewTrade(${trade.id})" title="View Details">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-action text-blue" onclick="editTrade(${trade.id})" title="Edit Trade">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                }).join('');
            } catch (error) {
                console.error('Error loading trades:', error);
                document.getElementById('recentTradesBody').innerHTML = '<tr><td colspan="8" class="text-center py-4 text-red">Error loading trades.</td></tr>';
            }
        }

        async function viewTrade(id) {
            currentTradeId = id;
            try {
                const response = await fetch(`api/trades/get.php?id=${id}`);
                const data = await response.json();
                
                if (data.success) {
                    renderTradeDetail(data.trade);
                    new bootstrap.Modal(document.getElementById('viewTradeModal')).show();
                } else {
                    alert(data.message || 'Error fetching trade details');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while fetching trade details.');
            }
        }

        function renderTradeDetail(trade) {
            const pnlClass = parseFloat(trade.net_pnl) >= 0 ? 'text-green' : 'text-red';
            const rClass = parseFloat(trade.r_multiple) >= 0 ? 'text-green' : 'text-red';
            const currency = trade.account_currency || 'USD';
            const accountName = trade.account_name || 'Personal';
            
            let attachmentsHtml = '';
            if (trade.attachments && trade.attachments.length > 0) {
                attachmentsHtml = `
                    <div class="col-12 mt-2">
                        <div class="glass-card">
                            <h6 class="text-gold mb-3 d-flex align-items-center">
                                <i class="bi bi-images me-2"></i>Trade Evidence
                                <span class="ms-auto badge bg-glass text-muted-custom font-monospace" style="font-size: 0.65rem;">${trade.attachments.length} ATTACHMENTS</span>
                            </h6>
                            <div class="row g-3">
                                ${trade.attachments.map(a => {
                                    const imgPath = a.file_path.startsWith('/') ? a.file_path.substring(1) : a.file_path;
                                    return `
                                        <div class="col-md-4 col-sm-6">
                                            <div class="screenshot-container shadow-sm border-luxury rounded overflow-hidden">
                                                <a href="${imgPath}" target="_blank">
                                                    <img src="${imgPath}" class="img-fluid w-100" style="cursor: zoom-in; transition: transform 0.3s ease;">
                                                </a>
                                            </div>
                                        </div>
                                    `;
                                }).join('')}
                            </div>
                        </div>
                    </div>
                `;
            } else {
                attachmentsHtml = `
                    <div class="col-12 mt-2">
                        <div class="glass-card text-center py-5 border-dashed">
                            <i class="bi bi-camera-video text-muted d-block mb-3 opacity-25" style="font-size: 2.5rem;"></i>
                            <p class="text-muted-custom mb-0 small text-uppercase letter-spacing-1">Visual documentation unavailable for this execution</p>
                        </div>
                    </div>
                `;
            }
            
            document.getElementById('tradeDetailContent').innerHTML = `
                <div class="row g-3">
                    <!-- Left Column: Core Data -->
                    <div class="col-md-7">
                        <div class="glass-card h-100">
                            <div class="d-flex justify-content-between align-items-start mb-4">
                                <div>
                                    <h4 class="text-gold mb-1 font-monospace">${trade.instrument_code || 'N/A'}</h4>
                                    <div class="d-flex gap-2 align-items-center">
                                        <span class="${trade.direction === 'LONG' ? 'badge-long' : 'badge-short'} px-3">${trade.direction}</span>
                                        <span class="text-muted-custom smaller border-start ps-2">${accountName}</span>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="text-muted-custom smaller text-uppercase">Execution ID</div>
                                    <div class="font-monospace small text-gold">#TRD-${String(trade.id).padStart(5, '0')}</div>
                                </div>
                            </div>

                            <div class="row g-4 mt-2">
                                <div class="col-6 col-sm-4 border-end border-glass">
                                    <small class="text-muted-custom d-block text-uppercase smaller mb-1">Entry Quote</small>
                                    <h5 class="mb-0 font-monospace">${parseFloat(trade.entry_price).toFixed(trade.entry_price < 1 ? 5 : 2)}</h5>
                                </div>
                                <div class="col-6 col-sm-4 border-end border-glass">
                                    <small class="text-muted-custom d-block text-uppercase smaller mb-1">Exit Quote</small>
                                    <h5 class="mb-0 font-monospace">${trade.exit_price ? parseFloat(trade.exit_price).toFixed(trade.exit_price < 1 ? 5 : 2) : '—'}</h5>
                                </div>
                                <div class="col-12 col-sm-4">
                                    <small class="text-muted-custom d-block text-uppercase smaller mb-1">Position Size</small>
                                    <h5 class="mb-0 font-monospace">${trade.position_size} <span class="smaller text-muted-custom font-sans">units</span></h5>
                                </div>
                            </div>

                            <hr class="my-4 opacity-10">

                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <i class="bi bi-clock-history text-cyan"></i>
                                        <small class="text-muted-custom text-uppercase smaller">Time of Entry</small>
                                    </div>
                                    <div class="ps-4 fw-medium">${formatDate(trade.entry_time)}</div>
                                </div>
                                <div class="col-6">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <i class="bi bi-clock text-pink"></i>
                                        <small class="text-muted-custom text-uppercase smaller">Time of Exit</small>
                                    </div>
                                    <div class="ps-4 fw-medium">${trade.exit_time ? formatDate(trade.exit_time) : '<span class="text-warning">ACTIVE</span>'}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column: Performance Metrics -->
                    <div class="col-md-5">
                        <div class="glass-card glass-card-cyan h-100">
                            <h6 class="text-cyan mb-4 d-flex align-items-center">
                                <i class="bi bi-cpu me-2"></i>Performance Analysis
                            </h6>
                            
                            <div class="p-3 rounded bg-glass mb-4 border-start border-4 ${parseFloat(trade.net_pnl) >= 0 ? 'border-green' : 'border-red'}">
                                <small class="text-muted-custom d-block text-uppercase mb-1">Net Realized P&L</small>
                                <div class="d-flex align-items-baseline gap-2">
                                    <h2 class="${pnlClass} mb-0 font-monospace">${parseFloat(trade.net_pnl).toFixed(2)}</h2>
                                    <span class="text-muted-custom small">${currency}</span>
                                </div>
                            </div>

                            <div class="row g-3 text-center">
                                <div class="col-6">
                                    <div class="border border-glass rounded p-3">
                                        <small class="text-muted-custom d-block smaller text-uppercase mb-1">R-Score</small>
                                        <h4 class="${rClass} mb-0 font-monospace">${parseFloat(trade.r_multiple).toFixed(2)}R</h4>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border border-glass rounded p-3">
                                        <small class="text-muted-custom d-block smaller text-uppercase mb-1">Status</small>
                                        <span class="badge ${trade.status === 'OPEN' ? 'bg-warning-subtle text-warning border border-warning' : 'bg-success-subtle text-green border border-green'} text-uppercase font-monospace" style="font-size: 0.7rem;">${trade.status}</span>
                                    </div>
                                </div>
                                <div class="col-12 mt-3">
                                    <div class="d-flex justify-content-between px-2">
                                        <span class="smaller text-muted-custom">GROSS P&L</span>
                                        <span class="smaller font-monospace text-white">${parseFloat(trade.gross_pnl).toFixed(2)} ${currency}</span>
                                    </div>
                                    <div class="d-flex justify-content-between px-2 mt-1">
                                        <span class="smaller text-muted-custom">TOTAL FEES</span>
                                        <span class="smaller font-monospace text-red">-${parseFloat(trade.fees).toFixed(2)} ${currency}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Full Width: Documentation Section -->
                    <div class="col-12">
                        <div class="glass-card">
                            <h6 class="text-gold mb-3 d-flex align-items-center">
                                <i class="bi bi-pencil-square me-2"></i>Documentation & Methodology
                            </h6>
                            <div class="row g-4">
                                <div class="col-md-4 border-end border-glass">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <div class="bullet bullet-gold"></div>
                                        <small class="text-muted-custom text-uppercase smaller">Hypothesis / Entry Reason</small>
                                    </div>
                                    <p class="mb-0 text-white-50 small pe-3">${trade.entry_reason || 'Methodology not documented.'}</p>
                                </div>
                                <div class="col-md-4 border-end border-glass">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <div class="bullet bullet-cyan"></div>
                                        <small class="text-muted-custom text-uppercase smaller">Exit Rationale</small>
                                    </div>
                                    <p class="mb-0 text-white-50 small pe-3">${trade.exit_reason || 'Manual liquidation or automation.'}</p>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <div class="bullet bullet-purple"></div>
                                        <small class="text-muted-custom text-uppercase smaller">Lessons & Insights</small>
                                    </div>
                                    <p class="mb-0 text-white-50 small italic opacity-75">${trade.lessons_learned || '—'}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    ${attachmentsHtml}
                </div>
            `;
        }

        async function editTrade(id = null) {
            const tradeId = id || currentTradeId;
            if (!tradeId) return;

            try {
                const response = await fetch(`api/trades/get.php?id=${tradeId}`);
                const data = await response.json();
                
                if (data.success) {
                    const trade = data.trade;
                    document.getElementById('editTradeId').value = trade.id;
                    document.getElementById('edit_account_id').value = trade.account_id;
                    document.getElementById('edit_instrument_id').value = trade.instrument_id;
                    document.getElementById('edit_entry_price').value = trade.entry_price;
                    document.getElementById('edit_exit_price').value = trade.exit_price;
                    document.getElementById('edit_stop_loss').value = trade.stop_loss;
                    document.getElementById('edit_take_profit').value = trade.take_profit;
                    document.getElementById('edit_position_size').value = trade.position_size;
                    document.getElementById('edit_fees').value = trade.fees;
                    document.getElementById('edit_emotional_state').value = trade.emotional_state || 'Neutral';
                    document.getElementById('edit_setup_quality').value = trade.setup_quality || 3;
                    document.getElementById('edit_execution_quality').value = trade.execution_quality || 3;
                    document.getElementById('edit_strategy_id').value = trade.strategy_id || '';
                    document.getElementById('edit_entry_reason').value = trade.entry_reason || '';
                    document.getElementById('edit_exit_reason').value = trade.exit_reason || '';
                    document.getElementById('edit_lessons_learned').value = trade.lessons_learned || '';
                    document.getElementById('edit_followed_rules').checked = parseInt(trade.followed_rules) === 1;
                    document.getElementById('edit_followed_rules').checked = parseInt(trade.followed_rules) === 1;

                    // Direction
                    const editDirInput = document.querySelector(`#editTradeForm input[name="direction"][value="${trade.direction}"]`);
                    if (editDirInput) editDirInput.checked = true;
                    
                    document.querySelectorAll('.edit-direction-btn').forEach(btn => {
                        if (btn.dataset.direction === trade.direction) {
                            btn.classList.add('active');
                            btn.classList.remove('btn-outline-luxury');
                            btn.classList.add(trade.direction === 'LONG' ? 'btn-success' : 'btn-danger');
                        } else {
                            btn.classList.remove('active', 'btn-success', 'btn-danger');
                            btn.classList.add('btn-outline-luxury');
                        }
                    });

                    // Format dates for datetime-local
                    if (trade.entry_time) {
                        document.getElementById('edit_entry_time').value = trade.entry_time.replace(' ', 'T').substring(0, 16);
                    }
                    if (trade.exit_time) {
                        document.getElementById('edit_exit_time').value = trade.exit_time.replace(' ', 'T').substring(0, 16);
                    }

                    // Reset uploads
                    currentTradeScreenshots = [];
                    document.getElementById('editImagePreview').innerHTML = '';
                    
                    // Close view modal if open
                    const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewTradeModal'));
                    if (viewModal) viewModal.hide();

                    initEditForm();
                    new bootstrap.Modal(document.getElementById('editTradeModal')).show();
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error fetching trade for edit');
            }
        }

        function initEditForm() {
            const directionBtns = document.querySelectorAll('.edit-direction-btn');
            directionBtns.forEach(btn => {
                btn.onclick = function() {
                    const dir = this.dataset.direction;
                    document.getElementById(`edit_direction_${dir.toLowerCase()}`).checked = true;
                    
                    directionBtns.forEach(b => {
                        b.classList.remove('active', 'btn-success', 'btn-danger');
                        b.classList.add('btn-outline-luxury');
                    });
                    
                    this.classList.add('active');
                    this.classList.remove('btn-outline-luxury');
                    this.classList.add(dir === 'LONG' ? 'btn-success' : 'btn-danger');
                };
            });

            // Re-bind file upload for edit
            const editZone = document.getElementById('editUploadZone');
            const editInput = document.getElementById('editScreenshotInput');

            editZone.onclick = () => editInput.click();
            editInput.onchange = (e) => handleEditFiles(e.target.files);

            editZone.ondragover = (e) => { e.preventDefault(); editZone.classList.add('dragover'); };
            editZone.ondragleave = () => editZone.classList.remove('dragover');
            editZone.ondrop = (e) => {
                e.preventDefault();
                editZone.classList.remove('dragover');
                handleEditFiles(e.dataTransfer.files);
            };
        }

        function handleEditFiles(files) {
            const preview = document.getElementById('editImagePreview');
            Array.from(files).forEach(file => {
                if (!file.type.startsWith('image/')) return;
                
                currentTradeScreenshots.push(file);
                const reader = new FileReader();
                reader.onload = (e) => {
                    const div = document.createElement('div');
                    div.className = 'image-preview-item';
                    const index = currentTradeScreenshots.length - 1;
                    div.innerHTML = `
                        <img src="${e.target.result}">
                        <button type="button" class="remove-btn" onclick="removeEditImage(this, ${index})">&times;</button>
                    `;
                    preview.appendChild(div);
                };
                reader.readAsDataURL(file);
            });
        }

        function removeEditImage(btn, index) {
            btn.closest('.image-preview-item').remove();
            // For simplicity, we just clear the reference in the array
            currentTradeScreenshots[index] = null;
        }

        async function deleteTrade() {
            if (!currentTradeId) return;
            new bootstrap.Modal(document.getElementById('deleteConfirmModal')).show();
        }

        document.getElementById('confirmDeleteBtn').addEventListener('click', async function() {
            if (!currentTradeId) return;
            
            try {
                const response = await fetch('api/trades/delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `trade_id=${currentTradeId}`
                });
                const data = await response.json();
                if (data.success) {
                    // Hide both modals
                    bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal')).hide();
                    const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewTradeModal'));
                    if (viewModal) viewModal.hide();
                    
                    location.reload();
                } else {
                    alert(data.message || 'Error deleting trade');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while deleting the trade.');
            }
        });

        // Edit form submission
        document.getElementById('editTradeForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            currentTradeScreenshots.forEach(file => {
                if (file) formData.append('screenshots[]', file);
            });

            try {
                const response = await fetch(this.action, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Error updating trade');
                }
            } catch (error) {
                alert('An error occurred');
            }
        });

        // Initialize dashboard
        loadRecentTrades();

        // Dashboard Limit Reset Timer
        <?php if ($isLimitReached): ?>
        (function() {
            const timerSpan = document.getElementById('dashboardResetTimer');
            if (!timerSpan) return;

            function updateDashboardTimer() {
                const now = new Date();
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                tomorrow.setHours(0, 0, 0, 0);
                
                const diff = tomorrow - now;
                if (diff <= 0) {
                    location.reload();
                    return;
                }
                
                const h = Math.floor(diff / (1000 * 60 * 60));
                const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const s = Math.floor((diff % (1000 * 60)) / 1000);
                
                timerSpan.innerText = 
                    String(h).padStart(2, '0') + ':' + 
                    String(m).padStart(2, '0') + ':' + 
                    String(s).padStart(2, '0');
            }
            
            updateDashboardTimer();
            setInterval(updateDashboardTimer, 1000);
        })();
        <?php endif; ?>
    </script>
    <script>
        // Mark notifications as read after 3 seconds
        setTimeout(async () => {
            const badge = document.querySelector('#notificationsCard .badge');
            if (badge) {
                try {
                    const response = await fetch('api/user/mark_notifications_read.php');
                    const data = await response.json();
                    if (data.success) {
                        badge.remove();
                        document.querySelectorAll('.notification-item').forEach(item => {
                            item.classList.remove('bg-glass-active');
                            item.classList.add('opacity-75');
                        });
                    }
                } catch (error) {
                    console.error('Error marking notifications as read:', error);
                }
            }
        }, 5000);
    </script>
</body>
</html>
