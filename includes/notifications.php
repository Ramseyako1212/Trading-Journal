<?php
/**
 * Notification System
 * 
 * SETUP INSTRUCTIONS:
 * 1. To use Gmail: You need to configure Laragon's Mail Sender OR use PHPMailer.
 * 2. If using Gmail, you MUST use an "App Password" (not your main password).
 *    Go to: Google Account -> Security -> 2-Step Verification -> App Passwords.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// SMTP Configuration from environment variables
if (!defined('SMTP_USER')) define('SMTP_USER', getenv('SMTP_USER') ?: '');
if (!defined('SMTP_PASS')) define('SMTP_PASS', getenv('SMTP_PASS') ?: ''); 

/**
 * Send an email notification
 */
function sendEmailNotification($userId, $subject, $message) {
    if (empty(SMTP_USER) || SMTP_USER === 'YourEmail@gmail.com') {
        return false;
    }

    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        return false;
    }

    $to = $user['email'];
    $name = $user['name'];

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom(SMTP_USER, 'Trading Journal');
        $mail->addAddress($to, $name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        
        // Inspirational Messages
        $quotes = [
            "The market is a device for transferring money from the impatient to the patient. — Warren Buffett",
            "Losses are just tuition for your future success. Stay the course.",
            "Your discipline today is your profit tomorrow. Keep going.",
            "Trading is 10% execution and 90% patience. Master your mind.",
            "One bad trade doesn't make a bad trader. A lack of discipline does.",
            "The best traders have the best risk management, not the most wins.",
            "Every loss is a lesson. Analyze, adapt, and move forward with confidence.",
            "Success in trading is a marathon, not a sprint. Pace yourself.",
            "Stay humble when you win, and stay resilient when you lose.",
            "Your plan is your edge. Don't let emotions override your strategy."
        ];
        $inspirationalQuote = $quotes[array_rand($quotes)];

        // Premium Dark Theme HTML
        $htmlContent = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { margin: 0; padding: 0; background-color: #0a0a0f; font-family: 'Segoe UI', Helvetica, Arial, sans-serif; color: #ffffff; }
                .email-wrapper { background-color: #0a0a0f; width: 100%; padding: 40px 0; }
                .container { max-width: 600px; margin: 0 auto; background: #12121a; border: 1px solid rgba(255, 215, 0, 0.2); border-radius: 12px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.5); }
                .header { background: linear-gradient(135deg, #FFD700 0%, #B8860B 100%); padding: 30px; text-align: center; }
                .header h1 { margin: 0; color: #0a0a0f; font-size: 24px; text-transform: uppercase; letter-spacing: 2px; font-weight: 800; }
                .content { padding: 40px; line-height: 1.8; font-size: 16px; color: rgba(255,255,255,0.9); }
                .stats-panel { background: rgba(255,255,255,0.03); border: 1px solid rgba(0, 212, 255, 0.2); border-radius: 8px; padding: 20px; margin: 20px 0; }
                .quote-box { border-left: 4px solid #00D4FF; padding: 15px 25px; margin: 30px 0; background: rgba(0, 212, 255, 0.05); font-style: italic; color: #00D4FF; }
                .footer { background: rgba(255,255,255,0.02); padding: 20px; text-align: center; font-size: 12px; color: rgba(255,255,255,0.4); border-top: 1px solid rgba(255,255,255,0.05); }
                .btn { display: inline-block; padding: 12px 30px; background: #FFD700; color: #0a0a0f; text-decoration: none; border-radius: 6px; font-weight: bold; margin-top: 20px; transition: transform 0.2s; }
                a { color: #00D4FF; text-decoration: none; }
            </style>
        </head>
        <body>
            <div class='email-wrapper'>
                <div class='container'>
                    <div class='header'>
                        <h1>Trading Journal</h1>
                    </div>
                    <div class='content'>
                        <h2 style='color: #FFD700; margin-top: 0;'>Notification</h2>
                        <p>Hello $name,</p>
                        <div class='stats-panel'>
                            $message
                        </div>
                        
                        <div class='quote-box'>
                            \"$inspirationalQuote\"
                        </div>

                        <p>Remember, the best investment you can make is in your own discipline. Keep refining your edge and trust your process.</p>
                        
                        <center>
                            <a href='http://localhost/TRADING-JOURNAL/' class='btn'>View Dashboard</a>
                        </center>
                    </div>
                    <div class='footer'>
                        &copy; 2026 Trading Journal - Premium Analytics & Performance Tracking<br>
                        This is an automated notification. Manage your alerts in <a href='http://localhost/TRADING-JOURNAL/settings.php'>Settings</a>.
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->Body = $htmlContent;
        $mail->AltBody = strip_tags($message);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Add a system/in-app notification
 */
function addSystemNotification($userId, $title, $message, $type = 'info') {
    $pdo = getConnection();
    $stmt = $pdo->prepare("INSERT INTO system_notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$userId, $title, $message, $type]);
}

/**
 * Handle Trade Closed Notification
 */
function notifyTradeClosed($userId, $tradeData) {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT notify_on_trade_close FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $notify = $stmt->fetchColumn();

    $pnl = number_format($tradeData['net_pnl'], 2);
    $direction = $tradeData['direction'];
    $instrument = $tradeData['instrument'] ?? 'Unknown Instrument';
    
    $subject = "Trade Closed: $instrument ($direction)";
    $message = "Your trade on <strong>$instrument</strong> has been closed.<br><br>";
    $message .= "<strong>Direction:</strong> $direction<br>";
    $message .= "<strong>Net P&L:</strong> $" . $pnl . "<br>";
    $message .= "<strong>Exit Time:</strong> " . $tradeData['exit_time'] . "<br>";

    // In-App Notification (Always save to app even if email notify is off)
    $type = $tradeData['net_pnl'] >= 0 ? 'success' : 'danger';
    addSystemNotification($userId, $subject, strip_tags($message), $type);

    if (!$notify) return;

    sendEmailNotification($userId, $subject, $message);
}

/**
 * Handle Overtrading Notification
 */
function notifyOvertrading($userId, $currentCount, $limit) {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT notify_on_overtrading FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $notify = $stmt->fetchColumn();

    $subject = "Overtrading Alert!";
    $message = "You have reached your daily trade limit.<br><br>";
    $message .= "<strong>Daily Limit:</strong> $limit trades<br>";
    $message .= "<strong>Current Count:</strong> $currentCount trades today.<br><br>";
    $message .= "Discipline is key to long-term success. Take a break and review your trades!";

    // In-App Notification
    addSystemNotification($userId, $subject, strip_tags($message), 'warning');

    if (!$notify) return;

    sendEmailNotification($userId, $subject, $message);
}
