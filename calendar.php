<?php
/**
 * Calendar View - Monthly Trading Calendar
 */

require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Get current month/year or from params
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Validate
if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

$firstDay = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = date('t', $firstDay);
$startingDay = date('N', $firstDay); // 1 (Mon) to 7 (Sun)
$monthName = date('F', $firstDay);

try {
    $pdo = getConnection();
    
    // Get daily P&L for this month
    $pnlQuery = $pdo->prepare("
        SELECT DATE(entry_time) as trade_date, 
               SUM(net_pnl) as daily_pnl,
               COUNT(*) as trade_count,
               SUM(CASE WHEN net_pnl > 0 THEN 1 ELSE 0 END) as wins
        FROM trades
        WHERE user_id = ? AND status = 'CLOSED'
          AND MONTH(entry_time) = ? AND YEAR(entry_time) = ?
        GROUP BY DATE(entry_time)
    ");
    $pnlQuery->execute([$userId, $month, $year]);
    $dailyData = [];
    while ($row = $pnlQuery->fetch()) {
        $dailyData[$row['trade_date']] = $row;
    }
    
    // Month stats
    $monthStatsQuery = $pdo->prepare("
        SELECT 
            COUNT(*) as total_trades,
            SUM(CASE WHEN net_pnl > 0 THEN 1 ELSE 0 END) as wins,
            COALESCE(SUM(net_pnl), 0) as total_pnl
        FROM trades
        WHERE user_id = ? AND status = 'CLOSED'
          AND MONTH(entry_time) = ? AND YEAR(entry_time) = ?
    ");
    $monthStatsQuery->execute([$userId, $month, $year]);
    $monthStats = $monthStatsQuery->fetch();
    
} catch (PDOException $e) {
    $error = $e->getMessage();
}

$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar | Trading Journal</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
        }
        .calendar-header {
            text-align: center;
            padding: 12px;
            color: var(--gold);
            font-weight: 600;
            font-size: 0.875rem;
        }
        .calendar-day {
            aspect-ratio: 1;
            background: var(--bg-glass);
            border: 1px solid var(--border-glass);
            border-radius: 12px;
            padding: 8px;
            display: flex;
            flex-direction: column;
            transition: var(--transition-smooth);
            cursor: pointer;
            min-height: 80px;
        }
        .calendar-day:hover {
            border-color: var(--gold);
            transform: translateY(-2px);
        }
        .calendar-day.empty {
            background: transparent;
            border: none;
            cursor: default;
        }
        .calendar-day.empty:hover {
            transform: none;
        }
        .calendar-day.today {
            border-color: var(--cyan);
            box-shadow: 0 0 15px rgba(0, 212, 255, 0.2);
        }
        .calendar-day.has-trades {
            border-color: rgba(255, 215, 0, 0.3);
        }
        .day-number {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-secondary);
        }
        .calendar-day.today .day-number {
            color: var(--cyan);
        }
        .day-pnl {
            margin-top: auto;
            font-size: 0.875rem;
            font-weight: 700;
        }
        .day-trades {
            font-size: 0.7rem;
            color: var(--text-muted);
        }
        .positive { color: var(--green); }
        .negative { color: var(--red); }
    </style>
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
                    <a href="dashboard.php" class="sidebar-nav-link"><i class="bi bi-speedometer2"></i>Dashboard</a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="journal.php" class="sidebar-nav-link"><i class="bi bi-journal-richtext"></i>Trade Journal</a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="analytics.php" class="sidebar-nav-link"><i class="bi bi-bar-chart-line"></i>Analytics</a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="calendar.php" class="sidebar-nav-link active"><i class="bi bi-calendar3"></i>Calendar</a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="strategies.php" class="sidebar-nav-link"><i class="bi bi-lightbulb"></i>Strategies</a>
                </li>
                <li class="mt-4 mb-2">
                    <small class="text-muted-custom text-uppercase px-3" style="font-size: 0.7rem;">Account</small>
                </li>
                <li class="sidebar-nav-item">
                    <a href="settings.php" class="sidebar-nav-link"><i class="bi bi-gear"></i>Settings</a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="api/auth/logout.php" class="sidebar-nav-link"><i class="bi bi-box-arrow-left"></i>Logout</a>
                </li>
            </ul>
        </nav>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1"><i class="bi bi-calendar3 text-gold me-2"></i>Trading Calendar</h4>
                <p class="text-muted-custom mb-0">Visual overview of your trading performance</p>
            </div>
        </div>
        
        <!-- Month Navigation -->
        <div class="glass-card mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <a href="?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>" class="btn btn-outline-luxury">
                    <i class="bi bi-chevron-left"></i> Previous
                </a>
                
                <h3 class="text-gold mb-0"><?php echo $monthName . ' ' . $year; ?></h3>
                
                <a href="?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>" class="btn btn-outline-luxury">
                    Next <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </div>
        
        <!-- Month Stats -->
        <div class="row g-3 mb-4">
            <div class="col-4">
                <div class="stat-card text-center py-3">
                    <div class="stat-value fs-4"><?php echo $monthStats['total_trades']; ?></div>
                    <div class="stat-label">Trades</div>
                </div>
            </div>
            <div class="col-4">
                <div class="stat-card text-center py-3">
                    <div class="stat-value fs-4 <?php echo $monthStats['total_pnl'] >= 0 ? 'text-green' : 'text-red'; ?>">
                        <?php echo number_format($monthStats['total_pnl'], 2); ?>
                    </div>
                    <div class="stat-label">Month P&L</div>
                </div>
            </div>
            <div class="col-4">
                <div class="stat-card text-center py-3">
                    <?php 
                    $winRate = $monthStats['total_trades'] > 0 
                        ? ($monthStats['wins'] / $monthStats['total_trades']) * 100 
                        : 0;
                    ?>
                    <div class="stat-value fs-4"><?php echo number_format($winRate, 0); ?>%</div>
                    <div class="stat-label">Win Rate</div>
                </div>
            </div>
        </div>
        
        <!-- Calendar Grid -->
        <div class="dashboard-card">
            <div class="calendar-grid">
                <!-- Day headers -->
                <div class="calendar-header">Mon</div>
                <div class="calendar-header">Tue</div>
                <div class="calendar-header">Wed</div>
                <div class="calendar-header">Thu</div>
                <div class="calendar-header">Fri</div>
                <div class="calendar-header">Sat</div>
                <div class="calendar-header">Sun</div>
                
                <!-- Empty cells before first day -->
                <?php for ($i = 1; $i < $startingDay; $i++): ?>
                <div class="calendar-day empty"></div>
                <?php endfor; ?>
                
                <!-- Days of month -->
                <?php for ($day = 1; $day <= $daysInMonth; $day++): 
                    $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $isToday = $dateStr === date('Y-m-d');
                    $dayData = $dailyData[$dateStr] ?? null;
                ?>
                <div class="calendar-day <?php echo $isToday ? 'today' : ''; ?> <?php echo $dayData ? 'has-trades' : ''; ?>"
                     onclick="viewDayTrades('<?php echo $dateStr; ?>')">
                    <div class="day-number"><?php echo $day; ?></div>
                    <?php if ($dayData): ?>
                    <div class="day-pnl <?php echo $dayData['daily_pnl'] >= 0 ? 'positive' : 'negative'; ?>">
                        <?php echo ($dayData['daily_pnl'] >= 0 ? '+' : '') . number_format($dayData['daily_pnl'], 0); ?>
                    </div>
                    <div class="day-trades"><?php echo $dayData['trade_count']; ?> trade<?php echo $dayData['trade_count'] > 1 ? 's' : ''; ?></div>
                    <?php endif; ?>
                </div>
                <?php endfor; ?>
            </div>
        </div>
        
        <!-- Legend -->
        <div class="glass-card mt-4">
            <div class="d-flex justify-content-center gap-4">
                <div class="d-flex align-items-center gap-2">
                    <div style="width: 20px; height: 20px; background: var(--green); border-radius: 4px;"></div>
                    <span class="text-secondary">Profitable Day</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div style="width: 20px; height: 20px; background: var(--red); border-radius: 4px;"></div>
                    <span class="text-secondary">Loss Day</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div style="width: 20px; height: 20px; border: 2px solid var(--cyan); border-radius: 4px;"></div>
                    <span class="text-secondary">Today</span>
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
        function viewDayTrades(date) {
            window.location.href = `journal.php?date_from=${date}&date_to=${date}`;
        }
    </script>
</body>
</html>
