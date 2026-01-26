<?php
/**
 * Settings Page - User Account Settings
 */

require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$user = null;
$accounts = [];
$success = '';
$error = '';

try {
    $pdo = getConnection();
    
    // Get user info
    $userQuery = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $userQuery->execute([$userId]);
    $user = $userQuery->fetch();
    
    // Get trading accounts
    $accountsQuery = $pdo->prepare("SELECT * FROM accounts WHERE user_id = ? ORDER BY name");
    $accountsQuery->execute([$userId]);
    $accounts = $accountsQuery->fetchAll();
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_profile') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $timezone = trim($_POST['timezone'] ?? 'UTC');
            
            if ($name && $email) {
                // Check if email is taken by another user
                $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $checkEmail->execute([$email, $userId]);
                if ($checkEmail->fetch()) {
                    $error = "Email is already taken by another account.";
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, timezone = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $timezone, $userId]);
                    $_SESSION['user_name'] = $name;
                    $success = "Profile updated successfully!";
                    $user['name'] = $name;
                    $user['email'] = $email;
                    $user['timezone'] = $timezone;
                }
            }
        } elseif ($action === 'change_password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            if (!password_verify($currentPassword, $user['password'])) {
                $error = "Current password is incorrect.";
            } elseif (strlen($newPassword) < 6) {
                $error = "New password must be at least 6 characters.";
            } elseif ($newPassword !== $confirmPassword) {
                $error = "New passwords do not match.";
            } else {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $userId]);
                $success = "Password changed successfully!";
            }
        } elseif ($action === 'add_account') {
            $accountName = trim($_POST['account_name'] ?? '');
            $broker = trim($_POST['broker'] ?? '');
            $accountType = trim($_POST['account_type'] ?? 'LIVE');
            $balance = floatval($_POST['initial_balance'] ?? 0);
            $currency = trim($_POST['currency'] ?? 'USD');
            
            if ($accountName) {
                $stmt = $pdo->prepare("INSERT INTO accounts (user_id, name, broker, type, initial_balance, current_balance, currency) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $accountName, $broker, $accountType, $balance, $balance, $currency]);
                $success = "Trading account added!";
                
                // Refresh accounts list
                $accountsQuery->execute([$userId]);
                $accounts = $accountsQuery->fetchAll();
            }
        } elseif ($action === 'delete_account') {
            $accountId = (int)($_POST['account_id'] ?? 0);
            if ($accountId > 0) {
                $stmt = $pdo->prepare("DELETE FROM accounts WHERE id = ? AND user_id = ?");
                $stmt->execute([$accountId, $userId]);
                $success = "Account deleted.";
                
                $accountsQuery->execute([$userId]);
                $accounts = $accountsQuery->fetchAll();
            }
        }
    }
    
} catch (PDOException $e) {
    $error = $e->getMessage();
}

$timezones = [
    'America/New_York' => 'Eastern Time (ET)',
    'America/Chicago' => 'Central Time (CT)',
    'America/Denver' => 'Mountain Time (MT)',
    'America/Los_Angeles' => 'Pacific Time (PT)',
    'UTC' => 'UTC',
    'Europe/London' => 'London (GMT)',
    'Europe/Paris' => 'Paris (CET)',
    'Asia/Tokyo' => 'Tokyo (JST)',
    'Asia/Shanghai' => 'Shanghai (CST)',
    'Asia/Singapore' => 'Singapore (SGT)',
    'Australia/Sydney' => 'Sydney (AEST)'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | Trading Journal</title>
    
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
                    <a href="calendar.php" class="sidebar-nav-link"><i class="bi bi-calendar3"></i>Calendar</a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="strategies.php" class="sidebar-nav-link"><i class="bi bi-lightbulb"></i>Strategies</a>
                </li>
                <li class="mt-4 mb-2">
                    <small class="text-muted-custom text-uppercase px-3" style="font-size: 0.7rem;">Account</small>
                </li>
                <li class="sidebar-nav-item">
                    <a href="settings.php" class="sidebar-nav-link active"><i class="bi bi-gear"></i>Settings</a>
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
                <h4 class="mb-1"><i class="bi bi-gear text-gold me-2"></i>Settings</h4>
                <p class="text-muted-custom mb-0">Manage your account and preferences</p>
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
            <!-- Profile Settings -->
            <div class="col-lg-6">
                <div class="dashboard-card h-100">
                    <h5 class="text-gold mb-4"><i class="bi bi-person me-2"></i>Profile Settings</h5>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-control-luxury" 
                                   value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control-luxury" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Timezone</label>
                            <select name="timezone" class="form-control-luxury">
                                <?php foreach ($timezones as $tz => $label): ?>
                                <option value="<?php echo $tz; ?>" <?php echo ($user['timezone'] ?? 'UTC') === $tz ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Member Since</label>
                            <input type="text" class="form-control-luxury" disabled 
                                   value="<?php echo date('F j, Y', strtotime($user['created_at'])); ?>">
                        </div>
                        
                        <button type="submit" class="btn btn-luxury w-100">
                            <i class="bi bi-check me-2"></i>Save Changes
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Change Password -->
            <div class="col-lg-6">
                <div class="dashboard-card h-100">
                    <h5 class="text-cyan mb-4"><i class="bi bi-shield-lock me-2"></i>Change Password</h5>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control-luxury" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control-luxury" required minlength="6">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control-luxury" required>
                        </div>
                        
                        <button type="submit" class="btn btn-outline-luxury w-100">
                            <i class="bi bi-key me-2"></i>Change Password
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Trading Accounts -->
            <div class="col-12">
                <div class="dashboard-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="text-gold mb-0"><i class="bi bi-wallet2 me-2"></i>Trading Accounts</h5>
                        <button class="btn btn-sm btn-luxury" data-bs-toggle="modal" data-bs-target="#addAccountModal">
                            <i class="bi bi-plus"></i> Add Account
                        </button>
                    </div>
                    
                    <?php if (empty($accounts)): ?>
                    <div class="text-center text-muted-custom py-4">
                        <i class="bi bi-wallet2 fs-1 d-block mb-3 opacity-50"></i>
                        <p>No trading accounts added yet.</p>
                        <button class="btn btn-outline-luxury" data-bs-toggle="modal" data-bs-target="#addAccountModal">
                            Add Your First Account
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table-luxury">
                            <thead>
                                <tr>
                                    <th>Account Name</th>
                                    <th>Broker</th>
                                    <th>Type</th>
                                    <th>Initial Balance</th>
                                    <th>Current Balance</th>
                                    <th>Currency</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($accounts as $account): ?>
                                <tr>
                                    <td class="fw-medium"><?php echo htmlspecialchars($account['name']); ?></td>
                                    <td class="text-secondary"><?php echo htmlspecialchars($account['broker'] ?: '-'); ?></td>
                                    <td>
                                        <span class="badge <?php echo $account['type'] === 'LIVE' ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                            <?php echo $account['type']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($account['initial_balance'], 2); ?> <small><?php echo $account['currency']; ?></small></td>
                                    <td class="<?php echo $account['current_balance'] >= $account['initial_balance'] ? 'text-green' : 'text-red'; ?>">
                                        <?php echo number_format($account['current_balance'], 2); ?> <small><?php echo $account['currency']; ?></small>
                                    </td>
                                    <td><?php echo $account['currency']; ?></td>
                                    <td>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this account?');">
                                            <input type="hidden" name="action" value="delete_account">
                                            <input type="hidden" name="account_id" value="<?php echo $account['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Danger Zone -->
            <div class="col-12">
                <div class="dashboard-card" style="border: 1px solid var(--red);">
                    <h5 class="text-red mb-3"><i class="bi bi-exclamation-triangle me-2"></i>Danger Zone</h5>
                    <p class="text-secondary mb-3">These actions are irreversible. Please be careful.</p>
                    <div class="d-flex gap-3">
                        <button class="btn btn-outline-danger" onclick="alert('This feature is not yet implemented.')">
                            <i class="bi bi-download me-2"></i>Export All Data
                        </button>
                        <button class="btn btn-outline-danger" onclick="alert('Contact support to delete your account.')">
                            <i class="bi bi-trash me-2"></i>Delete Account
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Add Account Modal -->
    <div class="modal fade" id="addAccountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content modal-luxury">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-wallet2 text-gold me-2"></i>Add Trading Account</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add_account">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label-luxury">Account Name *</label>
                            <input type="text" name="account_name" class="form-control-luxury" required placeholder="e.g., Main Trading Account">
                        </div>
                        <div class="mb-3">
                            <label class="form-label-luxury">Broker</label>
                            <input type="text" name="broker" class="form-control-luxury" placeholder="e.g., Interactive Brokers">
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label-luxury">Account Type</label>
                                <select name="account_type" class="form-control-luxury">
                                    <option value="LIVE">Live</option>
                                    <option value="PAPER">Paper/Demo</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label-luxury">Currency</label>
                                <select name="currency" class="form-control-luxury">
                                    <option value="USD">USD ($)</option>
                                    <option value="USC">USC (¢)</option>
                                    <option value="EUR">EUR (€)</option>
                                    <option value="GBP">GBP (£)</option>
                                    <option value="JPY">JPY (¥)</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label-luxury">Initial Balance</label>
                            <input type="number" step="0.01" name="initial_balance" class="form-control-luxury" value="10000" min="0">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-luxury" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-luxury">Add Account</button>
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
