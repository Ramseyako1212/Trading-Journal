<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$userId = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['responses'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
    exit;
}

$responses = $data['responses'];
$totalItems = count($responses);
$checkedCount = 0;

foreach ($responses as $response) {
    if ($response === true) {
        $checkedCount++;
    }
}

$score = ($totalItems > 0) ? ($checkedCount / $totalItems) * 100 : 0;
$passed = ($score >= 90) ? 1 : 0;
$checkDate = date('Y-m-d');

try {
    $pdo = getConnection();
    
    // Check if entry for today already exists
    $stmt = $pdo->prepare("
        INSERT INTO daily_checklists (user_id, check_date, score_percentage, passed, responses)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            score_percentage = VALUES(score_percentage),
            passed = VALUES(passed),
            responses = VALUES(responses),
            created_at = CURRENT_TIMESTAMP
    ");
    
    $stmt->execute([
        $userId,
        $checkDate,
        $score,
        $passed,
        json_encode($responses)
    ]);
    
    echo json_encode([
        'success' => true, 
        'score' => $score, 
        'passed' => $passed,
        'message' => $passed ? 'Ready to trade! Good luck.' : 'You do not meet the 90% requirement. Trading is restricted.'
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
