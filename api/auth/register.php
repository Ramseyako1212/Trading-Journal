<?php
/**
 * Register API Endpoint
 */

header('Content-Type: application/json');

require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';
$trading_focus = $_POST['trading_focus'] ?? 'crude_oil';

// Validation
$errors = [];

if (empty($name) || strlen($name) < 2) {
    $errors[] = 'Name must be at least 2 characters';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Valid email is required';
}

if (empty($password) || strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters';
}

if ($password !== $password_confirm) {
    $errors[] = 'Passwords do not match';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

try {
    $pdo = getConnection();
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        exit;
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$name, $email, $hashedPassword]);
    
    $userId = $pdo->lastInsertId();
    
    // Create default trading account
    $stmt = $pdo->prepare("INSERT INTO accounts (user_id, name, broker, type, currency, initial_balance, current_balance) VALUES (?, 'Default Account', 'Generic', 'LIVE', 'USD', 0, 0)");
    $stmt->execute([$userId]);
    
    // Create default strategy
    $stmt = $pdo->prepare("INSERT INTO strategies (user_id, name, description, color) VALUES (?, 'Default Strategy', 'My primary trading strategy', '#FFD700')");
    $stmt->execute([$userId]);
    
    // Create default tags
    $defaultTags = [
        ['Scalp', '#00D4FF'],
        ['Swing', '#8B5CF6'],
        ['Breakout', '#10B981'],
        ['Reversal', '#EF4444'],
        ['Trend', '#FFD700']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO tags (user_id, name, color) VALUES (?, ?, ?)");
    foreach ($defaultTags as $tag) {
        $stmt->execute([$userId, $tag[0], $tag[1]]);
    }
    
    // Set session
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;
    $_SESSION['logged_in'] = true;
    
    echo json_encode([
        'success' => true, 
        'message' => 'Account created successfully',
        'user' => [
            'id' => $userId,
            'name' => $name,
            'email' => $email
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
