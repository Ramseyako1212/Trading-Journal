<?php
/**
 * Setup and Diagnostic tool for Notifications
 */

require_once 'config/database.php';

$message = "";
$status = "";

try {
    $pdo = getConnection();
    
    // 1. Update Database
    $columns = [
        'notify_on_trade_close' => "BOOLEAN DEFAULT 1",
        'notify_on_overtrading' => "BOOLEAN DEFAULT 1"
    ];

    foreach ($columns as $column => $definition) {
        $check = $pdo->query("SHOW COLUMNS FROM users LIKE '$column'")->fetch();
        if (!$check) {
            $pdo->exec("ALTER TABLE users ADD COLUMN $column $definition");
            $message .= "✅ Added column: $column<br>";
        } else {
            $message .= "ℹ️ Column already exists: $column<br>";
        }
    }

    // 2. Ensure all existing users have it enabled
    $pdo->exec("UPDATE users SET notify_on_trade_close = 1, notify_on_overtrading = 1 WHERE notify_on_trade_close IS NULL OR notify_on_overtrading IS NULL");
    $message .= "✅ All existing users updated to 'Enabled' by default.<br>";

    $status = "success";
} catch (Exception $e) {
    $status = "danger";
    $message = "❌ Error: " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Setup | Trading Journal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #0a0a0f; color: white; font-family: 'Segoe UI', sans-serif; }
        .setup-card { background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255,215,0,0.3); border-radius: 15px; padding: 30px; margin-top: 50px; }
        .text-gold { color: #FFD700; }
        .btn-gold { background: #FFD700; color: #000; font-weight: bold; border: none; }
        .btn-gold:hover { background: #e6c200; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="setup-card text-center">
                    <h2 class="text-gold mb-4">Notification System Setup</h2>
                    
                    <div class="alert alert-<?php echo $status; ?> text-start">
                        <?php echo $message; ?>
                    </div>

                    <p class="mt-4">The database is now ready for Email Notifications.</p>
                    
                    <div class="d-grid gap-2 mt-4">
                        <a href="settings.php" class="btn btn-gold btn-lg">Go to Settings to Configure Email</a>
                        <a href="dashboard.php" class="btn btn-outline-light">Back to Dashboard</a>
                    </div>

                    <div class="mt-5 text-muted small p-3 border border-secondary rounded">
                        <strong>What's next?</strong><br>
                        Since you are using Laragon, make sure to configure your <strong>Mail Sender</strong> settings (Right-click Laragon > Tools > Mail Sender) with your Gmail App Password so the emails can actually fly out!
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
