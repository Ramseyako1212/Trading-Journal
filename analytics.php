<?php
/**
 * Analytics Dashboard
 */

require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $pdo = getConnection();
    
    // Overall stats
    $statsQuery = $pdo->prepare("
        SELECT 
            COUNT(*) as total_trades,
            SUM(CASE WHEN net_pnl > 0 THEN 1 ELSE 0 END) as winning_trades,
            SUM(CASE WHEN net_pnl < 0 THEN 1 ELSE 0 END) as losing_trades,
            SUM(CASE WHEN net_pnl = 0 THEN 1 ELSE 0 END) as breakeven_trades,
            COALESCE(SUM(net_pnl), 0) as total_pnl,
            COALESCE(SUM(CASE WHEN net_pnl > 0 THEN net_pnl ELSE 0 END), 0) as total_profit,
            COALESCE(SUM(CASE WHEN net_pnl < 0 THEN net_pnl ELSE 0 END), 0) as total_loss,
            COALESCE(AVG(net_pnl), 0) as avg_pnl,
            COALESCE(AVG(CASE WHEN net_pnl > 0 THEN net_pnl END), 0) as avg_win,
            COALESCE(AVG(CASE WHEN net_pnl < 0 THEN net_pnl END), 0) as avg_loss,
            COALESCE(AVG(r_multiple), 0) as avg_r,
            COALESCE(MAX(net_pnl), 0) as largest_win,
            COALESCE(MIN(net_pnl), 0) as largest_loss,
            COALESCE(SUM(fees), 0) as total_fees
        FROM trades 
        WHERE user_id = ? AND status = 'CLOSED'
    ");
    $statsQuery->execute([$userId]);
    $stats = $statsQuery->fetch();
    
    // Calculate derived metrics
    $winRate = $stats['total_trades'] > 0 
        ? ($stats['winning_trades'] / $stats['total_trades']) * 100 
        : 0;
    
    $profitFactor = abs($stats['total_loss']) > 0 
        ? abs($stats['total_profit'] / $stats['total_loss']) 
        : ($stats['total_profit'] > 0 ? $stats['total_profit'] : 0);
    
    $expectancy = $stats['total_trades'] > 0
        ? ($winRate/100 * abs($stats['avg_win'])) - ((100-$winRate)/100 * abs($stats['avg_loss']))
        : 0;
    
    // Daily P&L for equity curve (last 30 days)
    $dailyPnlQuery = $pdo->prepare("
        SELECT DATE(entry_time) as trade_date, SUM(net_pnl) as daily_pnl
        FROM trades
        WHERE user_id = ? AND status = 'CLOSED' AND entry_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(entry_time)
        ORDER BY trade_date ASC
    ");
    $dailyPnlQuery->execute([$userId]);
    $dailyPnl = $dailyPnlQuery->fetchAll();
    
    // Calculate cumulative P&L for equity curve
    $equityData = [];
    $cumulative = 0;
    foreach ($dailyPnl as $day) {
        $cumulative += $day['daily_pnl'];
        $equityData[] = [
            'date' => $day['trade_date'],
            'pnl' => $day['daily_pnl'],
            'cumulative' => $cumulative
        ];
    }
    
    // P&L by day of week
    $dayOfWeekQuery = $pdo->prepare("
        SELECT DAYNAME(entry_time) as day_name, 
               DAYOFWEEK(entry_time) as day_num,
               SUM(net_pnl) as total_pnl,
               COUNT(*) as trade_count
        FROM trades
        WHERE user_id = ? AND status = 'CLOSED'
        GROUP BY DAYNAME(entry_time), DAYOFWEEK(entry_time)
        ORDER BY day_num
    ");
    $dayOfWeekQuery->execute([$userId]);
    $dayOfWeekData = $dayOfWeekQuery->fetchAll();
    
    // P&L by hour
    $hourlyQuery = $pdo->prepare("
        SELECT HOUR(entry_time) as hour, 
               SUM(net_pnl) as total_pnl,
               COUNT(*) as trade_count
        FROM trades
        WHERE user_id = ? AND status = 'CLOSED'
        GROUP BY HOUR(entry_time)
        ORDER BY hour
    ");
    $hourlyQuery->execute([$userId]);
    $hourlyData = $hourlyQuery->fetchAll();
    
    // Performance by instrument
    $instrumentQuery = $pdo->prepare("
        SELECT COALESCE(i.code, 'Unknown') as code, COALESCE(i.name, 'Unknown') as name,
               COUNT(*) as trade_count,
               SUM(t.net_pnl) as total_pnl,
               AVG(t.r_multiple) as avg_r,
               SUM(CASE WHEN t.net_pnl > 0 THEN 1 ELSE 0 END) as wins
        FROM trades t
        LEFT JOIN instruments i ON t.instrument_id = i.id
        WHERE t.user_id = ? AND t.status = 'CLOSED'
        GROUP BY t.instrument_id
        ORDER BY total_pnl DESC
    ");
    $instrumentQuery->execute([$userId]);
    $instrumentData = $instrumentQuery->fetchAll();
    
    // Performance by strategy
    $strategyQuery = $pdo->prepare("
        SELECT COALESCE(s.name, 'No Strategy') as name, COALESCE(s.color, '#6c757d') as color,
               COUNT(*) as trade_count,
               SUM(t.net_pnl) as total_pnl,
               AVG(t.r_multiple) as avg_r,
               SUM(CASE WHEN t.net_pnl > 0 THEN 1 ELSE 0 END) as wins
        FROM trades t
        LEFT JOIN strategies s ON t.strategy_id = s.id
        WHERE t.user_id = ? AND t.status = 'CLOSED'
        GROUP BY t.strategy_id
        ORDER BY total_pnl DESC
    ");
    $strategyQuery->execute([$userId]);
    $strategyData = $strategyQuery->fetchAll();
    
    // Win/Loss streaks
    $streakQuery = $pdo->prepare("
        SELECT net_pnl > 0 as is_win, entry_time
        FROM trades
        WHERE user_id = ? AND status = 'CLOSED'
        ORDER BY entry_time DESC
    ");
    $streakQuery->execute([$userId]);
    $tradeResults = $streakQuery->fetchAll();
    
    $currentStreak = 0;
    $currentStreakType = null;
    $maxWinStreak = 0;
    $maxLossStreak = 0;
    $winStreak = 0;
    $lossStreak = 0;
    
    foreach ($tradeResults as $trade) {
        if ($trade['is_win']) {
            if ($currentStreakType !== 'win') {
                $lossStreak = 0;
            }
            $winStreak++;
            $currentStreakType = 'win';
            $maxWinStreak = max($maxWinStreak, $winStreak);
        } else {
            if ($currentStreakType !== 'loss') {
                $winStreak = 0;
            }
            $lossStreak++;
            $currentStreakType = 'loss';
            $maxLossStreak = max($maxLossStreak, $lossStreak);
        }
    }
    
    // Calculate current streak
    $currentStreak = $currentStreakType === 'win' ? $winStreak : -$lossStreak;
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics | Trading Journal</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <a href="journal.php" class="sidebar-nav-link">
                        <i class="bi bi-journal-richtext"></i>Trade Journal
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="analytics.php" class="sidebar-nav-link active">
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
                <h4 class="mb-1"><i class="bi bi-bar-chart-line text-gold me-2"></i>Analytics</h4>
                <p class="text-muted-custom mb-0">Deep insights into your trading performance</p>
            </div>
            <div class="d-flex gap-2">
                <select class="form-luxury form-select-luxury" id="timeRange" onchange="updateTimeRange()">
                    <option value="30">Last 30 Days</option>
                    <option value="90">Last 90 Days</option>
                    <option value="365">Last Year</option>
                    <option value="all">All Time</option>
                </select>
            </div>
        </div>
        
        <!-- Key Metrics -->
        <div class="row g-4 mb-4">
            <div class="col-6 col-lg-2">
                <div class="metric-card">
                    <div class="metric-value text-gold"><?php echo $stats['total_trades']; ?></div>
                    <div class="metric-label">Total Trades</div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="metric-card cyan">
                    <div class="metric-value"><?php echo number_format($winRate, 1); ?>%</div>
                    <div class="metric-label">Win Rate</div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="metric-card <?php echo $stats['total_pnl'] >= 0 ? 'green' : 'red'; ?>">
                    <div class="metric-value <?php echo $stats['total_pnl'] >= 0 ? 'text-green' : 'text-red'; ?>">
                        <?php echo number_format($stats['total_pnl'], 2); ?>
                    </div>
                    <div class="metric-label">Total P&L</div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="metric-card">
                    <div class="metric-value"><?php echo number_format($profitFactor, 2); ?></div>
                    <div class="metric-label">Profit Factor</div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="metric-card">
                    <div class="metric-value"><?php echo number_format($stats['avg_r'], 2); ?>R</div>
                    <div class="metric-label">Avg R-Multiple</div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="metric-card">
                    <div class="metric-value <?php echo $expectancy >= 0 ? 'text-green' : 'text-red'; ?>">
                        $<?php echo number_format($expectancy, 0); ?>
                    </div>
                    <div class="metric-label">Expectancy</div>
                </div>
            </div>
        </div>
        
        <!-- Charts Row 1 -->
        <div class="row g-4 mb-4">
            <!-- Equity Curve -->
            <div class="col-lg-8">
                <div class="dashboard-card h-100">
                    <div class="card-header-luxury">
                        <h5 class="card-title-luxury">
                            <i class="bi bi-graph-up"></i>
                            Equity Curve
                        </h5>
                    </div>
                    <div class="chart-container" style="height: 300px;">
                        <canvas id="equityChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Win/Loss Distribution -->
            <div class="col-lg-4">
                <div class="dashboard-card h-100">
                    <div class="card-header-luxury">
                        <h5 class="card-title-luxury">
                            <i class="bi bi-pie-chart"></i>
                            Win/Loss Distribution
                        </h5>
                    </div>
                    <div class="chart-container d-flex align-items-center justify-content-center" style="height: 300px;">
                        <canvas id="winLossChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts Row 2 -->
        <div class="row g-4 mb-4">
            <!-- P&L by Day of Week -->
            <div class="col-lg-6">
                <div class="dashboard-card h-100">
                    <div class="card-header-luxury">
                        <h5 class="card-title-luxury">
                            <i class="bi bi-calendar-week"></i>
                            P&L by Day of Week
                        </h5>
                    </div>
                    <div class="chart-container" style="height: 250px;">
                        <canvas id="dayOfWeekChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- P&L by Hour -->
            <div class="col-lg-6">
                <div class="dashboard-card h-100">
                    <div class="card-header-luxury">
                        <h5 class="card-title-luxury">
                            <i class="bi bi-clock"></i>
                            P&L by Trading Hour
                        </h5>
                    </div>
                    <div class="chart-container" style="height: 250px;">
                        <canvas id="hourlyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Detailed Stats -->
        <div class="row g-4 mb-4">
            <!-- Left Stats -->
            <div class="col-lg-4">
                <div class="dashboard-card h-100">
                    <div class="card-header-luxury">
                        <h5 class="card-title-luxury">
                            <i class="bi bi-calculator"></i>
                            Detailed Metrics
                        </h5>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="text-muted-custom small">Avg Win</div>
                            <div class="text-green fw-bold">$<?php echo number_format($stats['avg_win'], 2); ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted-custom small">Avg Loss</div>
                            <div class="text-red fw-bold">$<?php echo number_format($stats['avg_loss'], 2); ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted-custom small">Largest Win</div>
                            <div class="text-green fw-bold">$<?php echo number_format($stats['largest_win'], 2); ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted-custom small">Largest Loss</div>
                            <div class="text-red fw-bold">$<?php echo number_format($stats['largest_loss'], 2); ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted-custom small">Total Fees</div>
                            <div class="fw-bold">$<?php echo number_format($stats['total_fees'], 2); ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted-custom small">Breakeven Trades</div>
                            <div class="fw-bold"><?php echo $stats['breakeven_trades']; ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted-custom small">Max Win Streak</div>
                            <div class="text-green fw-bold"><?php echo $maxWinStreak; ?> trades</div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted-custom small">Max Loss Streak</div>
                            <div class="text-red fw-bold"><?php echo $maxLossStreak; ?> trades</div>
                        </div>
                        <div class="col-12 mt-3 pt-3 border-top" style="border-color: var(--border-glass) !important;">
                            <div class="text-muted-custom small">Current Streak</div>
                            <div class="fs-4 fw-bold <?php echo $currentStreak >= 0 ? 'text-green' : 'text-red'; ?>">
                                <?php echo abs($currentStreak); ?> <?php echo $currentStreak >= 0 ? 'Wins' : 'Losses'; ?>
                                <i class="bi bi-<?php echo $currentStreak >= 0 ? 'trophy' : 'emoji-frown'; ?> ms-2"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Performance by Instrument -->
            <div class="col-lg-4">
                <div class="dashboard-card h-100">
                    <div class="card-header-luxury">
                        <h5 class="card-title-luxury">
                            <i class="bi bi-droplet-half"></i>
                            By Instrument
                        </h5>
                    </div>
                    <?php if (empty($instrumentData)): ?>
                    <div class="text-center py-4 text-muted-custom">
                        <i class="bi bi-graph-down display-4"></i>
                        <p class="mt-2">No data yet</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table-luxury table-sm mb-0">
                            <thead>
                                <tr class="text-muted-custom small">
                                    <th class="border-0">Instrument</th>
                                    <th class="border-0 text-end">Trades</th>
                                    <th class="border-0 text-end">Win %</th>
                                    <th class="border-0 text-end">P&L</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($instrumentData as $inst): 
                                    $instWinRate = $inst['trade_count'] > 0 ? ($inst['wins'] / $inst['trade_count']) * 100 : 0;
                                    $displayName = !empty($inst['code']) ? $inst['code'] : $inst['name'];
                                ?>
                                <tr>
                                    <td class="border-0 text-white fw-semibold"><?php echo htmlspecialchars($displayName); ?></td>
                                    <td class="border-0 text-end text-secondary"><?php echo $inst['trade_count']; ?></td>
                                    <td class="border-0 text-end"><?php echo number_format($instWinRate, 0); ?>%</td>
                                    <td class="border-0 text-end <?php echo $inst['total_pnl'] >= 0 ? 'text-green' : 'text-red'; ?>">
                                        $<?php echo number_format($inst['total_pnl'], 0); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Performance by Strategy -->
            <div class="col-lg-4">
                <div class="dashboard-card h-100">
                    <div class="card-header-luxury">
                        <h5 class="card-title-luxury">
                            <i class="bi bi-lightbulb"></i>
                            By Strategy
                        </h5>
                    </div>
                    <?php if (empty($strategyData)): ?>
                    <div class="text-center py-4 text-muted-custom">
                        <i class="bi bi-graph-down display-4"></i>
                        <p class="mt-2">No data yet</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table-luxury table-sm mb-0">
                            <thead>
                                <tr class="text-muted-custom small">
                                    <th class="border-0">Strategy</th>
                                    <th class="border-0 text-end">Trades</th>
                                    <th class="border-0 text-end">Avg R</th>
                                    <th class="border-0 text-end">P&L</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($strategyData as $strat): ?>
                                <tr>
                                    <td class="border-0">
                                        <span class="d-inline-block rounded-circle me-2" 
                                              style="width: 10px; height: 10px; background: <?php echo $strat['color']; ?>;"></span>
                                        <span class="text-white"><?php echo htmlspecialchars($strat['name']); ?></span>
                                    </td>
                                    <td class="border-0 text-end text-secondary"><?php echo $strat['trade_count']; ?></td>
                                    <td class="border-0 text-end <?php echo $strat['avg_r'] >= 0 ? 'text-green' : 'text-red'; ?>">
                                        <?php echo number_format($strat['avg_r'], 2); ?>R
                                    </td>
                                    <td class="border-0 text-end <?php echo $strat['total_pnl'] >= 0 ? 'text-green' : 'text-red'; ?>">
                                        $<?php echo number_format($strat['total_pnl'], 0); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Mobile Menu Toggle -->
    <button class="btn btn-luxury d-lg-none position-fixed" 
            style="bottom: 2rem; right: 2rem; z-index: 1001; width: 56px; height: 56px; border-radius: 50%; padding: 0;"
            onclick="document.getElementById('sidebar').classList.toggle('show')">
        <i class="bi bi-list fs-4"></i>
    </button>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Chart.js defaults for dark theme
        Chart.defaults.color = 'rgba(255, 255, 255, 0.7)';
        Chart.defaults.borderColor = 'rgba(255, 255, 255, 0.1)';
        
        // Equity Curve Chart
        const equityData = <?php echo json_encode($equityData); ?>;
        const equityCtx = document.getElementById('equityChart').getContext('2d');
        
        new Chart(equityCtx, {
            type: 'line',
            data: {
                labels: equityData.map(d => d.date),
                datasets: [{
                    label: 'Cumulative P&L',
                    data: equityData.map(d => d.cumulative),
                    borderColor: '#FFD700',
                    backgroundColor: 'rgba(255, 215, 0, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointBackgroundColor: '#FFD700',
                    pointBorderColor: '#FFD700'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { maxTicksLimit: 7 }
                    },
                    y: {
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: {
                            callback: value => '$' + value.toLocaleString()
                        }
                    }
                }
            }
        });
        
        // Win/Loss Pie Chart
        const winLossCtx = document.getElementById('winLossChart').getContext('2d');
        new Chart(winLossCtx, {
            type: 'doughnut',
            data: {
                labels: ['Wins', 'Losses', 'Breakeven'],
                datasets: [{
                    data: [
                        <?php echo $stats['winning_trades']; ?>,
                        <?php echo $stats['losing_trades']; ?>,
                        <?php echo $stats['breakeven_trades']; ?>
                    ],
                    backgroundColor: ['#10B981', '#EF4444', '#6B7280'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 20 }
                    }
                }
            }
        });
        
        // Day of Week Chart
        const dayOfWeekData = <?php echo json_encode($dayOfWeekData); ?>;
        const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        const dayCtx = document.getElementById('dayOfWeekChart').getContext('2d');
        
        new Chart(dayCtx, {
            type: 'bar',
            data: {
                labels: dayOfWeekData.map(d => d.day_name.substring(0, 3)),
                datasets: [{
                    label: 'P&L',
                    data: dayOfWeekData.map(d => d.total_pnl),
                    backgroundColor: dayOfWeekData.map(d => d.total_pnl >= 0 ? '#10B981' : '#EF4444'),
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: { callback: value => '$' + value }
                    }
                }
            }
        });
        
        // Hourly Chart
        const hourlyData = <?php echo json_encode($hourlyData); ?>;
        const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
        
        // Fill in missing hours
        const allHours = Array.from({length: 24}, (_, i) => i);
        const hourlyMap = new Map(hourlyData.map(d => [d.hour, d.total_pnl]));
        const filledHourlyData = allHours.map(h => hourlyMap.get(h) || 0);
        
        new Chart(hourlyCtx, {
            type: 'bar',
            data: {
                labels: allHours.map(h => h + ':00'),
                datasets: [{
                    label: 'P&L',
                    data: filledHourlyData,
                    backgroundColor: filledHourlyData.map(v => v >= 0 ? 'rgba(0, 212, 255, 0.8)' : 'rgba(239, 68, 68, 0.8)'),
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { maxTicksLimit: 12 }
                    },
                    y: {
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: { callback: value => '$' + value }
                    }
                }
            }
        });
    </script>
</body>
</html>
