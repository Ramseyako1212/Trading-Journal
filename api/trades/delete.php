<?php
/**
 * Delete Trade API
 */

header('Content-Type: application/json');

require_once '../../config/database.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$userId = $_SESSION['user_id'];

// Get data from various sources (POST, JSON body, or GET)
$postData = json_decode(file_get_contents('php://input'), true);
$tradeId = filter_input(INPUT_POST, 'trade_id', FILTER_SANITIZE_NUMBER_INT) 
         ?: ($postData['id'] ?? $postData['trade_id'] ?? null)
         ?: filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

if (!$tradeId) {
    echo json_encode(['success' => false, 'message' => 'Trade ID is required']);
    exit;
}

try {
    $pdo = getConnection();
    
    // Get attachments before deleting
    $attachmentsQuery = $pdo->prepare("SELECT file_path FROM attachments WHERE trade_id = ? AND user_id = ?");
    $attachmentsQuery->execute([$tradeId, $userId]);
    $attachments = $attachmentsQuery->fetchAll();
    
    // Delete trade (cascade will delete attachments from DB)
    $stmt = $pdo->prepare("DELETE FROM trades WHERE id = ? AND user_id = ?");
    $stmt->execute([$tradeId, $userId]);
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Trade not found or already deleted']);
        exit;
    }
    
    // Delete attachment files
    foreach ($attachments as $attachment) {
        $filePath = '../../' . $attachment['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Trade deleted successfully'
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
