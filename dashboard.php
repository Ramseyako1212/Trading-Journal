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
    
    <!-- Sidebar -->
    <aside class="sidebar-luxury" id="sidebar">
        <div class="sidebar-brand">
            <a href="dashboard.php" class="brand-logo">
                <div class="logo-icon">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                Trading Journal
            </a>
        </div>
        
        <nav>
            <ul class="sidebar-nav">
                <li class="sidebar-nav-item">
                    <a href="dashboard.php" class="sidebar-nav-link active">
                        <i class="bi bi-speedometer2"></i>
                        Dashboard
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="journal.php" class="sidebar-nav-link">
                        <i class="bi bi-journal-richtext"></i>
                        Trade Journal
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="analytics.php" class="sidebar-nav-link">
                        <i class="bi bi-bar-chart-line"></i>
                        Analytics
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="calendar.php" class="sidebar-nav-link">
                        <i class="bi bi-calendar3"></i>
                        Calendar
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="strategies.php" class="sidebar-nav-link">
                        <i class="bi bi-lightbulb"></i>
                        Strategies
                    </a>
                </li>
                
                <li class="mt-4 mb-2">
                    <small class="text-muted-custom text-uppercase px-3" style="font-size: 0.7rem; letter-spacing: 0.1em;">Account</small>
                </li>
                
                <li class="sidebar-nav-item">
                    <a href="settings.php" class="sidebar-nav-link">
                        <i class="bi bi-gear"></i>
                        Settings
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="api/auth/logout.php" class="sidebar-nav-link">
                        <i class="bi bi-box-arrow-left"></i>
                        Logout
                    </a>
                </li>
            </ul>
        </nav>
        
        <!-- Quick Stats in Sidebar -->
        <div class="mt-auto pt-4">
            <div class="glass-card p-3" style="border-radius: 12px;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <small class="text-muted-custom">Today's P&L</small>
                    <span class="text-green fw-bold">+$0</span>
                </div>
                <div class="progress" style="height: 4px; background: var(--bg-glass);">
                    <div class="progress-bar" style="width: 0%; background: var(--gradient-gold);"></div>
                </div>
            </div>
        </div>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1">Welcome back, <span class="text-gold"><?php echo htmlspecialchars($userName); ?></span></h4>
                <p class="text-muted-custom mb-0">Here's your trading overview</p>
            </div>
            <div class="d-flex gap-3">
                <button class="btn btn-outline-luxury" onclick="openQuickAdd()">
                    <i class="bi bi-plus-lg me-2"></i>Quick Add
                </button>
                <button class="btn btn-luxury" data-bs-toggle="modal" data-bs-target="#newTradeModal">
                    <i class="bi bi-plus-circle me-2"></i>New Trade
                </button>
            </div>
        </div>
        
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
                                
                                <div class="form-group">
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
                                    <label class="form-label-luxury">Setup Quality (1-5)</label>
                                    <div class="rating-stars" id="setupRating">
                                        <i class="bi bi-star-fill" data-rating="1"></i>
                                        <i class="bi bi-star-fill" data-rating="2"></i>
                                        <i class="bi bi-star-fill" data-rating="3"></i>
                                        <i class="bi bi-star-fill" data-rating="4"></i>
                                        <i class="bi bi-star-fill" data-rating="5"></i>
                                    </div>
                                    <input type="hidden" name="setup_quality" id="setupQualityInput" value="3">
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
        
        // Star rating
        document.querySelectorAll('#setupRating i').forEach(star => {
            star.addEventListener('click', function() {
                const rating = this.dataset.rating;
                document.getElementById('setupQualityInput').value = rating;
                
                document.querySelectorAll('#setupRating i').forEach((s, idx) => {
                    if (idx < rating) {
                        s.classList.add('active');
                    } else {
                        s.classList.remove('active');
                    }
                });
            });
        });
        
        // Initialize rating display
        document.querySelectorAll('#setupRating i').forEach((s, idx) => {
            if (idx < 3) s.classList.add('active');
        });
        
        // P&L Preview Calculator
        function calculatePnL() {
            const entryPrice = parseFloat(document.querySelector('input[name="entry_price"]')?.value) || 0;
            const exitPrice = parseFloat(document.querySelector('input[name="exit_price"]')?.value) || 0;
            const positionSize = parseFloat(document.querySelector('input[name="position_size"]')?.value) || 0;
            const instrumentId = parseInt(document.querySelector('select[name="instrument_id"]')?.value) || 0;
            const direction = document.querySelector('input[name="direction"]:checked')?.value || 'LONG';
            
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
        document.querySelector('input[name="entry_price"]')?.addEventListener('input', calculatePnL);
        document.querySelector('input[name="exit_price"]')?.addEventListener('input', calculatePnL);
        document.querySelector('input[name="position_size"]')?.addEventListener('input', calculatePnL);
        document.querySelector('select[name="instrument_id"]')?.addEventListener('change', calculatePnL);
        document.querySelectorAll('input[name="direction"]')?.forEach(radio => radio.addEventListener('change', calculatePnL));
        
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
        document.querySelector('input[name="entry_time"]').value = now.toISOString().slice(0, 16);

        // --- DASHBOARD TRADE MANAGEMENT ---
        let currentTradeId = null;
        let currentTradeScreenshots = [];

        async function loadRecentTrades() {
            try {
                const response = await fetch('api/trades/list.php?limit=10');
                const data = await response.json();
                
                const tbody = document.getElementById('recentTradesBody');
                if (!data.success || data.trades.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted-custom">No recent trades found.</td></tr>';
                    return;
                }

                tbody.innerHTML = data.trades.map(trade => {
                    const pnl = parseFloat(trade.pnl || 0);
                    const pnlClass = pnl >= 0 ? 'text-green' : 'text-red';
                    const pnlPrefix = pnl >= 0 ? '+' : '';
                    
                    return `
                        <tr>
                            <td>
                                <div class="d-flex flex-column">
                                    <span>${new Date(trade.entry_time).toLocaleDateString()}</span>
                                    <small class="text-muted-custom" style="font-size: 0.75rem;">
                                        ${new Date(trade.entry_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                                    </small>
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold text-gold">${trade.instrument_code}</div>
                            </td>
                            <td>
                                <span class="badge ${trade.direction === 'LONG' ? 'bg-success-subtle text-green' : 'bg-danger-subtle text-red'} " style="font-size: 0.7rem;">
                                    ${trade.direction}
                                </span>
                            </td>
                            <td class="font-monospace">${parseFloat(trade.entry_price).toFixed(trade.entry_price < 1 ? 5 : 2)}</td>
                            <td class="font-monospace">${trade.exit_price ? parseFloat(trade.exit_price).toFixed(trade.exit_price < 1 ? 5 : 2) : '-'}</td>
                            <td class="font-monospace fw-bold ${pnlClass}">${pnlPrefix}${pnl.toFixed(2)} ${trade.account_currency}</td>
                            <td>
                                <span class="badge bg-luxury text-gold" style="font-size: 0.7rem;">${parseFloat(trade.r_multiple || 0).toFixed(2)}R</span>
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
            const container = document.getElementById('tradeDetailContent');
            const pnl = parseFloat(trade.net_pnl || trade.pnl || 0);
            const pnlClass = pnl >= 0 ? 'text-green' : 'text-red';
            const priceDecimals = trade.entry_price < 1 ? 5 : 2;
            const currency = trade.account_currency || 'USD';

            let screenshotsHtml = '';
            if (trade.attachments && trade.attachments.length > 0) {
                screenshotsHtml = `
                    <div class="col-12 mt-4">
                        <h6 class="text-gold mb-3"><i class="bi bi-images me-2"></i>Trade Screenshots (${trade.attachments.length})</h6>
                        <div class="row g-3">
                            ${trade.attachments.map(att => {
                                const imgPath = att.file_path.startsWith('/') ? att.file_path.substring(1) : att.file_path;
                                return `
                                    <div class="col-md-4 col-sm-6">
                                        <div class="screenshot-container">
                                            <a href="${imgPath}" target="_blank">
                                                <img src="${imgPath}" class="img-fluid rounded border-luxury" style="cursor: zoom-in;">
                                            </a>
                                        </div>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    </div>
                `;
            } else {
                screenshotsHtml = `
                    <div class="col-12 mt-4">
                        <div class="glass-card text-center py-4">
                            <i class="bi bi-image text-muted d-block mb-2" style="font-size: 2rem;"></i>
                            <p class="text-muted-custom mb-0">No screenshots attached.</p>
                        </div>
                    </div>
                `;
            }

            container.innerHTML = `
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="detail-card">
                            <label>Instrument</label>
                            <div class="value text-gold">${trade.instrument_code}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="detail-card">
                            <label>Direction</label>
                            <div class="value ${trade.direction === 'LONG' ? 'text-green' : 'text-red'}">${trade.direction}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="detail-card">
                            <label>Account</label>
                            <div class="value">${trade.account_name || 'Personal'} (${currency})</div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="detail-card">
                            <label>Entry Price</label>
                            <div class="value">${parseFloat(trade.entry_price).toFixed(priceDecimals)}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="detail-card">
                            <label>Exit Price</label>
                            <div class="value">${trade.exit_price ? parseFloat(trade.exit_price).toFixed(priceDecimals) : '-'}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="detail-card">
                            <label>P&L</label>
                            <div class="value ${pnlClass}">${pnl >= 0 ? '+' : ''}${pnl.toFixed(2)} ${currency}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="detail-card">
                            <label>R-Multiple</label>
                            <div class="value text-gold">${parseFloat(trade.r_multiple || 0).toFixed(2)}R</div>
                        </div>
                    </div>

                    ${screenshotsHtml}

                    <div class="col-md-6">
                        <div class="detail-card h-100">
                            <label>Notes & Strategy</label>
                            <div class="mb-2"><span class="text-gold">Strategy:</span> ${trade.strategy_name || 'N/A'}</div>
                            <div class="mb-2"><span class="text-gold">Entry Reason:</span><br>${trade.entry_reason || '-'}</div>
                            <div><span class="text-gold">Exit Reason:</span><br>${trade.exit_reason || '-'}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-card h-100">
                            <label>Lessons Learned</label>
                            <div>${trade.lessons_learned || '-'}</div>
                        </div>
                    </div>
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
                    document.getElementById('edit_strategy_id').value = trade.strategy_id || '';
                    document.getElementById('edit_entry_reason').value = trade.entry_reason || '';
                    document.getElementById('edit_exit_reason').value = trade.exit_reason || '';
                    document.getElementById('edit_lessons_learned').value = trade.lessons_learned || '';

                    // Direction
                    document.querySelector(`input[name="direction"][value="${trade.direction}"]`).checked = true;
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
    </script>
</body>
</html>
