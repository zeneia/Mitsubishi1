<?php
// Include the session initialization file
include_once(dirname(__DIR__) . '/includes/init.php');

// Set content type to JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST requests allowed']);
    exit();
}

// Initialize database connection
$pdo = $GLOBALS['pdo'] ?? null;

if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$user_name = $_SESSION['name'] ?? 'User';

try {
    // Get POST data
    $recipient = trim($_POST['recipient'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $email_type = $_POST['email_type'] ?? 'general';
    $priority = $_POST['priority'] ?? 'normal';
    $save_template = isset($_POST['save_template']) ? 1 : 0;
    
    // Validation
    if (empty($recipient) || empty($subject) || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
        exit();
    }
    
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address format']);
        exit();
    }
    
    // Check if email_logs table exists, create if not
    createEmailLogsTable($pdo);
    
    // Prepare email content
    $email_content = [
        'to' => $recipient,
        'subject' => $subject,
        'message' => $message,
        'type' => $email_type,
        'priority' => $priority,
        'sender' => $user_name,
        'sender_id' => $user_id
    ];
    
    // Send email using your preferred method
    $email_sent = sendEmail($email_content);
    
    if ($email_sent['success']) {
        // Log successful email
        $log_sql = "INSERT INTO email_logs (sender_id, sender_name, recipient, subject, message, email_type, priority, sent_at, status, delivery_status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'sent', 'delivered')";
        
        $stmt = $pdo->prepare($log_sql);
        $stmt->execute([
            $user_id,
            $user_name,
            $recipient,
            $subject,
            $message,
            $email_type,
            $priority
        ]);
        
        $email_log_id = $pdo->lastInsertId();
        
        // Save as template if requested
        if ($save_template) {
            saveEmailTemplate($pdo, $user_id, $subject, $message, $email_type);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Email sent successfully to ' . $recipient,
            'email_id' => $email_log_id
        ]);
        
    } else {
        // Log failed email
        $log_sql = "INSERT INTO email_logs (sender_id, sender_name, recipient, subject, message, email_type, priority, sent_at, status, delivery_status, error_message) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'failed', 'failed', ?)";
        
        $stmt = $pdo->prepare($log_sql);
        $stmt->execute([
            $user_id,
            $user_name,
            $recipient,
            $subject,
            $message,
            $email_type,
            $priority,
            $email_sent['error'] ?? 'Unknown error'
        ]);
        
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send email: ' . ($email_sent['error'] ?? 'Unknown error')
        ]);
    }
    
} catch (Exception $e) {
    error_log("Email API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Server error occurred while sending email'
    ]);
}

/**
 * Create email_logs table if it doesn't exist
 */
function createEmailLogsTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS email_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        sender_name VARCHAR(255) NOT NULL,
        recipient VARCHAR(255) NOT NULL,
        subject VARCHAR(500) NOT NULL,
        message TEXT NOT NULL,
        email_type VARCHAR(100) DEFAULT 'general',
        priority VARCHAR(20) DEFAULT 'normal',
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
        delivery_status ENUM('delivered', 'failed', 'pending') DEFAULT 'pending',
        error_message TEXT NULL,
        opened_at TIMESTAMP NULL,
        clicked_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_sender_id (sender_id),
        INDEX idx_recipient (recipient),
        INDEX idx_sent_at (sent_at),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
}

/**
 * Send email function using Gmail SMTP
 * Updated to use PHPMailer with Gmail SMTP instead of Mailgun
 * No Composer required - PHPMailer is included directly
 */
function sendEmail($email_content) {
    try {
        // Load GmailMailer (includes PHPMailer automatically)
        require_once dirname(__DIR__) . '/includes/backend/GmailMailer.php';

        // Use Gmail SMTP to send email
        return sendEmailWithGmail($email_content);

    } catch (Exception $e) {
        error_log("Email sending error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Send email using Gmail SMTP via PHPMailer
 */
function sendEmailWithGmail($email_content) {
    try {
        $mailer = new \Mitsubishi\Backend\GmailMailer();

        // Prepare options
        $options = [
            'priority' => $email_content['priority'] ?? 'normal',
            'is_html' => true
        ];

        // Add CC if specified
        if (isset($email_content['cc'])) {
            $options['cc'] = $email_content['cc'];
        }

        // Add BCC if specified
        if (isset($email_content['bcc'])) {
            $options['bcc'] = $email_content['bcc'];
        }

        // Send email
        $result = $mailer->sendEmail(
            $email_content['to'],
            $email_content['subject'],
            $email_content['message'],
            $options
        );

        return $result;

    } catch (Exception $e) {
        error_log("Gmail SMTP Error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Failed to send email via Gmail SMTP: ' . $e->getMessage()
        ];
    }
}

/**
 * Send email using Mailgun HTTP API
 */
function sendEmailWithMailgun($email_content) {
    global $mailgun_config;
    
    // Default configuration if not set
    if (!isset($mailgun_config)) {
        $mailgun_config = [
            'api_key' => 'your-mailgun-api-key',
            'domain' => 'your-domain.mailgun.org',
            'from_email' => 'noreply@mitsubishi-motors.com',
            'from_name' => 'Mitsubishi Motors'
        ];
    }
    
    // Prepare email data
    $to = $email_content['to'];
    $subject = $email_content['subject'];
    $message = $email_content['message'];
    
    // Format message as HTML
    $html_message = formatEmailMessage($message, $email_content);
    
    // Mailgun API endpoint
    $url = "https://api.mailgun.net/v3/{$mailgun_config['domain']}/messages";
    
    // Prepare POST data
    $post_data = [
        'from' => "{$mailgun_config['from_name']} <{$mailgun_config['from_email']}>",
        'to' => $to,
        'subject' => $subject,
        'html' => $html_message,
        'o:tag' => $email_content['type'] ?? 'general',
        'o:tracking' => 'yes',
        'o:tracking-clicks' => 'yes',
        'o:tracking-opens' => 'yes'
    ];
    
    // Set priority if specified
    if (isset($email_content['priority']) && in_array($email_content['priority'], ['high', 'urgent'])) {
        $post_data['h:X-Priority'] = $email_content['priority'] === 'urgent' ? '1' : '2';
    }
    
    // Initialize cURL
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($post_data),
        CURLOPT_USERPWD => "api:{$mailgun_config['api_key']}",
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded'
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);
    
    // Execute request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    
    curl_close($ch);
    
    // Handle cURL errors
    if ($curl_error) {
        return ['success' => false, 'error' => 'cURL Error: ' . $curl_error];
    }
    
    // Parse response
    $response_data = json_decode($response, true);
    
    // Check if email was sent successfully
    if ($http_code === 200 && isset($response_data['id'])) {
        return [
            'success' => true,
            'message_id' => $response_data['id'],
            'message' => $response_data['message'] ?? 'Email queued successfully'
        ];
    } else {
        $error_message = 'Failed to send email';
        
        if (isset($response_data['message'])) {
            $error_message = $response_data['message'];
        } elseif ($http_code !== 200) {
            $error_message = "HTTP Error: {$http_code}";
        }
        
        return ['success' => false, 'error' => $error_message];
    }
}

/**
 * Format email message as HTML
 */
function formatEmailMessage($message, $email_content) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($email_content['subject']) . '</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .email-container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #dc143c, #b91c3c); color: white; padding: 20px; text-align: center; }
            .content { background: #f9f9f9; padding: 30px; }
            .footer { background: #333; color: white; padding: 20px; text-align: center; font-size: 12px; }
            .logo { max-width: 150px; }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="header">
                <h1>Mitsubishi Motors</h1>
                <p>Excellence in Motion</p>
            </div>
            <div class="content">
                ' . nl2br(htmlspecialchars($message)) . '
            </div>
            <div class="footer">
                <p>This email was sent from Mitsubishi Motors Customer Service</p>
                <p>If you have any questions, please contact us at support@mitsubishi-motors.com</p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Save email template
 */
function saveEmailTemplate($pdo, $user_id, $subject, $message, $email_type) {
    try {
        // Create email_templates table if it doesn't exist
        $sql = "CREATE TABLE IF NOT EXISTS email_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            template_name VARCHAR(255) NOT NULL,
            subject VARCHAR(500) NOT NULL,
            message TEXT NOT NULL,
            email_type VARCHAR(100) NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_email_type (email_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        
        // Save template
        $template_name = $email_type . ' - ' . date('Y-m-d H:i:s');
        
        $insert_sql = "INSERT INTO email_templates (user_id, template_name, subject, message, email_type) 
                       VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($insert_sql);
        $stmt->execute([$user_id, $template_name, $subject, $message, $email_type]);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Template save error: " . $e->getMessage());
        return false;
    }
}
?>