<?php
/**
 * Google Auth Handler
 */
header('Content-Type: application/json');
require_once '../../config/database.php';

// Get the ID token from the POST request
$id_token = $_POST['credential'] ?? '';

if (empty($id_token)) {
    echo json_encode(['success' => false, 'message' => 'No credential provided']);
    exit;
}

/**
 * For this implementation, we use Google's tokeninfo endpoint to verify.
 * In production, you'd want to use a JWT library to verify locally for performance.
 */
function verifyGoogleToken($token) {
    $url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $token;
    $response = file_get_contents($url);
    return json_decode($response, true);
}

$payload = verifyGoogleToken($id_token);

if (!$payload || isset($payload['error'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid Google token']);
    exit;
}

// Extract data
$email = $payload['email'];
$name = $payload['name'];
$googleId = $payload['sub']; // Unique Google ID
$avatar = $payload['picture'] ?? null;

try {
    $pdo = getConnection();
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        $userId = $user['id'];
        // Update user's avatar if they don't have one
        $update = $pdo->prepare("UPDATE users SET avatar = COALESCE(avatar, ?) WHERE id = ?");
        $update->execute([$avatar, $userId]);
    } else {
        // Register new user
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, avatar) VALUES (?, ?, ?, ?)");
        // Use a random password for social login users
        $randomPass = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $stmt->execute([$name, $email, $randomPass, $avatar]);
        $userId = $pdo->lastInsertId();
        
        // Setup default account for new user
        $pdo->prepare("INSERT INTO accounts (user_id, name, broker, type, currency, initial_balance, current_balance) VALUES (?, 'Default Account', 'Google Auth', 'LIVE', 'USD', 0, 0)")
            ->execute([$userId]);
            
        // Setup default strategy
        $pdo->prepare("INSERT INTO strategies (user_id, name, description, color) VALUES (?, 'Trend Following', 'Default strategy from Google Login', '#FFD700')")
            ->execute([$userId]);
    }
    
    // Set session
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;
    $_SESSION['logged_in'] = true;
    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'redirect' => 'dashboard.php'
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
