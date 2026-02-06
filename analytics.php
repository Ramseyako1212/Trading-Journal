<?php
/**
 * Analytics Dashboard - Final Intelligence Suite
 */

require_once 'config/database.php';

// Check session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Trader';

// Range filter logic
$range = $_GET['range'] ?? 'last_30';
$dateCondition = "AND entry_time <= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$rangeTitle = "Last 30 Days";

switch ($range) {
    case 'today':
        $dateCondition = "AND DATE(entry_time) = CURDATE()";
        $rangeTitle = "Today";
        break;
    case 'this_week':
        $dateCondition = "AND YEARWEEK(entry_time, 1) = YEARWEEK(CURDATE(), 1)";
        $rangeTitle = "This Week";
        break;
    case 'this_month':
        $dateCondition = "AND MONTH(entry_time) = MONTH(CURDATE()) AND YEAR(entry_time) = YEAR(CURDATE())";
        $rangeTitle = "This Month";
        break;
    case 'all':
        $dateCondition = "";
        $rangeTitle = "All Time";
        break;
}

try {
    $pdo = getConnection();
    
    // 1. Core Metrics
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
            COALESCE(SUM(fees), 0) as total_fees,
            SUM(CASE WHEN followed_rules = 0 THEN 1 ELSE 0 END) as rule_violations
        FROM trades 
        WHERE user_id = ? AND status = 'CLOSED' $dateCondition
    ");
    $statsQuery->execute([$userId]);
    $stats = $statsQuery->fetch();
    
    // Derived Calculations
    $winRate = $stats['total_trades'] > 0 ? ($stats['winning_trades'] / $stats['total_trades']) * 100 : 0;
    $profitFactor = abs($stats['total_loss']) > 0 ? abs($stats['total_profit'] / $stats['total_loss']) : ($stats['total_profit'] > 0 ? 100 : 0);
    $expectancy = $stats['total_trades'] > 0 ? ($winRate/100 * abs($stats['avg_win'])) - ((100-$winRate)/100 * abs($stats['avg_loss'])) : 0;

    // 2. Equity Curve & Max Drawdown Calculation
    $equityQuery = $pdo->prepare("
        SELECT DATE(entry_time) as date, SUM(net_pnl) as daily_pnl
        FROM trades
        WHERE user_id = ? AND status = 'CLOSED' $dateCondition
        GROUP BY DATE(entry_time)
        ORDER BY date ASC
    ");
    $equityQuery->execute([$userId]);
    $dailyEquity = $equityQuery->fetchAll();
    
    $cumulativePnl = 0;
    $equityCurve = [];
    $peak = 0;
    $maxDrawdown = 0;
    
    foreach ($dailyEquity as $row) {
        $cumulativePnl += (float)$row['daily_pnl'];
        $equityCurve[] = ['date' => $row['date'], 'value' => $cumulativePnl];
        
        if ($cumulativePnl > $peak) $peak = $cumulativePnl;
        $drawdown = $peak - $cumulativePnl;
        if ($drawdown > $maxDrawdown) $maxDrawdown = $drawdown;
    }

    // 3. Behavioral Risk Logic
    // Detect "Revenge Trading" - Multiple losses within 1 hour followed by a trade
    $revengeDateCondition = str_replace('entry_time', 't1.entry_time', $dateCondition);
    $revengeQuery = $pdo->prepare("
        SELECT t1.id 
        FROM trades t1
        JOIN trades t2 ON t1.user_id = t2.user_id 
        WHERE t1.user_id = ? 
        AND t1.status = 'CLOSED' AND t2.status = 'CLOSED'
        AND t1.net_pnl < 0 AND t2.net_pnl < 0
        AND t1.id != t2.id
        AND ABS(TIMESTAMPDIFF(MINUTE, t1.exit_time, t2.entry_time)) < 60
        $revengeDateCondition
        LIMIT 5
    ");
    $revengeQuery->execute([$userId]);
    $hasRevengeSigns = $revengeQuery->rowCount() > 0;

    // 4. Data for Charts
    $dayOfWeekQuery = $pdo->prepare("
        SELECT 
            DAYNAME(entry_time) as day_name, 
            SUM(net_pnl) as pnl
        FROM trades 
        WHERE user_id = ? AND status = 'CLOSED' $dateCondition
        GROUP BY DAYOFWEEK(entry_time), DAYNAME(entry_time)
        ORDER BY DAYOFWEEK(entry_time)
    ");
    $dayOfWeekQuery->execute([$userId]);
    $dayData = $dayOfWeekQuery->fetchAll();

    $sessionQuery = $pdo->prepare("
        SELECT 
            CASE 
                WHEN HOUR(entry_time) BETWEEN 8 AND 11 THEN 'Morning'
                WHEN HOUR(entry_time) BETWEEN 12 AND 15 THEN 'Afternoon'
                WHEN HOUR(entry_time) BETWEEN 16 AND 20 THEN 'Evening'
                ELSE 'Late Night'
            END as session,
            SUM(net_pnl) as pnl
        FROM trades WHERE user_id = ? AND status = 'CLOSED' $dateCondition
        GROUP BY 
            CASE 
                WHEN HOUR(entry_time) BETWEEN 8 AND 11 THEN 'Morning'
                WHEN HOUR(entry_time) BETWEEN 12 AND 15 THEN 'Afternoon'
                WHEN HOUR(entry_time) BETWEEN 16 AND 20 THEN 'Evening'
                ELSE 'Late Night'
            END
    ");
    $sessionQuery->execute([$userId]);
    $sessionData = $sessionQuery->fetchAll();

    // 5. Instrument Performance
    $instrumentQuery = $pdo->prepare("
        SELECT i.code, SUM(t.net_pnl) as pnl, COUNT(*) as trades
        FROM trades t
        JOIN instruments i ON t.instrument_id = i.id
        WHERE t.user_id = ? AND t.status = 'CLOSED' $dateCondition
        GROUP BY i.code ORDER BY pnl DESC
    ");
    $instrumentQuery->execute([$userId]);
    $instrumentData = $instrumentQuery->fetchAll();

    // 6. Hourly Performance
    $hourlyQuery = $pdo->prepare("
        SELECT HOUR(entry_time) as hr, SUM(net_pnl) as pnl
        FROM trades 
        WHERE user_id = ? AND status = 'CLOSED' $dateCondition
        GROUP BY HOUR(entry_time) ORDER BY hr ASC
    ");
    $hourlyQuery->execute([$userId]);
    $hourlyDataRaw = $hourlyQuery->fetchAll();
    
    // Fill in missing hours for smooth chart
    $hourlyData = [];
    for($i=0; $i<24; $i++) {
        $found = false;
        foreach($hourlyDataRaw as $h) {
            if((int)$h['hr'] === $i) {
                $hourlyData[] = ['hr' => $i, 'pnl' => (float)$h['pnl']];
                $found = true;
                break;
            }
        }
        if(!$found) $hourlyData[] = ['hr' => $i, 'pnl' => 0];
    }

    // 7. Best Performance Summary
    $bestInstrument = !empty($instrumentData) ? $instrumentData[0] : null;

    $bestDayQuery = $pdo->prepare("
        SELECT DAYNAME(entry_time) as day_name, SUM(net_pnl) as pnl
        FROM trades WHERE user_id = ? AND status = 'CLOSED' $dateCondition
        GROUP BY DAYNAME(entry_time) ORDER BY pnl DESC LIMIT 1
    ");
    $bestDayQuery->execute([$userId]);
    $bestDay = $bestDayQuery->fetch();

    $bestHour = null;
    $sortedHourly = $hourlyData;
    usort($sortedHourly, fn($a, $b) => $b['pnl'] <=> $a['pnl']);
    if(!empty($sortedHourly) && $sortedHourly[0]['pnl'] > 0) {
        $bestHour = $sortedHourly[0];
    }

} catch (PDOException $e) {
    die("Database access error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Intelligence Analytics | Trading Journal</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-dark text-white">
    <div class="bg-animated"></div>
    <div class="grid-overlay"></div>
    
    <?php include 'includes/checklist_modal.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Header Section -->
        <header class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2 opacity-50 small text-uppercase letter-spacing-1">
                        <li class="breadcrumb-item"><a href="dashboard.php" class="text-white text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item active text-gold">Analytics</li>
                    </ol>
                </nav>
                <h2 class="fw-800 mb-0"><i class="bi bi-cpu text-gold me-2"></i>Market <span class="text-gold">Intelligence</span></h2>
            </div>

            <div class="glass-btn-group p-1 rounded-3">
                <div class="btn-group" role="group">
                    <a href="?range=today" class="btn btn-sm <?php echo $range == 'today' ? 'btn-luxury' : 'btn-outline-luxury'; ?>">Today</a>
                    <a href="?range=this_week" class="btn btn-sm <?php echo $range == 'this_week' ? 'btn-luxury' : 'btn-outline-luxury'; ?>">Week</a>
                    <a href="?range=this_month" class="btn btn-sm <?php echo $range == 'this_month' ? 'btn-luxury' : 'btn-outline-luxury'; ?>">Month</a>
                    <a href="?range=last_30" class="btn btn-sm <?php echo ($range == 'last_30' || !$range) ? 'btn-luxury' : 'btn-outline-luxury'; ?>">Last 30d</a>
                    <a href="?range=all" class="btn btn-sm <?php echo $range == 'all' ? 'btn-luxury' : 'btn-outline-luxury'; ?>">All Time</a>
                </div>
            </div>
        </header>

        <!-- Intelligence Insights -->
        <section class="mb-4">
            <div class="glass-card border-gold-glow">
                <div class="intelligence-header p-3 d-flex align-items-center gap-3 border-bottom border-glass">
                    <div class="ai-pulse-ring">
                        <i class="bi bi-robot text-gold"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-700">Performance Intelligence</h5>
                        <span class="text-muted-custom smaller">Advanced pattern & behavioral analysis</span>
                    </div>
                </div>
                <div class="p-4">
                    <div class="row g-4">
                        <div class="col-md-4">
                            <div class="intelligence-item">
                                <span class="text-uppercase smaller letter-spacing-1 opacity-50 d-block mb-1">Discipline Score</span>
                                <?php 
                                    $discipline = $stats['total_trades'] > 0 ? round((($stats['total_trades'] - $stats['rule_violations']) / $stats['total_trades']) * 100) : 100;
                                ?>
                                <div class="d-flex justify-content-between align-items-end">
                                    <h3 class="mb-0 <?php echo $discipline >= 90 ? 'text-green' : 'text-gold'; ?>"><?php echo $discipline; ?>%</h3>
                                    <span class="smaller text-muted-custom"><?php echo $stats['rule_violations']; ?> Violations</span>
                                </div>
                                <div class="progress mt-2" style="height: 4px; background: rgba(255,255,255,0.05);">
                                    <div class="progress-bar <?php echo $discipline >= 90 ? 'bg-green' : 'bg-gold'; ?>" style="width: <?php echo $discipline; ?>%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 border-start border-glass">
                            <div class="intelligence-item px-md-3">
                                <span class="text-uppercase smaller letter-spacing-1 opacity-50 d-block mb-1">Behavioral Risk</span>
                                <?php if($hasRevengeSigns): ?>
                                    <div class="badge bg-danger-subtle text-red border border-red mb-1 p-2 w-100 text-start">
                                        <i class="bi bi-fire me-2"></i>REVENGE TRADING DETECTED
                                    </div>
                                    <h5 class="mb-0 text-red">High Emotional Impact</h5>
                                <?php else: ?>
                                    <div class="badge bg-success-subtle text-green border border-green mb-1 p-2 w-100 text-start">
                                        <i class="bi bi-shield-check me-2"></i>CALM EXECUTION
                                    </div>
                                    <h5 class="mb-0 text-green">Stable Discipline</h5>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4 border-start border-glass">
                            <div class="intelligence-item px-md-3">
                                <span class="text-uppercase smaller letter-spacing-1 opacity-50 d-block mb-1">Exposure Analytics</span>
                                <div class="d-flex justify-content-between">
                                    <h3 class="mb-0 text-white font-monospace">$<?php echo number_format($maxDrawdown, 2); ?></h3>
                                    <i class="bi bi-graph-down text-red fs-4"></i>
                                </div>
                                <span class="text-muted-custom smaller">Max Drawdown in Period</span>
                            </div>
                        </div>
                    </div>

                    <!-- Optimal Windows Row (NEW) -->
                    <div class="row g-4 mt-2 pt-3 border-top border-glass">
                        <div class="col-md-4">
                            <div class="intelligence-item p-2">
                                <span class="text-muted-custom smaller d-block mb-1 text-uppercase letter-spacing-1">Alpha Instrument</span>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="badge bg-luxury text-gold p-2">
                                        <i class="bi bi-star-fill"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 text-white"><?php echo $bestInstrument['code'] ?? 'N/A'; ?></h6>
                                        <small class="<?php echo ($bestInstrument['pnl'] ?? 0) >= 0 ? 'text-green' : 'text-red'; ?>">
                                            <?php echo ($bestInstrument['pnl'] ?? 0) >= 0 ? '+' : ''; ?>$<?php echo number_format($bestInstrument['pnl'] ?? 0, 2); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 border-start border-glass">
                            <div class="intelligence-item p-2">
                                <span class="text-muted-custom smaller d-block mb-1 text-uppercase letter-spacing-1">Optimal Day</span>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="badge bg-glass text-cyan p-2">
                                        <i class="bi bi-calendar-check"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 text-white"><?php echo $bestDay['day_name'] ?? 'N/A'; ?></h6>
                                        <small class="text-green">Peak Win Day</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 border-start border-glass">
                            <div class="intelligence-item p-2">
                                <span class="text-muted-custom smaller d-block mb-1 text-uppercase letter-spacing-1">Power Hour</span>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="badge bg-glass text-gold p-2">
                                        <i class="bi bi-alarm"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 text-white"><?php echo isset($bestHour) ? sprintf("%02d:00", $bestHour['hr']) : 'N/A'; ?></h6>
                                        <small class="text-gold">Highest Edge</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- KPI Metrics Grid -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="glass-card p-4 text-center h-100">
                    <span class="text-muted-custom smaller text-uppercase letter-spacing-1 mb-2 d-inline-block">Expectancy</span>
                    <h2 class="fw-800 <?php echo $expectancy >= 0 ? 'text-green' : 'text-red'; ?> font-monospace">
                        $<?php echo number_format($expectancy, 2); ?>
                    </h2>
                    <p class="smaller text-muted-custom mb-0 mt-2">Value gain per executed trade</p>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="glass-card p-4 text-center h-100">
                    <span class="text-muted-custom smaller text-uppercase letter-spacing-1 mb-2 d-inline-block">Profit Factor</span>
                    <h2 class="fw-800 text-gold font-monospace"><?php echo number_format($profitFactor, 2); ?></h2>
                    <div class="progress mt-3 mx-auto" style="height: 4px; width: 60%; background: rgba(255,255,255,0.05);">
                        <div class="progress-bar bg-gold" style="width: <?php echo min(100, $profitFactor * 30); ?>%"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="glass-card p-4 text-center h-100">
                    <span class="text-muted-custom smaller text-uppercase letter-spacing-1 mb-2 d-inline-block">Win Rate</span>
                    <h2 class="fw-800 <?php echo $winRate >= 50 ? 'text-cyan' : 'text-white'; ?> font-monospace"><?php echo round($winRate); ?>%</h2>
                    <div class="d-flex justify-content-center gap-3 mt-2 smaller">
                        <span class="text-green"><?php echo $stats['winning_trades']; ?>W</span>
                        <span class="text-red"><?php echo $stats['losing_trades']; ?>L</span>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="glass-card p-4 text-center h-100">
                    <span class="text-muted-custom smaller text-uppercase letter-spacing-1 mb-2 d-inline-block">Avg R-Multiple</span>
                    <h2 class="fw-800 text-white font-monospace"><?php echo number_format($stats['avg_r'], 2); ?>R</h2>
                    <p class="smaller text-muted-custom mb-0 mt-2">Focus on quality over quantity</p>
                </div>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="glass-card p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h6 class="text-uppercase letter-spacing-1 smaller text-gold mb-0">Equity Statistics Evolution</h6>
                        <span class="badge bg-glass text-gold font-monospace"><?php echo $rangeTitle; ?></span>
                    </div>
                    <div style="height: 350px;">
                        <canvas id="equityCurve"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="glass-card p-4 mb-4">
                    <h6 class="text-uppercase letter-spacing-1 smaller text-gold mb-4">Daily Performance</h6>
                    <div style="height: 200px;">
                        <canvas id="dailyBars"></canvas>
                    </div>
                </div>
                <div class="glass-card p-4">
                    <h6 class="text-uppercase letter-spacing-1 smaller text-gold mb-4">Session Analysis</h6>
                    <div style="height: 180px;">
                        <canvas id="sessionDoughnut"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- New Charts Row (Instrument & Hourly) -->
        <div class="row g-4 mt-4">
            <div class="col-lg-6">
                <div class="glass-card p-4">
                    <h6 class="text-uppercase letter-spacing-1 smaller text-gold mb-4">Instrument Breakdown (P&L)</h6>
                    <div style="height: 250px;">
                        <canvas id="instrumentChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="glass-card p-4">
                    <h6 class="text-uppercase letter-spacing-1 smaller text-gold mb-4">Hourly Edge Distribution</h6>
                    <div style="height: 250px;">
                        <canvas id="hourlyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <footer class="mt-5 pb-4 text-center">
            <p class="text-muted-custom smaller">&copy; 2026 Trading Journal Intelligence Suite &bull; Standard Organizational Layout</p>
        </footer>
    </main>

    <script>
    // Chart.js Theme Initialization
    Chart.defaults.color = 'rgba(255, 255, 255, 0.4)';
    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.borderColor = 'rgba(255, 255, 255, 0.05)';

    // 1. Equity Curve
    const equityCtx = document.getElementById('equityCurve').getContext('2d');
    const equityGradient = equityCtx.createLinearGradient(0, 0, 0, 350);
    equityGradient.addColorStop(0, 'rgba(0, 212, 255, 0.15)');
    equityGradient.addColorStop(1, 'rgba(0, 212, 255, 0)');

    new Chart(equityCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($equityCurve, 'date')); ?>,
            datasets: [{
                label: 'Cumulative P&L',
                data: <?php echo json_encode(array_column($equityCurve, 'value')); ?>,
                borderColor: '#00D4FF',
                backgroundColor: equityGradient,
                fill: true,
                tension: 0.4,
                borderWidth: 2,
                pointRadius: 3,
                pointBackgroundColor: '#00D4FF',
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(10, 10, 15, 0.95)',
                    borderColor: 'rgba(255, 255, 255, 0.1)',
                    borderWidth: 1,
                    padding: 12
                }
            },
            scales: {
                y: {
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { callback: value => "$" + value }
                },
                x: { display: false }
            }
        }
    });

    // 2. Daily Performance
    new Chart(document.getElementById('dailyBars'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($dayData, 'day_name')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($dayData, 'pnl')); ?>,
                backgroundColor: <?php echo json_encode(array_map(function($d){ return $d['pnl'] >= 0 ? '#10B981' : '#EF4444'; }, $dayData)); ?>,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false } },
                y: { grid: { display: false }, display: false }
            }
        }
    });

    // 3. Session Analysis
    new Chart(document.getElementById('sessionDoughnut'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($sessionData, 'session')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($sessionData, 'pnl')); ?>,
                backgroundColor: ['#FFD700', '#00D4FF', '#8B5CF6', '#EC4899'],
                borderWidth: 0,
                hoverOffset: 15
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '75%',
            plugins: {
                legend: {
                    position: 'right',
                    labels: { boxWidth: 8, padding: 10, font: { size: 10 } }
                }
            }
        }
    });

    // 4. Instrument Breakdown (NEW)
    new Chart(document.getElementById('instrumentChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($instrumentData, 'code')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($instrumentData, 'pnl')); ?>,
                backgroundColor: <?php echo json_encode(array_map(fn($d) => $d['pnl'] >= 0 ? '#10B981' : '#EF4444', $instrumentData)); ?>,
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: 'rgba(255,255,255,0.05)' } },
                y: { grid: { display: false } }
            }
        }
    });

    // 5. Hourly Distribution (NEW)
    new Chart(document.getElementById('hourlyChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_map(fn($h) => sprintf("%02d:00", $h['hr']), $hourlyData)); ?>,
            datasets: [{
                label: 'Hourly P&L',
                data: <?php echo json_encode(array_column($hourlyData, 'pnl')); ?>,
                borderColor: '#8B5CF6',
                backgroundColor: 'rgba(139, 92, 246, 0.1)',
                fill: true,
                tension: 0.4,
                borderWidth: 2,
                pointRadius: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { grid: { color: 'rgba(255,255,255,0.05)' } },
                x: { grid: { display: false } }
            }
        }
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
