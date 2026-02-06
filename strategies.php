<?php
/**
 * Strategies Management Page
 */

require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$strategies = [];
$instruments = [];
$success = '';
$error = '';

try {
    $pdo = getConnection();
    
    // Verify user still exists (prevents FK errors if DB was reset)
    $verifyUser = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $verifyUser->execute([$userId]);
    if (!$verifyUser->fetch()) {
        session_destroy();
        header('Location: login.php?error=session_expired');
        exit;
    }
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add_strategy') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $rules = trim($_POST['rules'] ?? '');
            
            if ($name) {
                $stmt = $pdo->prepare("INSERT INTO strategies (user_id, name, description, rules) VALUES (?, ?, ?, ?)");
                $stmt->execute([$userId, $name, $description, $rules]);
                $success = "Strategy added successfully!";
            }
        } elseif ($action === 'delete_strategy') {
            $strategyId = (int)($_POST['strategy_id'] ?? 0);
            if ($strategyId > 0) {
                $stmt = $pdo->prepare("DELETE FROM strategies WHERE id = ? AND user_id = ?");
                $stmt->execute([$strategyId, $userId]);
                $success = "Strategy deleted.";
            }
        } elseif ($action === 'add_instrument') {
            $code = strtoupper(trim($_POST['symbol'] ?? ''));
            $name = trim($_POST['name'] ?? '');
            $type = trim($_POST['type'] ?? 'FUTURES');
            $tickSize = floatval($_POST['tick_size'] ?? 0.01);
            $tickValue = floatval($_POST['tick_value'] ?? 10);
            
            if ($code && $name) {
                $stmt = $pdo->prepare("INSERT INTO instruments (code, name, type, tick_size, tick_value) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$code, $name, $type, $tickSize, $tickValue]);
                $success = "Instrument added successfully!";
            }
        }
    }
    
    // Get strategies with performance stats
    $strategiesQuery = $pdo->prepare("
        SELECT s.*, 
               COUNT(t.id) as trade_count,
               COALESCE(SUM(t.net_pnl), 0) as total_pnl,
               SUM(CASE WHEN t.net_pnl > 0 THEN 1 ELSE 0 END) as wins
        FROM strategies s
        LEFT JOIN trades t ON t.strategy_id = s.id AND t.status = 'CLOSED'
        WHERE s.user_id = ? OR s.user_id IS NULL
        GROUP BY s.id
        ORDER BY s.name
    ");
    $strategiesQuery->execute([$userId]);
    $strategies = $strategiesQuery->fetchAll();
    
    // Get instruments
    $instrumentsQuery = $pdo->query("SELECT * FROM instruments ORDER BY code");
    $instruments = $instrumentsQuery->fetchAll();
    
} catch (PDOException $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Strategies | Trading Journal</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="bg-animated"></div>
    <div class="grid-overlay"></div>
    
    <?php include "includes/sidebar.php"; ?>

    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1"><i class="bi bi-lightbulb text-gold me-2"></i>Strategies & Instruments</h4>
                <p class="text-muted-custom mb-0">Manage your trading strategies and instruments</p>
            </div>
        </div>
        
        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="row g-4">
            <!-- Strategies Section -->
            <div class="col-lg-7">
                <div class="dashboard-card h-100">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="text-gold mb-0"><i class="bi bi-diagram-3 me-2"></i>Trading Strategies</h5>
                        <button class="btn btn-sm btn-luxury" data-bs-toggle="modal" data-bs-target="#addStrategyModal">
                            <i class="bi bi-plus"></i> Add Strategy
                        </button>
                    </div>
                    
                    <?php if (empty($strategies)): ?>
                    <div class="text-center text-muted-custom py-5">
                        <i class="bi bi-diagram-3 fs-1 d-block mb-3 opacity-50"></i>
                        <p>No strategies defined yet.</p>
                        <button class="btn btn-outline-luxury" data-bs-toggle="modal" data-bs-target="#addStrategyModal">
                            Add Your First Strategy
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="accordion" id="strategiesAccordion">
                        <?php foreach ($strategies as $i => $strategy): 
                            $winRate = $strategy['trade_count'] > 0 
                                ? ($strategy['wins'] / $strategy['trade_count']) * 100 
                                : 0;
                        ?>
                        <div class="accordion-item" style="background: var(--bg-glass); border: 1px solid var(--border-glass); margin-bottom: 8px; border-radius: 8px;">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" 
                                        data-bs-toggle="collapse" data-bs-target="#strategy<?php echo $strategy['id']; ?>"
                                        style="background: transparent; color: var(--text-primary);">
                                    <span class="me-3"><?php echo htmlspecialchars($strategy['name']); ?></span>
                                    <span class="badge bg-secondary me-2"><?php echo $strategy['trade_count']; ?> trades</span>
                                    <span class="badge <?php echo $strategy['total_pnl'] >= 0 ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo ($strategy['total_pnl'] >= 0 ? '+' : '') . '$' . number_format($strategy['total_pnl'], 0); ?>
                                    </span>
                                </button>
                            </h2>
                            <div id="strategy<?php echo $strategy['id']; ?>" class="accordion-collapse collapse" 
                                 data-bs-parent="#strategiesAccordion">
                                <div class="accordion-body" style="color: var(--text-secondary);">
                                    <?php if ($strategy['description']): ?>
                                    <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($strategy['description'])); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if ($strategy['rules']): ?>
                                    <p><strong>Rules:</strong></p>
                                    <pre class="text-light" style="background: var(--bg-secondary); padding: 12px; border-radius: 8px; white-space: pre-wrap;"><?php echo htmlspecialchars($strategy['rules']); ?></pre>
                                    <?php endif; ?>
                                    
                                    <div class="row g-3 mt-2">
                                        <div class="col-4">
                                            <div class="text-center p-2" style="background: var(--bg-secondary); border-radius: 8px;">
                                                <div class="fs-5 text-gold"><?php echo $strategy['trade_count']; ?></div>
                                                <small class="text-muted">Trades</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-center p-2" style="background: var(--bg-secondary); border-radius: 8px;">
                                                <div class="fs-5"><?php echo number_format($winRate, 0); ?>%</div>
                                                <small class="text-muted">Win Rate</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-center p-2" style="background: var(--bg-secondary); border-radius: 8px;">
                                                <div class="fs-5 <?php echo $strategy['total_pnl'] >= 0 ? 'text-green' : 'text-red'; ?>">
                                                    $<?php echo number_format($strategy['total_pnl'], 0); ?>
                                                </div>
                                                <small class="text-muted">Total P&L</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($strategy['user_id']): ?>
                                    <form method="POST" class="mt-3" onsubmit="return confirm('Delete this strategy?');">
                                        <input type="hidden" name="action" value="delete_strategy">
                                        <input type="hidden" name="strategy_id" value="<?php echo $strategy['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i> Delete Strategy
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Instruments Section -->
            <div class="col-lg-5">
                <div class="dashboard-card h-100">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="text-cyan mb-0"><i class="bi bi-bar-chart me-2"></i>Instruments</h5>
                        <button class="btn btn-sm btn-outline-luxury" data-bs-toggle="modal" data-bs-target="#addInstrumentModal">
                            <i class="bi bi-plus"></i> Add
                        </button>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table-luxury">
                            <thead>
                                <tr>
                                    <th>Symbol</th>
                                    <th>Name</th>
                                    <th>Tick Size</th>
                                    <th>Tick Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($instruments as $inst): ?>
                                <tr>
                                    <td><span class="badge bg-gold text-dark"><?php echo htmlspecialchars($inst['code']); ?></span></td>
                                    <td class="text-secondary"><?php echo htmlspecialchars($inst['name']); ?></td>
                                    <td><?php echo $inst['tick_size']; ?></td>
                                    <td>$<?php echo number_format($inst['tick_value'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Add Strategy Modal -->
    <div class="modal fade" id="addStrategyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content modal-luxury">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle text-gold me-2"></i>Add Strategy</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add_strategy">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label-luxury">Strategy Name *</label>
                            <input type="text" name="name" class="form-control-luxury" required placeholder="e.g., Breakout Strategy">
                        </div>
                        <div class="mb-3">
                            <label class="form-label-luxury">Description</label>
                            <textarea name="description" class="form-control-luxury" rows="3" 
                                      placeholder="Brief description of what this strategy does..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label-luxury">Trading Rules</label>
                            <textarea name="rules" class="form-control-luxury" rows="5" 
                                      placeholder="Entry rules:&#10;1. ...&#10;2. ...&#10;&#10;Exit rules:&#10;1. ..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-luxury" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-luxury">Add Strategy</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Add Instrument Modal -->
    <div class="modal fade" id="addInstrumentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content modal-luxury">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle text-cyan me-2"></i>Add Instrument</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add_instrument">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-4">
                                <label class="form-label-luxury">Symbol *</label>
                                <input type="text" name="symbol" class="form-control-luxury" required placeholder="ES">
                            </div>
                            <div class="col-8">
                                <label class="form-label-luxury">Name *</label>
                                <input type="text" name="name" class="form-control-luxury" required placeholder="E-mini S&P 500">
                            </div>
                            <div class="col-4">
                                <label class="form-label-luxury">Type</label>
                                <select name="type" class="form-control-luxury">
                                    <option value="FUTURES">Futures</option>
                                    <option value="FOREX">Forex</option>
                                    <option value="STOCK">Stock</option>
                                    <option value="CRYPTO">Crypto</option>
                                </select>
                            </div>
                            <div class="col-4">
                                <label class="form-label-luxury">Tick Size</label>
                                <input type="number" step="0.0001" name="tick_size" class="form-control-luxury" value="0.01">
                            </div>
                            <div class="col-4">
                                <label class="form-label-luxury">Tick Value ($)</label>
                                <input type="number" step="0.01" name="tick_value" class="form-control-luxury" value="10">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-luxury" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-luxury">Add Instrument</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Mobile Menu Toggle -->
    <button class="btn btn-luxury d-lg-none position-fixed" 
            style="bottom: 2rem; right: 2rem; z-index: 1001; width: 56px; height: 56px; border-radius: 50%; padding: 0;"
            onclick="document.getElementById('sidebar').classList.toggle('show')">
        <i class="bi bi-list fs-4"></i>
    </button>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
