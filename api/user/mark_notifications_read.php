<?php
/**
 * Mark Notifications as Read
 */

header('Content-Type: application/json');
require_once '../../config/database.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $pdo = getConnection();
    $stmt = $pdo->prepare("UPDATE system_notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
