# Trading Journal

A luxury, futuristic trading journal system designed for serious traders. Track crude oil futures, analyze performance, and elevate your trading with stunning visualizations.

![Trading Journal](https://via.placeholder.com/800x400/0a0a0f/FFD700?text=Trading+Journal)

## ✨ Features

- **🎨 Luxury Futuristic Design** - Dark theme with gold/cyan accents, glassmorphism, and smooth animations
- **📊 Trade Journaling** - Full CRUD operations for trades with detailed fields
- **📸 Screenshot Upload** - Drag & drop image uploads with preview
- **📈 Advanced Analytics** - Equity curves, win rates, P&L by time, strategy performance
- **🛢️ Crude Oil Focus** - Pre-configured for CL, MCL, and BRN futures
- **🔐 Authentication** - Secure login/register with password hashing
- **📱 Responsive** - Works on desktop and mobile

## 🚀 Quick Start

### Prerequisites

- [Laragon](https://laragon.org/) (or any PHP 7.4+ environment)
- MySQL 5.7+
- Web browser

### Installation

1. **Clone or copy files** to your Laragon www directory:
   ```
   C:\laragon\www\TRADING-JOURNAL\
   ```

2. **Start Laragon** and ensure Apache and MySQL are running

3. **Run the setup script** by visiting:
   ```
   http://localhost/TRADING-JOURNAL/setup.php
   ```

4. **Login with demo account**:
   - Email: `demo@tradingjournal.com`
   - Password: `demo123`

5. **Start tracking your trades!**

## 📁 Project Structure

```
TRADING-JOURNAL/
├── api/
│   ├── auth/
│   │   ├── login.php
│   │   ├── logout.php
│   │   └── register.php
│   └── trades/
│       ├── create.php
│       ├── update.php
│       ├── delete.php
│       └── list.php
├── assets/
│   └── css/
│       └── style.css
├── config/
│   └── database.php
├── database/
│   └── schema.sql
├── uploads/
│   └── trades/
├── index.php          # Homepage
├── login.php          # Login page
├── register.php       # Registration page
├── dashboard.php      # Main dashboard
├── journal.php        # Trade journal list
├── analytics.php      # Analytics & charts
├── setup.php          # Setup script
└── README.md
```

## 🎯 Key Pages

| Page | Description |
|------|-------------|
| `/` | Luxury landing page with features |
| `/login.php` | User login |
| `/register.php` | New account registration |
| `/dashboard.php` | Main dashboard with stats |
| `/journal.php` | Full trade journal with filters |
| `/analytics.php` | Charts and performance analysis |

## 🛠️ Configuration

Edit `config/database.php` to update database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'trading_journal');
define('DB_USER', 'root');
define('DB_PASS', '');
```

## 📊 Trade Fields

Each trade tracks:
- **Instrument** (CL, MCL, BRN, etc.)
- **Direction** (Long/Short)
- **Entry/Exit prices**
- **Stop Loss & Take Profit**
- **Position size & Fees**
- **Entry/Exit times**
- **Strategy used**
- **Setup quality rating**
- **Screenshots**
- **Notes & lessons learned**
- **Automatic P&L & R-multiple calculation**

## 📈 Analytics Metrics

- Win Rate
- Profit Factor
- Expectancy
- Average R-Multiple
- Equity Curve
- P&L by Day of Week
- P&L by Trading Hour
- Performance by Instrument
- Performance by Strategy
- Win/Loss Streaks

## 🎨 Design System

### Colors
- **Primary Background**: `#0a0a0f`
- **Secondary Background**: `#12121a`
- **Gold Accent**: `#FFD700`
- **Cyan Accent**: `#00D4FF`
- **Green (Profit)**: `#10B981`
- **Red (Loss)**: `#EF4444`

### Components
- Glassmorphism cards
- Gradient buttons
- Custom form inputs
- Animated backgrounds
- Dark-themed charts

## 🔒 Security Features

- Password hashing with `password_hash()`
- Prepared statements for SQL
- Session-based authentication
- Input sanitization
- File type validation for uploads

## 📝 License

This project is for personal use. Customize and extend as needed for your trading needs.

## 🤝 Support

For issues or feature requests, please open an issue in the repository.

---

**Happy Trading! 📈**
