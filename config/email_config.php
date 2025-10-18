<?php
/**
 * Email Configuration File
 *
 * UPDATED: Now using Gmail SMTP instead of Mailgun
 * Configuration is loaded from .env file for security
 */

// Load environment variables
require_once dirname(__DIR__) . '/includes/utils/EnvLoader.php';
\Mitsubishi\Utils\EnvLoader::load();

// Gmail SMTP configuration (loaded from .env)
$gmail_config = [
    'smtp_host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
    'smtp_port' => getenv('SMTP_PORT') ?: 587,
    'smtp_encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls',
    'smtp_auth' => getenv('SMTP_AUTH') ?: true,
    'username' => getenv('GMAIL_EMAIL'),
    'password' => getenv('GMAIL_PASSWORD'),
    'from_email' => getenv('GMAIL_FROM_EMAIL') ?: getenv('GMAIL_EMAIL'),
    'from_name' => getenv('GMAIL_FROM_NAME') ?: 'Mitsubishi Motors'
];

// Legacy Mailgun configuration (deprecated - kept for backward compatibility)
$mailgun_config = [
    'api_key' => 'deprecated',
    'domain' => 'deprecated',
    'from_email' => $gmail_config['from_email'],
    'from_name' => $gmail_config['from_name']
];

/**
 * Gmail SMTP Setup Instructions:
 *
 * 1. Enable 2-Step Verification on your Gmail account
 *    - Go to https://myaccount.google.com/security
 *    - Enable 2-Step Verification
 *
 * 2. Generate an App Password
 *    - Go to https://myaccount.google.com/apppasswords
 *    - Select "Mail" and your device
 *    - Copy the 16-character password
 *
 * 3. Update your .env file with:
 *    GMAIL_EMAIL=your-email@gmail.com
 *    GMAIL_PASSWORD=your-app-password
 *    GMAIL_FROM_NAME=Your Company Name
 *
 * 4. Gmail SMTP Settings:
 *    - Host: smtp.gmail.com
 *    - Port: 587 (TLS) or 465 (SSL)
 *    - Encryption: TLS or SSL
 *    - Authentication: Required
 *
 * 5. Sending Limits:
 *    - Free Gmail: 500 emails/day
 *    - Google Workspace: 2000 emails/day
 */
?>