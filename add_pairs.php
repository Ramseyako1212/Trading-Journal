<?php
require_once 'config/database.php';
try {
    $pdo = getConnection();
    $pairs = [
        ['AUDUSD', 'AUD/USD', 'Forex', 0.0001, 10.00, 'Sun-Fri 5:00 PM - 5:00 PM ET'],
        ['NZDUSD', 'NZD/USD', 'Forex', 0.0001, 10.00, 'Sun-Fri 5:00 PM - 5:00 PM ET'],
        ['USDCAD', 'USD/CAD', 'Forex', 0.0001, 10.00, 'Sun-Fri 5:00 PM - 5:00 PM ET'],
        ['USDCHF', 'USD/CHF', 'Forex', 0.0001, 10.00, 'Sun-Fri 5:00 PM - 5:00 PM ET'],
        ['USDJPY', 'USD/JPY', 'Forex', 0.01, 6.50, 'Sun-Fri 5:00 PM - 5:00 PM ET'],
    ];
    
    foreach ($pairs as $p) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO instruments (code, name, type, tick_size, tick_value, session_times) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute($p);
        echo "Added/Checked: " . $p[0] . "<br>";
    }
    echo "Done.";
} catch (Exception $e) {
    echo $e->getMessage();
}
