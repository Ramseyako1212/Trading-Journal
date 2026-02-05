<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$checkDate = date('Y-m-d');

try {
    $pdo = getConnection();
    
    // Get checklist status for today
    $stmt = $pdo->prepare("SELECT * FROM daily_checklists WHERE user_id = ? AND check_date = ?");
    $stmt->execute([$userId, $checkDate]);
    $checklist = $stmt->fetch();
    
    // NEW: Get daily trade limit and current count
    $userStmt = $pdo->prepare("SELECT daily_trade_limit FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();
    $limit = (int)($user['daily_trade_limit'] ?? 2);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM trades WHERE user_id = ? AND DATE(entry_time) = CURDATE() AND status != 'CANCELLED'");
    $countStmt->execute([$userId]);
    $todayTradeCount = (int)$countStmt->fetchColumn();

    // Get custom rules
    $rulesStmt = $pdo->prepare("SELECT id, rule_text FROM user_rules WHERE user_id = ? ORDER BY display_order ASC");
    $rulesStmt->execute([$userId]);
    $userRules = $rulesStmt->fetchAll();
    
    // Default rules if user has none
    if (empty($userRules)) {
        $defaultRules = [
            "Did I get at least 7 hours of quality sleep?",
            "Is my head clear (no 'cloudy head', stress, or hangover)?",
            "Have I checked the economic calendar for high-impact news?",
            "Is my trading plan/strategy open and visible?",
            "Do I have a specific daily loss limit set for today?",
            "Is my trading environment quiet and free of distractions?",
            "Have I checked my internet and broker connection?",
            "Am I feeling patient and ready to wait for my setups?",
            "Am I committed to journaling every single trade today?",
            "Have I identified key support/resistance levels on my charts?"
        ];
        
        $rulesResponse = [];
        foreach ($defaultRules as $index => $rule) {
            $rulesResponse[] = ['id' => 'd' . $index, 'rule_text' => $rule];
        }
    } else {
        $rulesResponse = $userRules;
    }
    
    echo json_encode([
        'success' => true,
        'completed' => (bool)$checklist,
        'passed' => $checklist ? (bool)$checklist['passed'] : false,
        'score' => $checklist ? (float)$checklist['score_percentage'] : 0,
        'daily_trade_limit' => $limit,
        'today_trade_count' => $todayTradeCount,
        'rules' => $rulesResponse
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
