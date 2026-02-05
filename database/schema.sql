-- Trading Journal Database Schema
-- Run this in phpMyAdmin or MySQL CLI

CREATE DATABASE IF NOT EXISTS trading_journal;
USE trading_journal;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    timezone VARCHAR(50) DEFAULT 'UTC',
    api_key VARCHAR(64) UNIQUE DEFAULT NULL,
    daily_trade_limit INT DEFAULT 2,
    notify_on_trade_close BOOLEAN DEFAULT 1,
    notify_on_overtrading BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Trading accounts
CREATE TABLE IF NOT EXISTS accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    broker VARCHAR(100),
    type ENUM('LIVE', 'PAPER') DEFAULT 'LIVE',
    currency VARCHAR(10) DEFAULT 'USD',
    initial_balance DECIMAL(15,2) DEFAULT 0,
    current_balance DECIMAL(15,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Instruments (CL = Crude Oil, MCL = Micro Crude, etc.)
CREATE TABLE IF NOT EXISTS instruments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(50) DEFAULT 'Futures',
    tick_size DECIMAL(10,5) DEFAULT 0.01,
    tick_value DECIMAL(10,2) DEFAULT 10.00,
    margin_required DECIMAL(15,2) DEFAULT 0,
    session_times VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default instruments
INSERT INTO instruments (code, name, type, tick_size, tick_value, session_times) VALUES
('BTC', 'Bitcoin', 'Crypto', 1.00, 1.00, '24/7'),
('XAUUSD', 'XAU/USD (Gold)', 'Spot', 0.01, 1.00, 'Sun-Fri 6:00 PM - 5:00 PM ET'),
('EURUSD', 'EUR/USD', 'Forex', 0.0001, 10.00, 'Sun-Fri 5:00 PM - 5:00 PM ET'),
('GBPUSD', 'GBP/USD', 'Forex', 0.0001, 10.00, 'Sun-Fri 5:00 PM - 5:00 PM ET'),
('USDCHF', 'USD/CHF', 'Forex', 0.0001, 10.00, 'Sun-Fri 5:00 PM - 5:00 PM ET'),
('USDCAD', 'USD/CAD', 'Forex', 0.0001, 10.00, 'Sun-Fri 5:00 PM - 5:00 PM ET'),
('AUDUSD', 'AUD/USD', 'Forex', 0.0001, 10.00, 'Sun-Fri 5:00 PM - 5:00 PM ET'),
('NZDUSD', 'NZD/USD', 'Forex', 0.0001, 10.00, 'Sun-Fri 5:00 PM - 5:00 PM ET'),
('USDJPY', 'USD/JPY', 'Forex', 0.01, 6.50, 'Sun-Fri 5:00 PM - 5:00 PM ET');

-- Trading strategies
CREATE TABLE IF NOT EXISTS strategies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    rules TEXT,
    risk_profile VARCHAR(50) DEFAULT 'Medium',
    color VARCHAR(20) DEFAULT '#FFD700',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tags for categorizing trades
CREATE TABLE IF NOT EXISTS tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    color VARCHAR(20) DEFAULT '#00D4FF',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Daily pre-trade checklist
CREATE TABLE IF NOT EXISTS daily_checklists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    check_date DATE NOT NULL,
    score_percentage DECIMAL(5,2) NOT NULL,
    passed TINYINT(1) DEFAULT 0,
    responses TEXT, -- JSON blob of answers
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (user_id, check_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Customizable rules for checklist
CREATE TABLE IF NOT EXISTS user_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    rule_text VARCHAR(255) NOT NULL,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Default rules for new users
-- Note: These would normally be inserted via PHP during registration, 
-- but we'll add them here for the current setup.
-- INSERT INTO user_rules (user_id, rule_text, display_order) VALUES ...

-- Main trades table
CREATE TABLE IF NOT EXISTS trades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    account_id INT,
    instrument_id INT,
    strategy_id INT,
    
    -- Trade details
    direction ENUM('LONG', 'SHORT') NOT NULL,
    entry_price DECIMAL(15,5) NOT NULL,
    exit_price DECIMAL(15,5),
    stop_loss DECIMAL(15,5),
    take_profit DECIMAL(15,5),
    position_size DECIMAL(10,2) DEFAULT 1,
    
    -- Timing
    entry_time DATETIME NOT NULL,
    exit_time DATETIME,
    
    -- Results
    gross_pnl DECIMAL(15,2) DEFAULT 0,
    fees DECIMAL(10,2) DEFAULT 0,
    net_pnl DECIMAL(15,2) DEFAULT 0,
    r_multiple DECIMAL(5,2) DEFAULT 0,
    slippage DECIMAL(10,5) DEFAULT 0,
    
    -- Evaluation
    setup_quality TINYINT DEFAULT 0, -- 1-5 rating
    execution_quality TINYINT DEFAULT 0, -- 1-5 rating
    followed_rules BOOLEAN DEFAULT TRUE,
    emotional_state VARCHAR(50),
    confidence_level TINYINT DEFAULT 3, -- 1-5
    
    -- Notes
    entry_reason TEXT,
    exit_reason TEXT,
    lessons_learned TEXT,
    notes TEXT,
    
    -- Meta
    status ENUM('OPEN', 'CLOSED', 'CANCELLED') DEFAULT 'OPEN',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (instrument_id) REFERENCES instruments(id) ON DELETE SET NULL,
    FOREIGN KEY (strategy_id) REFERENCES strategies(id) ON DELETE SET NULL,
    
    INDEX idx_user_date (user_id, entry_time),
    INDEX idx_instrument (instrument_id),
    INDEX idx_status (status)
);

-- Trade-tag junction table (many-to-many)
CREATE TABLE IF NOT EXISTS trade_tags (
    trade_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (trade_id, tag_id),
    FOREIGN KEY (trade_id) REFERENCES trades(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
);

-- Trade attachments (screenshots, charts, etc.)
CREATE TABLE IF NOT EXISTS attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trade_id INT NOT NULL,
    user_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(50),
    file_size INT DEFAULT 0,
    caption TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trade_id) REFERENCES trades(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Journal entries (daily reflections)
CREATE TABLE IF NOT EXISTS journal_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    trade_id INT,
    entry_date DATE NOT NULL,
    title VARCHAR(255),
    content TEXT NOT NULL,
    mood VARCHAR(50),
    market_conditions TEXT,
    goals_met BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (trade_id) REFERENCES trades(id) ON DELETE SET NULL
);

-- Daily performance summary (cached calculations)
CREATE TABLE IF NOT EXISTS daily_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    stat_date DATE NOT NULL,
    total_trades INT DEFAULT 0,
    winning_trades INT DEFAULT 0,
    losing_trades INT DEFAULT 0,
    gross_pnl DECIMAL(15,2) DEFAULT 0,
    net_pnl DECIMAL(15,2) DEFAULT 0,
    win_rate DECIMAL(5,2) DEFAULT 0,
    avg_r DECIMAL(5,2) DEFAULT 0,
    max_drawdown DECIMAL(15,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_date (user_id, stat_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create uploads directory reminder
-- Make sure to create: /uploads/trades/ directory with write permissions
