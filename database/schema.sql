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
    code VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(50) DEFAULT 'Futures',
    tick_size DECIMAL(10,5) DEFAULT 0.01,
    tick_value DECIMAL(10,2) DEFAULT 10.00,
    margin_required DECIMAL(15,2) DEFAULT 0,
    session_times VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default instruments (Forex and Gold)
INSERT INTO instruments (code, name, type, tick_size, tick_value, session_times) VALUES
('XAUUSD', 'Gold / US Dollar', 'Spot', 0.01, 1.00, 'Sun-Fri 6:00 PM - 5:00 PM ET'),
('EURUSD', 'Euro / US Dollar', 'Forex', 0.0001, 10.00, 'Sun-Fri 5:00 PM - 5:00 PM ET'),
('GBPUSD', 'British Pound / US Dollar', 'Forex', 0.0001, 10.00, 'Sun-Fri 5:00 PM - 5:00 PM ET'),
('USDJPY', 'US Dollar / Japanese Yen', 'Forex', 0.01, 6.50, 'Sun-Fri 5:00 PM - 5:00 PM ET');

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
