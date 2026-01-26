<?php
/**
 * Trading Journal - Setup Script
 * Run this once to set up the database
 */

// Database configuration
$host = 'localhost';
$user = 'root';
$pass = '';

echo "<style>
    body { 
        font-family: 'Segoe UI', system-ui, sans-serif; 
        background: #0a0a0f; 
        color: #fff; 
        padding: 40px;
        line-height: 1.8;
    }
    .container { max-width: 800px; margin: 0 auto; }
    h1 { color: #FFD700; }
    .success { color: #10B981; }
    .error { color: #EF4444; }
    .warning { color: #F59E0B; }
    .box { 
        background: rgba(255,255,255,0.05); 
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 12px;
        padding: 20px;
        margin: 20px 0;
    }
    code { 
        background: rgba(255,215,0,0.1); 
        padding: 2px 8px; 
        border-radius: 4px;
        color: #FFD700;
    }
    a { color: #00D4FF; }
    .btn {
        display: inline-block;
        background: linear-gradient(135deg, #FFD700 0%, #B8860B 100%);
        color: #000;
        padding: 12px 24px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        margin-top: 20px;
    }
</style>";

echo "<div class='container'>";
echo "<h1>🚀 Trading Journal Setup</h1>";

try {
    // Connect to MySQL
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p class='success'>✅ Connected to MySQL successfully</p>";
    
    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS trading_journal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p class='success'>✅ Database 'trading_journal' created/verified</p>";
    
    // Select database
    $pdo->exec("USE trading_journal");
    
    // Read and execute schema
    $schemaFile = __DIR__ . '/database/schema.sql';
    if (file_exists($schemaFile)) {
        $schema = file_get_contents($schemaFile);
        
        // Remove comments and execute
        $schema = preg_replace('/--.*?\n/', "\n", $schema);
        $schema = preg_replace('/\/\*.*?\*\//s', '', $schema);
        
        // Split by semicolon and execute each statement
        $statements = array_filter(array_map('trim', explode(';', $schema)));
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                try {
                    $pdo->exec($statement);
                } catch (PDOException $e) {
                    // Ignore "already exists" errors
                    if (strpos($e->getMessage(), 'already exists') === false && 
                        strpos($e->getMessage(), 'Duplicate') === false) {
                        echo "<p class='warning'>⚠️ " . $e->getMessage() . "</p>";
                    }
                }
            }
        }
        echo "<p class='success'>✅ Database tables created successfully</p>";
    } else {
        echo "<p class='error'>❌ Schema file not found at: $schemaFile</p>";
    }
    
    // Create demo user
    $demoEmail = 'demo@tradingjournal.com';
    $demoPassword = password_hash('demo123', PASSWORD_DEFAULT);
    
    $checkUser = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $checkUser->execute([$demoEmail]);
    
    if (!$checkUser->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
        $stmt->execute(['Demo Trader', $demoEmail, $demoPassword]);
        $userId = $pdo->lastInsertId();
        
        // Create default account
        $pdo->prepare("INSERT INTO accounts (user_id, name, broker, type, currency, initial_balance, current_balance) VALUES (?, 'Demo Account', 'Trading Broker', 'LIVE', 'USD', 10000, 10000)")
            ->execute([$userId]);
        
        // Create default strategy
        $pdo->prepare("INSERT INTO strategies (user_id, name, description, color) VALUES (?, 'Breakout', 'Price breakout strategy', '#FFD700')")
            ->execute([$userId]);
        $pdo->prepare("INSERT INTO strategies (user_id, name, description, color) VALUES (?, 'Reversal', 'Mean reversion strategy', '#00D4FF')")
            ->execute([$userId]);
        
        // Create tags
        $tags = [['Scalp', '#00D4FF'], ['Swing', '#8B5CF6'], ['News', '#EF4444'], ['Technical', '#10B981']];
        foreach ($tags as $tag) {
            $pdo->prepare("INSERT INTO tags (user_id, name, color) VALUES (?, ?, ?)")
                ->execute([$userId, $tag[0], $tag[1]]);
        }
        
        // Create sample trades
        $strategies = $pdo->query("SELECT id FROM strategies WHERE user_id = $userId")->fetchAll(PDO::FETCH_COLUMN);
        
        $sampleTrades = [
            ['LONG', 2025.50, 2032.20, 2020.00, 2040.00, 2, 1340, 1260.50, 2.52, '-3 days'],
            ['SHORT', 2038.80, 2029.90, 2045.00, 2020.00, 1, 890, 855.50, 1.22, '-2 days'],
            ['LONG', 2012.20, 2005.50, 2008.00, 2020.00, 3, -2010, -2145.50, -5.36, '-1 day'],
            ['LONG', 2008.80, 2016.60, 2004.00, 2020.00, 2, 1560, 1555.50, 3.89, 'now'],
        ];
        
        foreach ($sampleTrades as $i => $t) {
            $strategyId = $strategies[$i % count($strategies)];
            $entryTime = date('Y-m-d H:i:s', strtotime($t[9]));
            $exitTime = date('Y-m-d H:i:s', strtotime($t[9] . ' +2 hours'));
            
            $pdo->prepare("
                INSERT INTO trades (user_id, instrument_id, strategy_id, direction, entry_price, exit_price, 
                                    stop_loss, take_profit, position_size, gross_pnl, net_pnl, r_multiple,
                                    entry_time, exit_time, status, entry_reason, setup_quality)
                VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'CLOSED', 'Sample trade for demo', 4)
            ")->execute([$userId, $strategyId, $t[0], $t[1], $t[2], $t[3], $t[4], $t[5], $t[6], $t[7], $t[8], $entryTime, $exitTime]);
        }
        
        echo "<p class='success'>✅ Demo user created with sample trades</p>";
    } else {
        echo "<p class='warning'>⚠️ Demo user already exists</p>";
    }
    
    // Check uploads directory
    $uploadsDir = __DIR__ . '/uploads/trades';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }
    
    if (is_writable($uploadsDir)) {
        echo "<p class='success'>✅ Uploads directory is writable</p>";
    } else {
        echo "<p class='error'>❌ Uploads directory is not writable. Please set permissions.</p>";
    }
    
    echo "<div class='box'>";
    echo "<h3>🎉 Setup Complete!</h3>";
    echo "<p>Your Trading Journal is ready to use.</p>";
    echo "<p><strong>Demo Login:</strong></p>";
    echo "<ul>";
    echo "<li>Email: <code>demo@tradingjournal.com</code></li>";
    echo "<li>Password: <code>demo123</code></li>";
    echo "</ul>";
    echo "<a href='index.php' class='btn'>Go to Homepage →</a>";
    echo " <a href='login.php' class='btn' style='margin-left: 10px;'>Login →</a>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div class='box'>";
    echo "<h3 class='error'>❌ Database Connection Error</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<p>Please make sure:</p>";
    echo "<ul>";
    echo "<li>MySQL is running in Laragon</li>";
    echo "<li>Database credentials are correct in <code>config/database.php</code></li>";
    echo "</ul>";
    echo "</div>";
}

echo "</div>";
