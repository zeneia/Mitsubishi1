<?php
/**
 * Gmail SMTP Mailer
 * 
 * Handles email sending via Gmail SMTP using PHPMailer
 * Configured to work with Gmail's SMTP service
 */

namespace Mitsubishi\Backend;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class GmailMailer
{
    private $mailer;
    private $fromEmail;
    private $fromName;
    private $smtpHost;
    private $smtpPort;
    private $smtpUsername;
    private $smtpPassword;
    private $smtpEncryption;
    
    /**
     * Constructor - Initialize PHPMailer with Gmail SMTP settings
     */
    public function __construct()
    {
        // Load PHPMailer (no Composer needed!)
        require_once dirname(__DIR__) . '/phpmailer/PHPMailer.php';

        // Load environment variables
        require_once dirname(__DIR__) . '/utils/EnvLoader.php';
        \Mitsubishi\Utils\EnvLoader::load();
        
        // Get configuration from environment
        $this->fromEmail = getenv('GMAIL_FROM_EMAIL') ?: getenv('GMAIL_EMAIL');
        $this->fromName = getenv('GMAIL_FROM_NAME') ?: 'Mitsubishi Motors';
        $this->smtpHost = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
        $this->smtpPort = getenv('SMTP_PORT') ?: 587;
        $this->smtpUsername = getenv('GMAIL_EMAIL');
        $this->smtpPassword = getenv('GMAIL_PASSWORD');
        $this->smtpEncryption = getenv('SMTP_ENCRYPTION') ?: 'tls';
        
        // Validate required configuration
        if (empty($this->smtpUsername) || empty($this->smtpPassword)) {
            throw new \Exception('Gmail SMTP credentials not configured. Please check your .env file.');
        }
        
        // Initialize PHPMailer
        $this->mailer = new PHPMailer(true);
        $this->configureSMTP();
    }
    
    /**
     * Configure SMTP settings
     */
    private function configureSMTP()
    {
        try {
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->smtpHost;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->smtpUsername;
            $this->mailer->Password = $this->smtpPassword;
            $this->mailer->SMTPSecure = $this->smtpEncryption === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port = $this->smtpPort;
            
            // Enable verbose debug output (disable in production)
            if (getenv('APP_DEBUG') === 'true') {
                $this->mailer->SMTPDebug = SMTP::DEBUG_OFF; // Change to DEBUG_SERVER for troubleshooting
            }
            
            // Content settings
            $this->mailer->isHTML(true);
            $this->mailer->CharSet = 'UTF-8';
            
            // Set default from address
            $this->mailer->setFrom($this->fromEmail, $this->fromName);
            
        } catch (Exception $e) {
            error_log("GmailMailer SMTP Configuration Error: " . $e->getMessage());
            throw new \Exception("Failed to configure SMTP: " . $e->getMessage());
        }
    }
    
    /**
     * Send email
     * 
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $body Email body (HTML or plain text)
     * @param array $options Additional options (cc, bcc, attachments, priority, etc.)
     * @return array ['success' => bool, 'message' => string, 'message_id' => string|null, 'error' => string|null]
     */
    public function sendEmail($to, $subject, $body, $options = [])
    {
        try {
            // Clear any previous recipients
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            $this->mailer->clearCCs();
            $this->mailer->clearBCCs();
            $this->mailer->clearReplyTos();
            
            // Set recipient
            $this->mailer->addAddress($to);
            
            // Set subject
            $this->mailer->Subject = $subject;
            
            // Set body (check if HTML or plain text)
            if (isset($options['is_html']) && $options['is_html'] === false) {
                $this->mailer->isHTML(false);
                $this->mailer->Body = $body;
            } else {
                $this->mailer->isHTML(true);
                $this->mailer->Body = $this->formatHTMLBody($body, $subject);
                $this->mailer->AltBody = strip_tags($body);
            }
            
            // Handle CC
            if (isset($options['cc'])) {
                $ccList = is_array($options['cc']) ? $options['cc'] : [$options['cc']];
                foreach ($ccList as $cc) {
                    $this->mailer->addCC($cc);
                }
            }
            
            // Handle BCC
            if (isset($options['bcc'])) {
                $bccList = is_array($options['bcc']) ? $options['bcc'] : [$options['bcc']];
                foreach ($bccList as $bcc) {
                    $this->mailer->addBCC($bcc);
                }
            }
            
            // Handle Reply-To
            if (isset($options['reply_to'])) {
                $this->mailer->addReplyTo($options['reply_to']);
            }
            
            // Handle attachments
            if (isset($options['attachments']) && is_array($options['attachments'])) {
                foreach ($options['attachments'] as $attachment) {
                    if (is_array($attachment)) {
                        $this->mailer->addAttachment($attachment['path'], $attachment['name'] ?? '');
                    } else {
                        $this->mailer->addAttachment($attachment);
                    }
                }
            }
            
            // Set priority
            if (isset($options['priority'])) {
                switch ($options['priority']) {
                    case 'high':
                    case 'urgent':
                        $this->mailer->Priority = 1;
                        $this->mailer->addCustomHeader('X-Priority', '1');
                        $this->mailer->addCustomHeader('Importance', 'High');
                        break;
                    case 'low':
                        $this->mailer->Priority = 5;
                        $this->mailer->addCustomHeader('X-Priority', '5');
                        $this->mailer->addCustomHeader('Importance', 'Low');
                        break;
                    default:
                        $this->mailer->Priority = 3;
                }
            }
            
            // Send email
            $result = $this->mailer->send();
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Email sent successfully',
                    'message_id' => $this->mailer->getLastMessageID(),
                    'error' => null
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to send email',
                    'message_id' => null,
                    'error' => 'Unknown error occurred'
                ];
            }
            
        } catch (Exception $e) {
            error_log("GmailMailer Send Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send email',
                'message_id' => null,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Format email body as HTML with Mitsubishi branding
     * 
     * @param string $message Message content
     * @param string $subject Email subject
     * @return string Formatted HTML
     */
    private function formatHTMLBody($message, $subject)
    {
        // If message already contains HTML tags, use it as-is
        if (preg_match('/<html|<body|<div/i', $message)) {
            return $message;
        }
        
        // Otherwise, wrap in branded template
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . htmlspecialchars($subject) . '</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    line-height: 1.6; 
                    color: #333; 
                    margin: 0;
                    padding: 0;
                    background-color: #f4f4f4;
                }
                .email-container { 
                    max-width: 600px; 
                    margin: 20px auto; 
                    background: #ffffff;
                    border-radius: 8px;
                    overflow: hidden;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                .header { 
                    background: linear-gradient(135deg, #dc143c, #b91c3c); 
                    color: white; 
                    padding: 30px 20px; 
                    text-align: center; 
                }
                .header h1 {
                    margin: 0;
                    font-size: 28px;
                    font-weight: bold;
                }
                .header p {
                    margin: 5px 0 0 0;
                    font-size: 14px;
                    opacity: 0.9;
                }
                .content { 
                    background: #ffffff; 
                    padding: 30px; 
                    font-size: 15px;
                }
                .footer { 
                    background: #333; 
                    color: white; 
                    padding: 20px; 
                    text-align: center; 
                    font-size: 12px; 
                }
                .footer p {
                    margin: 5px 0;
                }
                .logo { 
                    max-width: 150px; 
                }
            </style>
        </head>
        <body>
            <div class="email-container">
                <div class="header">
                    <h1>🚗 Mitsubishi Motors</h1>
                    <p>Excellence in Motion</p>
                </div>
                <div class="content">
                    ' . nl2br(htmlspecialchars($message)) . '
                </div>
                <div class="footer">
                    <p>This email was sent from Mitsubishi Motors Customer Service</p>
                    <p>If you have any questions, please contact us at mitsubishiautoxpress@gmail.com</p>
                    <p style="margin-top: 15px; opacity: 0.7;">© ' . date('Y') . ' Mitsubishi Motors. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    /**
     * Test SMTP connection
     * 
     * @return array ['success' => bool, 'message' => string]
     */
    public function testConnection()
    {
        try {
            $this->mailer->smtpConnect();
            $this->mailer->smtpClose();
            
            return [
                'success' => true,
                'message' => 'SMTP connection successful'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'SMTP connection failed: ' . $e->getMessage()
            ];
        }
    }
}

