<?php
/**
 * Update Instruments script
 */
require_once dirname(__DIR__) . '/config/database.php';

header('Content-Type: text/plain');

try {
    $pdo = getConnection();
    
    // Add UNIQUE constraint to code if it doesn't exist (MySQL syntax)
    try {
        $pdo->exec("ALTER TABLE instruments ADD UNIQUE (code)");
    } catch (Exception $e) {
        // Already unique or failed for other reasons, continue
    }
    
    $instruments = [
        // Crypto
        ['BTC', 'Bitcoin', 'Crypto', 1.00, 1.00, '24/7'],
        ['ETH', 'Ethereum', 'Crypto', 0.01, 1.00, '24/7'],
        
        // Metals & Spot
        ['XAUUSD', 'XAU/USD (Gold)', 'Spot', 0.01, 1.00, 'Sun-Fri 6:00 PM - 5:00 PM ET'],
        ['XAGUSD', 'XAG/USD (Silver)', 'Spot', 0.001, 50.00, 'Sun-Fri 6:00 PM - 5:00 PM ET'],
        
        // Forex Majors
        ['EURUSD', 'EUR/USD', 'Forex', 0.0001, 10.00, 'Sun-Fri 5:00 PM - 5:00 PM ET'],
        ['GBPUSD', 'GBP/USD', 'Forex', 0.0001, 10.00, 'Sun-Fri 5:00 PM - 5:00 PM ET'],
        ['USDCHF', 'USD/CHF', 'Forex', 0.0001, 10.00, 'Sun-Fri 5:00 PM - 5:00 PM ET'],
        ['USDCAD', 'USD/CAD', 'Forex', 0.0001, 10.00, 'Sun-Fri 5:00 PM - 5:00 PM ET'],
        ['AUDUSD', 'AUD/USD', 'Forex', 0.0001, 10.00, 'Sun-Fri 5:00 PM - 5:00 PM ET'],
        ['NZDUSD', 'NZD/USD', 'Forex', 0.0001, 10.00, 'Sun-Fri 5:00 PM - 5:00 PM ET'],
        ['USDJPY', 'USD/JPY', 'Forex', 0.01, 6.50, 'Sun-Fri 5:00 PM - 5:00 PM ET'],

        // Forex Minors / Crosses (from user images)
        ['AUDCAD', 'AUD/CAD', 'Forex', 0.0001, 10.00, 'Sun-Fri 5:00 PM - 5:00 PM ET'],
        ['AUDCHF', 'AUD/CHF', 'Forex', 0.0001, 10.00, 'Sun-Fri 5:00 PM - 5:00 PM ET'],
        ['AUDJPY', 'AUD/JPY', 'Forex', 0.01, 6.50, 'Sun-Fri 5:00 PM - 5:00 PM ET'],
        ['AUDNZD', 'AUD/NZD', 'Forex', 0.0001, 10.00, 'Sun-Fri 5:00 PM - 5:00 PM ET'],
        ['CADCHF', 'CAD/CHF', 'Forex', 0.0001, 10.00, 'Sun-Fri 5:00 PM - 5:00 PM ET'],
        ['CADJPY', 'CAD/JPY', 'Forex', 0.01, 6.50, 'Sun-Fri 5:00 PM - 5:00 PM ET'],
        ['CHFJPY', 'CHF/JPY', 'Forex', 0.01, 6.50, 'Sun-Fri 5:00 PM - 5:00 PM ET'],
        ['EURAUD', 'EUR/AUD', 'Forex', 0.0001, 10.00, 'Sun-Fri 5:00 PM - 5:00 PM ET'],
        ['EURCAD', 'EUR/CAD', 'Forex', 0.0001, 10.00, 'Sun-Fri 5:00 PM - 5:00 PM ET'],
        ['EURCHF', 'EUR/CHF', 'Forex', 0.0001, 10.00, 'Sun-Fri 5:00 PM - 5:00 PM ET'],
        ['EURGBP', 'EUR/GBP', 'Forex', 0.0001, 10.00, 'Sun-Fri 5:00 PM - 5:00 PM ET'],
        ['EURJPY', 'EUR/JPY', 'Forex', 0.01, 6.50, 'Sun-Fri 5:00 PM - 5:00 PM ET'],
        ['EURNZD', 'EUR/NZD', 'Forex', 0.0001, 10.00, 'Sun-Fri 5:00 PM - 5:00 PM ET'],
        ['GBPAUD', 'GBP/AUD', 'Forex', 0.0001, 10.00, 'Sun-Fri 5:00 PM - 5:00 PM ET'],
        ['GBPCAD', 'GBP/CAD', 'Forex', 0.0001, 10.00, 'Sun-Fri 5:00 PM - 5:00 PM ET'],
        ['GBPCHF', 'GBP/CHF', 'Forex', 0.0001, 10.00, 'Sun-Fri 5:00 PM - 5:00 PM ET'],
        ['GBPJPY', 'GBP/JPY', 'Forex', 0.01, 6.50, 'Sun-Fri 5:00 PM - 5:00 PM ET'],
        ['GBPNZD', 'GBP/NZD', 'Forex', 0.0001, 10.00, 'Sun-Fri 5:00 PM - 5:00 PM ET'],
        ['NZDCAD', 'NZD/CAD', 'Forex', 0.0001, 10.00, 'Sun-Fri 5:00 PM - 5:00 PM ET'],
        ['NZDCHF', 'NZD/CHF', 'Forex', 0.0001, 10.00, 'Sun-Fri 5:00 PM - 5:00 PM ET'],
        ['NZDJPY', 'NZD/JPY', 'Forex', 0.01, 6.50, 'Sun-Fri 5:00 PM - 5:00 PM ET']
    ];

    $stmt = $pdo->prepare("INSERT INTO instruments (code, name, type, tick_size, tick_value, session_times) 
                           VALUES (?, ?, ?, ?, ?, ?) 
                           ON DUPLICATE KEY UPDATE 
                           name=VALUES(name), type=VALUES(type), tick_size=VALUES(tick_size), 
                           tick_value=VALUES(tick_value), session_times=VALUES(session_times)");

    foreach ($instruments as $inst) {
        $stmt->execute($inst);
    }

    echo "Instruments updated successfully! Added/Updated " . count($instruments) . " items.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

