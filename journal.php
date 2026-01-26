<?php
/**
 * Trade Journal - Full Trade List with Filters
 */

require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $pdo = getConnection();
    
    // Get instruments for filter (with tick data)
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
    
    // Get strategies for filter
    $strategiesQuery = $pdo->prepare("SELECT * FROM strategies WHERE user_id = ? ORDER BY name");
    $strategiesQuery->execute([$userId]);
    $strategies = $strategiesQuery->fetchAll();
    
    // Get tags
    $tagsQuery = $pdo->prepare("SELECT * FROM tags WHERE user_id = ? ORDER BY name");
    $tagsQuery->execute([$userId]);
    $tags = $tagsQuery->fetchAll();
    
    // Get accounts
    $accountsQuery = $pdo->prepare("SELECT * FROM accounts WHERE user_id = ? ORDER BY name");
    $accountsQuery->execute([$userId]);
    $accounts = $accountsQuery->fetchAll();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trade Journal | Trading Journal</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Instrument Tick Data for P&L Preview -->
    <script>
        const instrumentTickData = <?php echo $instrumentTickDataJson; ?>;
    </script>
</head>
<body>
    <div class="bg-animated"></div>
    <div class="grid-overlay"></div>
    
    <!-- Sidebar -->
    <aside class="sidebar-luxury" id="sidebar">
        <div class="sidebar-brand">
            <a href="dashboard.php" class="brand-logo">
                <div class="logo-icon"><i class="bi bi-graph-up-arrow"></i></div>
                Trading Journal
            </a>
        </div>
        
        <nav>
            <ul class="sidebar-nav">
                <li class="sidebar-nav-item">
                    <a href="dashboard.php" class="sidebar-nav-link">
                        <i class="bi bi-speedometer2"></i>Dashboard
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="journal.php" class="sidebar-nav-link active">
                        <i class="bi bi-journal-richtext"></i>Trade Journal
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="analytics.php" class="sidebar-nav-link">
                        <i class="bi bi-bar-chart-line"></i>Analytics
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="calendar.php" class="sidebar-nav-link">
                        <i class="bi bi-calendar3"></i>Calendar
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="strategies.php" class="sidebar-nav-link">
                        <i class="bi bi-lightbulb"></i>Strategies
                    </a>
                </li>
                <li class="mt-4 mb-2">
                    <small class="text-muted-custom text-uppercase px-3" style="font-size: 0.7rem;">Account</small>
                </li>
                <li class="sidebar-nav-item">
                    <a href="settings.php" class="sidebar-nav-link">
                        <i class="bi bi-gear"></i>Settings
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="api/auth/logout.php" class="sidebar-nav-link">
                        <i class="bi bi-box-arrow-left"></i>Logout
                    </a>
                </li>
            </ul>
        </nav>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1"><i class="bi bi-journal-richtext text-gold me-2"></i>Trade Journal</h4>
                <p class="text-muted-custom mb-0">View and manage all your trades</p>
            </div>
            <button class="btn btn-luxury" data-bs-toggle="modal" data-bs-target="#newTradeModal">
                <i class="bi bi-plus-circle me-2"></i>New Trade
            </button>
        </div>
        
        <!-- Filters -->
        <div class="glass-card mb-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label-luxury">Instrument</label>
                    <select id="filterInstrument" class="form-luxury form-select-luxury">
                        <option value="">All Instruments</option>
                        <?php foreach ($instruments as $inst): ?>
                        <option value="<?php echo $inst['id']; ?>"><?php echo $inst['code']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label-luxury">Strategy</label>
                    <select id="filterStrategy" class="form-luxury form-select-luxury">
                        <option value="">All Strategies</option>
                        <?php foreach ($strategies as $strategy): ?>
                        <option value="<?php echo $strategy['id']; ?>"><?php echo htmlspecialchars($strategy['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label-luxury">Direction</label>
                    <select id="filterDirection" class="form-luxury form-select-luxury">
                        <option value="">All</option>
                        <option value="LONG">Long</option>
                        <option value="SHORT">Short</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label-luxury">Status</label>
                    <select id="filterStatus" class="form-luxury form-select-luxury">
                        <option value="">All</option>
                        <option value="OPEN">Open</option>
                        <option value="CLOSED">Closed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label-luxury">From Date</label>
                    <input type="date" id="filterDateFrom" class="form-luxury">
                </div>
                <div class="col-md-2">
                    <label class="form-label-luxury">To Date</label>
                    <input type="date" id="filterDateTo" class="form-luxury">
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top" style="border-color: var(--border-glass) !important;">
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-luxury btn-sm" onclick="applyFilters()">
                        <i class="bi bi-funnel me-1"></i>Apply Filters
                    </button>
                    <button class="btn btn-outline-luxury btn-sm" onclick="resetFilters()">
                        <i class="bi bi-x-circle me-1"></i>Reset
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-luxury btn-sm" onclick="exportTrades()">
                        <i class="bi bi-download me-1"></i>Export CSV
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Trade Stats Summary -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="stat-card text-center py-3">
                    <div class="stat-value fs-4" id="statTotalTrades">0</div>
                    <div class="stat-label">Total Trades</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card text-center py-3">
                    <div class="stat-value fs-4 text-green" id="statWinRate">0%</div>
                    <div class="stat-label">Win Rate</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card text-center py-3">
                    <div class="stat-value fs-4" id="statTotalPnl">$0</div>
                    <div class="stat-label">Total P&L</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card text-center py-3">
                    <div class="stat-value fs-4" id="statAvgR">0R</div>
                    <div class="stat-label">Average R</div>
                </div>
            </div>
        </div>
        
        <!-- Trades Table -->
        <div class="dashboard-card">
            <div class="table-responsive">
                <table class="table-luxury" id="tradesTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Instrument</th>
                            <th>Direction</th>
                            <th>Entry</th>
                            <th>Exit</th>
                            <th>Size</th>
                            <th>P&L</th>
                            <th>R</th>
                            <th>Strategy</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="tradesBody">
                        <!-- Trades loaded via JS -->
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top" style="border-color: var(--border-glass) !important;">
                <div class="text-muted-custom">
                    Showing <span id="showingFrom">0</span>-<span id="showingTo">0</span> of <span id="totalTrades">0</span> trades
                </div>
                <nav>
                    <ul class="pagination mb-0" id="pagination">
                        <!-- Pagination generated via JS -->
                    </ul>
                </nav>
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
    
    <!-- New Trade Modal (same as dashboard) -->
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
                            <div class="col-md-6">
                                <h6 class="text-gold mb-3"><i class="bi bi-info-circle me-2"></i>Trade Details</h6>
                                
                                <div class="form-group">                                    <label class="form-label-custom">Trading Account</label>
                                    <select name="account_id" class="form-luxury form-select-luxury" required>
                                        <?php foreach ($accounts as $acc): ?>
                                        <option value="<?php echo $acc['id']; ?>"><?php echo htmlspecialchars($acc['name']) . ' (' . $acc['currency'] . ')'; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group mb-3">                                    <label class="form-label-luxury">Instrument</label>
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
                                            <div class="btn btn-luxury w-100 direction-btn active" data-direction="LONG">
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
                                            <input type="number" name="position_size" class="form-luxury" value="1" step="0.01">
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
                                    <label class="form-label-luxury">Screenshots</label>
                                    <div class="upload-zone" id="uploadZone">
                                        <i class="bi bi-cloud-arrow-up"></i>
                                        <p>Drag & drop images or click to browse</p>
                                        <small>PNG, JPG, WebP up to 5MB each</small>
                                        <input type="file" name="screenshots[]" id="screenshotInput" accept="image/*" multiple class="d-none">
                                    </div>
                                    <div class="image-preview-grid" id="imagePreview"></div>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <h6 class="text-gold mb-3"><i class="bi bi-journal-text me-2"></i>Notes</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label-luxury">Entry Reason</label>
                                            <textarea name="entry_reason" class="form-luxury" rows="2"></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label-luxury">Exit Reason</label>
                                            <textarea name="exit_reason" class="form-luxury" rows="2"></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label-luxury">Lessons Learned</label>
                                    <textarea name="lessons_learned" class="form-luxury" rows="2"></textarea>
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
            onclick="document.getElementById('sidebar').classList.toggle('show')">
        <i class="bi bi-list fs-4"></i>
    </button>

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
        let currentPage = 1;
        let currentTradeId = null;
        
        // Load trades on page load
        document.addEventListener('DOMContentLoaded', () => {
            loadTrades();
            initForm();
            initEditForm();
        });
        
        function loadTrades(page = 1) {
            currentPage = page;
            
            const params = new URLSearchParams({
                page: page,
                limit: 20
            });
            
            // Add filters
            const instrumentId = document.getElementById('filterInstrument').value;
            const strategyId = document.getElementById('filterStrategy').value;
            const direction = document.getElementById('filterDirection').value;
            const status = document.getElementById('filterStatus').value;
            const dateFrom = document.getElementById('filterDateFrom').value;
            const dateTo = document.getElementById('filterDateTo').value;
            
            if (instrumentId) params.append('instrument_id', instrumentId);
            if (strategyId) params.append('strategy_id', strategyId);
            if (direction) params.append('direction', direction);
            if (status) params.append('status', status);
            if (dateFrom) params.append('date_from', dateFrom);
            if (dateTo) params.append('date_to', dateTo);
            
            fetch(`api/trades/list.php?${params}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        renderTrades(data.trades);
                        renderPagination(data.pagination);
                        updateStats(data.trades);
                    }
                })
                .catch(err => console.error('Error loading trades:', err));
        }
        
        function renderTrades(trades) {
            const tbody = document.getElementById('tradesBody');
            
            if (trades.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="11" class="text-center py-5">
                            <i class="bi bi-journal-x display-4 text-muted-custom"></i>
                            <p class="text-muted-custom mt-3">No trades found</p>
                        </td>
                    </tr>
                `;
                return;
            }
            
            tbody.innerHTML = trades.map(trade => {
                const currency = trade.account_currency || 'USD';
                return `
                <tr>
                    <td>${formatDate(trade.entry_time)}</td>
                    <td><span class="fw-semibold">${trade.instrument_code || 'N/A'}</span></td>
                    <td><span class="${trade.direction === 'LONG' ? 'badge-long' : 'badge-short'}">${trade.direction}</span></td>
                    <td>${parseFloat(trade.entry_price).toFixed(2)}</td>
                    <td>${trade.exit_price ? parseFloat(trade.exit_price).toFixed(2) : '—'}</td>
                    <td>${trade.position_size}</td>
                    <td class="${parseFloat(trade.net_pnl) >= 0 ? 'pnl-positive' : 'pnl-negative'}">
                        ${parseFloat(trade.net_pnl) >= 0 ? '+' : ''}${parseFloat(trade.net_pnl).toFixed(2)} <small>${currency}</small>
                    </td>
                    <td class="${parseFloat(trade.r_multiple) >= 0 ? 'text-green' : 'text-red'}">
                        ${parseFloat(trade.r_multiple).toFixed(2)}R
                    </td>
                    <td>
                        ${trade.strategy_name ? `<span class="badge" style="background: ${trade.strategy_color}20; color: ${trade.strategy_color}">${trade.strategy_name}</span>` : '—'}
                    </td>
                    <td>
                        <span class="badge ${trade.status === 'OPEN' ? 'bg-warning' : 'bg-success'}">${trade.status}</span>
                    </td>
                    <td>
                        <button class="btn btn-sm text-gold" onclick="viewTrade(${trade.id})">
                            <i class="bi bi-eye"></i>
                        </button>
                    </td>
                </tr>
            `;}).join('');
        }
        
        function renderPagination(pagination) {
            const { page, pages, total, limit } = pagination;
            const from = (page - 1) * limit + 1;
            const to = Math.min(page * limit, total);
            
            document.getElementById('showingFrom').textContent = total > 0 ? from : 0;
            document.getElementById('showingTo').textContent = to;
            document.getElementById('totalTrades').textContent = total;
            
            let html = '';
            
            // Previous
            html += `<li class="page-item ${page <= 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="loadTrades(${page - 1})">&laquo;</a>
            </li>`;
            
            // Pages
            for (let i = 1; i <= pages; i++) {
                if (i === 1 || i === pages || (i >= page - 2 && i <= page + 2)) {
                    html += `<li class="page-item ${i === page ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="loadTrades(${i})">${i}</a>
                    </li>`;
                } else if (i === page - 3 || i === page + 3) {
                    html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
            }
            
            // Next
            html += `<li class="page-item ${page >= pages ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="loadTrades(${page + 1})">&raquo;</a>
            </li>`;
            
            document.getElementById('pagination').innerHTML = html;
        }
        
        function updateStats(trades) {
            const closedTrades = trades.filter(t => t.status === 'CLOSED');
            const winners = closedTrades.filter(t => parseFloat(t.net_pnl) > 0);
            
            // Group P&L by currency
            const pnlTotals = {};
            closedTrades.forEach(t => {
                const curr = t.account_currency || 'USD';
                pnlTotals[curr] = (pnlTotals[curr] || 0) + parseFloat(t.net_pnl);
            });
            
            const avgR = closedTrades.length > 0 
                ? closedTrades.reduce((sum, t) => sum + parseFloat(t.r_multiple), 0) / closedTrades.length 
                : 0;
            const winRate = closedTrades.length > 0 ? (winners.length / closedTrades.length * 100) : 0;
            
            document.getElementById('statTotalTrades').textContent = trades.length;
            document.getElementById('statWinRate').textContent = winRate.toFixed(1) + '%';
            
            // Display multi-currency P&L
            const pnlHtml = Object.entries(pnlTotals).map(([curr, total]) => {
                return `<div>${total >= 0 ? '+' : ''}${total.toFixed(2)} <small>${curr}</small></div>`;
            }).join('') || '$0.00';
            
            const totalPnlSum = closedTrades.reduce((sum, t) => sum + parseFloat(t.net_pnl), 0);
            document.getElementById('statTotalPnl').innerHTML = pnlHtml;
            document.getElementById('statTotalPnl').className = 'stat-value fs-4 ' + (totalPnlSum >= 0 ? 'text-green' : 'text-red');
            document.getElementById('statAvgR').textContent = avgR.toFixed(2) + 'R';
        }
        
        function viewTrade(id) {
            currentTradeId = id;
            
            fetch(`api/trades/get.php?id=${id}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        renderTradeDetail(data.trade);
                        new bootstrap.Modal(document.getElementById('viewTradeModal')).show();
                    }
                });
        }
        
        function renderTradeDetail(trade) {
            const pnlClass = parseFloat(trade.net_pnl) >= 0 ? 'text-green' : 'text-red';
            const rClass = parseFloat(trade.r_multiple) >= 0 ? 'text-green' : 'text-red';
            const currency = trade.account_currency || 'USD';
            
            let attachmentsHtml = '';
            if (trade.attachments && trade.attachments.length > 0) {
                attachmentsHtml = `
                    <div class="col-12 mt-3">
                        <h6 class="text-gold mb-3"><i class="bi bi-images me-2"></i>Trade Screenshots (${trade.attachments.length})</h6>
                        <div class="row g-3">
                            ${trade.attachments.map(a => {
                                // Ensure path is correct (handle potential leading slashes)
                                const imgPath = a.file_path.startsWith('/') ? a.file_path.substring(1) : a.file_path;
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
                attachmentsHtml = `
                    <div class="col-12 mt-3">
                        <div class="glass-card text-center py-4">
                            <i class="bi bi-image text-muted d-block mb-2" style="font-size: 2rem;"></i>
                            <p class="text-muted-custom mb-0">No screenshots attached to this trade.</p>
                        </div>
                    </div>
                `;
            }
            
            document.getElementById('tradeDetailContent').innerHTML = `
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="glass-card h-100">
                            <h6 class="text-gold mb-3"><i class="bi bi-info-circle me-2"></i>Trade Info</h6>
                            <div class="row g-3">
                                <div class="col-6">
                                    <small class="text-muted-custom d-block">Instrument</small>
                                    <strong>${trade.instrument_code || 'N/A'}</strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted-custom d-block">Direction</small>
                                    <span class="${trade.direction === 'LONG' ? 'badge-long' : 'badge-short'}">${trade.direction}</span>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted-custom d-block">Entry Price</small>
                                    <strong>${parseFloat(trade.entry_price).toFixed(trade.entry_price < 1 ? 5 : 2)}</strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted-custom d-block">Exit Price</small>
                                    <strong>${trade.exit_price ? parseFloat(trade.exit_price).toFixed(trade.exit_price < 1 ? 5 : 2) : '—'}</strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted-custom d-block">Stop Loss</small>
                                    <strong>${trade.stop_loss ? parseFloat(trade.stop_loss).toFixed(trade.stop_loss < 1 ? 5 : 2) : '—'}</strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted-custom d-block">Take Profit</small>
                                    <strong>${trade.take_profit ? parseFloat(trade.take_profit).toFixed(trade.take_profit < 1 ? 5 : 2) : '—'}</strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted-custom d-block">Position Size</small>
                                    <strong>${trade.position_size}</strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted-custom d-block">Fees</small>
                                    <strong>${parseFloat(trade.fees).toFixed(2)} <small>${currency}</small></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="glass-card h-100">
                            <h6 class="text-gold mb-3"><i class="bi bi-graph-up me-2"></i>Performance</h6>
                            <div class="row g-3">
                                <div class="col-6">
                                    <small class="text-muted-custom d-block">Gross P&L</small>
                                    <strong class="${pnlClass}">${parseFloat(trade.gross_pnl).toFixed(2)} <small>${currency}</small></strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted-custom d-block">Net P&L</small>
                                    <strong class="${pnlClass}" style="font-size: 1.25rem;">${parseFloat(trade.net_pnl).toFixed(2)} <small>${currency}</small></strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted-custom d-block">R-Multiple</small>
                                    <strong class="${rClass}">${parseFloat(trade.r_multiple).toFixed(2)}R</strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted-custom d-block">Status</small>
                                    <span class="badge ${trade.status === 'OPEN' ? 'bg-warning' : 'bg-success'}">${trade.status}</span>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted-custom d-block">Entry Time</small>
                                    <strong>${formatDate(trade.entry_time)}</strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted-custom d-block">Exit Time</small>
                                    <strong>${trade.exit_time ? formatDate(trade.exit_time) : '—'}</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    ${attachmentsHtml}
                    
                    <div class="col-12">
                        <div class="glass-card">
                            <h6 class="text-gold mb-3"><i class="bi bi-journal-text me-2"></i>Notes</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <small class="text-muted-custom d-block mb-1">Entry Reason</small>
                                    <p class="mb-0 text-white">${trade.entry_reason || 'No notes'}</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted-custom d-block mb-1">Exit Reason</small>
                                    <p class="mb-0 text-white">${trade.exit_reason || 'No notes'}</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted-custom d-block mb-1">Lessons Learned</small>
                                    <p class="mb-0 text-white">${trade.lessons_learned || 'No notes'}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        function editTrade() {
            if (!currentTradeId) return;
            
            fetch(`api/trades/get.php?id=${currentTradeId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const trade = data.trade;
                        document.getElementById('editTradeId').value = trade.id;
                        document.getElementById('edit_account_id').value = trade.account_id;
                        document.getElementById('edit_instrument_id').value = trade.instrument_id;
                        
                        if (trade.direction === 'LONG') {
                            document.getElementById('edit_direction_long').checked = true;
                            updateEditDirectionBtn('LONG');
                        } else {
                            document.getElementById('edit_direction_short').checked = true;
                            updateEditDirectionBtn('SHORT');
                        }
                        
                        document.getElementById('edit_entry_price').value = trade.entry_price;
                        document.getElementById('edit_exit_price').value = trade.exit_price;
                        document.getElementById('edit_stop_loss').value = trade.stop_loss;
                        document.getElementById('edit_take_profit').value = trade.take_profit;
                        document.getElementById('edit_position_size').value = trade.position_size;
                        document.getElementById('edit_fees').value = trade.fees;
                        
                        // Format dates for datetime-local
                        if (trade.entry_time) {
                            document.getElementById('edit_entry_time').value = trade.entry_time.replace(' ', 'T').substring(0, 16);
                        }
                        if (trade.exit_time) {
                            document.getElementById('edit_exit_time').value = trade.exit_time.replace(' ', 'T').substring(0, 16);
                        }
                        
                        document.getElementById('edit_strategy_id').value = trade.strategy_id || '';
                        document.getElementById('edit_entry_reason').value = trade.entry_reason || '';
                        document.getElementById('edit_exit_reason').value = trade.exit_reason || '';
                        document.getElementById('edit_lessons_learned').value = trade.lessons_learned || '';
                        
                        // Clear previous previews
                        document.getElementById('editImagePreview').innerHTML = '';
                        
                        // Close view modal and open edit modal
                        bootstrap.Modal.getInstance(document.getElementById('viewTradeModal')).hide();
                        new bootstrap.Modal(document.getElementById('editTradeModal')).show();
                    }
                });
        }

        function updateEditDirectionBtn(direction) {
            document.querySelectorAll('.edit-direction-btn').forEach(btn => {
                if (btn.dataset.direction === direction) {
                    btn.classList.add('btn-luxury', 'active');
                    btn.classList.remove('btn-outline-luxury');
                } else {
                    btn.classList.remove('btn-luxury', 'active');
                    btn.classList.add('btn-outline-luxury');
                }
            });
        }

        function initEditForm() {
            document.querySelectorAll('.edit-direction-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const direction = this.dataset.direction;
                    document.getElementById(`edit_direction_${direction.toLowerCase()}`).checked = true;
                    updateEditDirectionBtn(direction);
                });
            });

            // File upload for edit
            const uploadZone = document.getElementById('editUploadZone');
            const fileInput = document.getElementById('editScreenshotInput');
            const imagePreview = document.getElementById('editImagePreview');
            
            uploadZone.addEventListener('click', () => fileInput.click());
            uploadZone.addEventListener('dragover', e => { e.preventDefault(); uploadZone.classList.add('dragover'); });
            uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('dragover'));
            uploadZone.addEventListener('drop', e => {
                e.preventDefault();
                uploadZone.classList.remove('dragover');
                handleEditFiles(e.dataTransfer.files);
            });
            fileInput.addEventListener('change', e => handleEditFiles(e.target.files));

            document.getElementById('editTradeForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                try {
                    const response = await fetch(this.action, { method: 'POST', body: formData });
                    const data = await response.json();
                    
                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('editTradeModal')).hide();
                        loadTrades(currentPage);
                    } else {
                        alert(data.message || 'Error updating trade');
                    }
                } catch (error) {
                    alert('An error occurred');
                }
            });
        }

        function handleEditFiles(files) {
            const imagePreview = document.getElementById('editImagePreview');
            Array.from(files).forEach(file => {
                if (file.type.startsWith('image/') && file.size <= 5 * 1024 * 1024) {
                    const reader = new FileReader();
                    reader.onload = e => {
                        const div = document.createElement('div');
                        div.className = 'image-preview-item';
                        div.innerHTML = `
                            <img src="${e.target.result}" alt="Preview">
                            <button type="button" class="remove-btn" onclick="this.closest('.image-preview-item').remove()">
                                <i class="bi bi-x"></i>
                            </button>
                        `;
                        imagePreview.appendChild(div);
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
        
        function deleteTrade() {
            if (!currentTradeId) return;
            new bootstrap.Modal(document.getElementById('deleteConfirmModal')).show();
        }

        // Handle confirmed deletion
        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            if (!currentTradeId) return;
            
            fetch('api/trades/delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `trade_id=${currentTradeId}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Hide both modals
                    bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal')).hide();
                    const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewTradeModal'));
                    if (viewModal) viewModal.hide();
                    
                    loadTrades(currentPage);
                } else {
                    alert(data.message || 'Error deleting trade');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the trade.');
            });
        });
        
        function applyFilters() {
            loadTrades(1);
        }
        
        function resetFilters() {
            document.getElementById('filterInstrument').value = '';
            document.getElementById('filterStrategy').value = '';
            document.getElementById('filterDirection').value = '';
            document.getElementById('filterStatus').value = '';
            document.getElementById('filterDateFrom').value = '';
            document.getElementById('filterDateTo').value = '';
            loadTrades(1);
        }
        
        function exportTrades() {
            // Simple CSV export
            window.location.href = 'api/trades/export.php';
        }
        
        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
        }
        
        function initForm() {
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
                document.getElementById('previewEntry').textContent = entryPrice.toFixed(entryPrice < 1 ? 5 : 2);
                document.getElementById('previewExit').textContent = exitPrice.toFixed(exitPrice < 1 ? 5 : 2);
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
            
            // Direction toggle
            document.querySelectorAll('#newTradeForm .direction-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('#newTradeForm .direction-btn').forEach(b => {
                        b.classList.remove('active', 'btn-luxury');
                        b.classList.add('btn-outline-luxury');
                    });
                    this.classList.remove('btn-outline-luxury');
                    this.classList.add('btn-luxury', 'active');
                    document.querySelector(`#newTradeForm input[value="${this.dataset.direction}"]`).checked = true;
                });
            });
            
            // File upload
            const uploadZone = document.getElementById('uploadZone');
            const fileInput = document.getElementById('screenshotInput');
            const imagePreview = document.getElementById('imagePreview');
            
            uploadZone.addEventListener('click', () => fileInput.click());
            uploadZone.addEventListener('dragover', e => { e.preventDefault(); uploadZone.classList.add('dragover'); });
            uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('dragover'));
            uploadZone.addEventListener('drop', e => {
                e.preventDefault();
                uploadZone.classList.remove('dragover');
                handleFiles(e.dataTransfer.files);
            });
            fileInput.addEventListener('change', e => handleFiles(e.target.files));
            
            // Set current time
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            document.querySelector('#newTradeForm input[name="entry_time"]').value = now.toISOString().slice(0, 16);

            // Update currency labels on account change
            const accountSelect = document.querySelector('#newTradeForm select[name="account_id"]');
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
                
                try {
                    const response = await fetch(this.action, { method: 'POST', body: formData });
                    const data = await response.json();
                    
                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('newTradeModal')).hide();
                        this.reset();
                        imagePreview.innerHTML = '';
                        loadTrades(1);
                    } else {
                        alert(data.message || 'Error saving trade');
                    }
                } catch (error) {
                    alert('An error occurred');
                }
            });
        }
        
        function handleFiles(files) {
            const imagePreview = document.getElementById('imagePreview');
            Array.from(files).forEach(file => {
                if (file.type.startsWith('image/') && file.size <= 5 * 1024 * 1024) {
                    const reader = new FileReader();
                    reader.onload = e => {
                        const div = document.createElement('div');
                        div.className = 'image-preview-item';
                        div.innerHTML = `
                            <img src="${e.target.result}" alt="Preview">
                            <button type="button" class="remove-btn" onclick="this.closest('.image-preview-item').remove()">
                                <i class="bi bi-x"></i>
                            </button>
                        `;
                        imagePreview.appendChild(div);
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    </script>

    
    <style>
        .page-link {
            background: var(--bg-glass);
            border: 1px solid var(--border-glass);
            color: var(--text-secondary);
        }
        .page-link:hover {
            background: rgba(255, 215, 0, 0.1);
            border-color: var(--gold);
            color: var(--gold);
        }
        .page-item.active .page-link {
            background: var(--gradient-gold);
            border-color: var(--gold);
            color: #000;
        }
        .page-item.disabled .page-link {
            background: var(--bg-glass);
            border-color: var(--border-glass);
            color: var(--text-muted);
        }
    </style>
</body>
</html>
