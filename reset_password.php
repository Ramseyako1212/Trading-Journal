<?php
/**
 * Emergency Password Reset Utility
 * Use this ONLY if you forgotten your password.
 * DELETE THIS FILE AFTER USE FOR SECURITY!
 */

require_once 'config/database.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    
    if (empty($email) || empty($newPassword)) {
        $error = "Both fields are required.";
    } elseif (strlen($newPassword) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        try {
            $pdo = getConnection();
            
            // Check if user exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $error = "User with that email address not found.";
            } else {
                // Update password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update->execute([$hashedPassword, $user['id']]);
                
                $message = "Password updated successfully! You can now <a href='login.php' style='color:#00D4FF;'>Login here</a>.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Trading Journal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #0a0a0f; color: #fff; font-family: 'Inter', sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .reset-card { background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 215, 0, 0.2); border-radius: 16px; padding: 40px; width: 100%; max-width: 400px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .text-gold { color: #FFD700; }
        .form-control { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #fff; border-radius: 8px; padding: 12px; }
        .form-control:focus { background: rgba(255,255,255,0.1); border-color: #FFD700; color: #fff; box-shadow: none; }
        .btn-gold { background: linear-gradient(135deg, #FFD700 0%, #B8860B 100%); color: #000; font-weight: 600; border: none; border-radius: 8px; padding: 12px; margin-top: 10px; }
        .alert { border-radius: 8px; border: none; }
        .alert-success { background: rgba(16, 185, 129, 0.1); color: #10B981; }
        .alert-danger { background: rgba(239, 68, 68, 0.1); color: #EF4444; }
    </style>
</head>
<body>
    <div class="reset-card">
        <h3 class="text-gold mb-1">Password Reset</h3>
        <p class="text-secondary small mb-4">Emergency utility for local development</p>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label small text-secondary">User Email</label>
                <input type="email" name="email" class="form-control" placeholder="demo@tradingjournal.com" required>
            </div>
            <div class="mb-3">
                <label class="form-label small text-secondary">New Password</label>
                <input type="password" name="new_password" class="form-control" placeholder="Min 8 characters" required>
            </div>
            <button type="submit" class="btn btn-gold w-100">Update Password</button>
        </form>
        
        <div class="mt-4 text-center">
            <a href="login.php" class="text-secondary text-decoration-none small">← Back to Login</a>
        </div>
        
        <p class="mt-4 mb-0 text-center text-muted x-small" style="font-size: 10px;">
            ⚠️ Delete 이 file (`reset_password.php`) from your folder after use!
        </p>
    </div>
</body>
</html>
