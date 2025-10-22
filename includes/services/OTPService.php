<?php
/**
 * OTP Service for Email Verification
 * 
 * Handles OTP generation, sending, verification, and resend functionality
 * with rate limiting and security features.
 * 
 * @package Mitsubishi\Services
 * @author Mitsubishi Motors Development Team
 * @date 2025-10-21
 */

namespace Mitsubishi\Services;

class OTPService
{
    private $pdo;
    private $mailer;
    
    // Configuration constants
    const OTP_LENGTH = 6;
    const OTP_EXPIRY_MINUTES = 10;
    const MAX_ATTEMPTS = 5;
    const MAX_RESENDS = 3;
    const RESEND_COOLDOWN_SECONDS = 60;
    
    /**
     * Constructor
     * 
     * @param \PDO $pdo Database connection
     */
    public function __construct($pdo = null)
    {
        // Use provided PDO or get from global connection
        if ($pdo) {
            $this->pdo = $pdo;
        } else {
            // Load database connection
            require_once dirname(dirname(__DIR__)) . '/includes/database/db_conn.php';
            global $connect;
            $this->pdo = $connect;
        }
        
        // Load GmailMailer
        require_once dirname(__DIR__) . '/backend/GmailMailer.php';
        $this->mailer = new \Mitsubishi\Backend\GmailMailer();
    }
    
    /**
     * Generate a cryptographically secure 6-digit OTP
     * 
     * @return string 6-digit OTP code
     */
    public function generateOTP()
    {
        try {
            // Generate random number between 100000 and 999999
            $otp = random_int(100000, 999999);
            return str_pad($otp, self::OTP_LENGTH, '0', STR_PAD_LEFT);
        } catch (\Exception $e) {
            // Fallback to mt_rand if random_int fails
            $otp = mt_rand(100000, 999999);
            return str_pad($otp, self::OTP_LENGTH, '0', STR_PAD_LEFT);
        }
    }
    
    /**
     * Hash OTP for secure storage
     * 
     * @param string $otp Plain OTP code
     * @return string Hashed OTP
     */
    private function hashOTP($otp)
    {
        return password_hash($otp, PASSWORD_DEFAULT);
    }
    
    /**
     * Verify OTP hash
     * 
     * @param string $otp Plain OTP code
     * @param string $hash Hashed OTP
     * @return bool True if OTP matches hash
     */
    private function verifyOTPHash($otp, $hash)
    {
        return password_verify($otp, $hash);
    }
    
    /**
     * Send OTP to user's email
     *
     * @param int $accountId Account ID
     * @param string $email User's email address
     * @param string $context Context for email template ('registration' or 'password_reset')
     * @return array ['success' => bool, 'message' => string, 'otp_id' => int|null]
     */
    public function sendOTP($accountId, $email, $context = 'registration')
    {
        try {
            // Generate OTP
            $otp = $this->generateOTP();
            $otpHash = $this->hashOTP($otp);

            // Calculate expiry time
            $expiresAt = date('Y-m-d H:i:s', strtotime('+' . self::OTP_EXPIRY_MINUTES . ' minutes'));

            // Get client information
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            // Invalidate any existing OTPs for this account
            $this->invalidateExistingOTPs($accountId);

            // Store OTP in database
            $stmt = $this->pdo->prepare("
                INSERT INTO email_verifications
                (account_id, email, otp_code, otp_hash, expires_at, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $accountId,
                $email,
                $otp, // Store plain OTP temporarily for email sending
                $otpHash,
                $expiresAt,
                $ipAddress,
                $userAgent
            ]);

            $otpId = $this->pdo->lastInsertId();

            // Send OTP email
            $emailResult = $this->sendOTPEmail($email, $otp, $context);

            if ($emailResult['success']) {
                return [
                    'success' => true,
                    'message' => 'OTP sent successfully to your email',
                    'otp_id' => $otpId
                ];
            } else {
                // Email sending failed, but OTP is stored
                return [
                    'success' => false,
                    'message' => 'Failed to send OTP email: ' . ($emailResult['error'] ?? 'Unknown error'),
                    'otp_id' => $otpId
                ];
            }

        } catch (\Exception $e) {
            error_log("OTP Send Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to generate OTP: ' . $e->getMessage(),
                'otp_id' => null
            ];
        }
    }
    
    /**
     * Send OTP email using email template
     *
     * @param string $email Recipient email
     * @param string $otp OTP code
     * @param string $context Context for email template ('registration' or 'password_reset')
     * @return array Email sending result
     */
    private function sendOTPEmail($email, $otp, $context = 'registration')
    {
        if ($context === 'password_reset') {
            // Load password reset email template
            require_once dirname(__DIR__) . '/email_templates/password_reset_otp.php';

            $subject = 'Password Reset Request - Mitsubishi Motors';
            $body = getPasswordResetOTPTemplate($otp, $email);
        } else {
            // Load registration email template
            require_once dirname(__DIR__) . '/email_templates/otp_verification.php';

            $subject = 'Verify Your Email - Mitsubishi Motors';
            $body = getOTPEmailTemplate($otp);
        }

        return $this->mailer->sendEmail($email, $subject, $body, ['is_html' => true]);
    }
    
    /**
     * Verify OTP code
     * 
     * @param int $accountId Account ID
     * @param string $otpCode OTP code to verify
     * @return array ['success' => bool, 'message' => string]
     */
    public function verifyOTP($accountId, $otpCode)
    {
        try {
            // Get the latest OTP for this account
            $stmt = $this->pdo->prepare("
                SELECT * FROM email_verifications 
                WHERE account_id = ? 
                AND is_used = 0 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$accountId]);
            $otpRecord = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$otpRecord) {
                return [
                    'success' => false,
                    'message' => 'No OTP found. Please request a new one.'
                ];
            }
            
            // Check if OTP has expired
            if (strtotime($otpRecord['expires_at']) < time()) {
                return [
                    'success' => false,
                    'message' => 'OTP has expired. Please request a new one.'
                ];
            }
            
            // Check if max attempts reached
            if ($otpRecord['attempts'] >= $otpRecord['max_attempts']) {
                return [
                    'success' => false,
                    'message' => 'Maximum verification attempts reached. Please request a new OTP.'
                ];
            }
            
            // Increment attempts
            $updateAttempts = $this->pdo->prepare("
                UPDATE email_verifications 
                SET attempts = attempts + 1 
                WHERE id = ?
            ");
            $updateAttempts->execute([$otpRecord['id']]);
            
            // Verify OTP
            if ($this->verifyOTPHash($otpCode, $otpRecord['otp_hash'])) {
                // Mark OTP as used
                $markUsed = $this->pdo->prepare("
                    UPDATE email_verifications 
                    SET is_used = 1, verified_at = NOW() 
                    WHERE id = ?
                ");
                $markUsed->execute([$otpRecord['id']]);
                
                // Mark account email as verified
                $markAccountVerified = $this->pdo->prepare("
                    UPDATE accounts 
                    SET email_verified = 1, email_verified_at = NOW() 
                    WHERE Id = ?
                ");
                $markAccountVerified->execute([$accountId]);
                
                return [
                    'success' => true,
                    'message' => 'Email verified successfully!'
                ];
            } else {
                $remainingAttempts = $otpRecord['max_attempts'] - ($otpRecord['attempts'] + 1);
                return [
                    'success' => false,
                    'message' => "Invalid OTP code. $remainingAttempts attempts remaining."
                ];
            }
            
        } catch (\Exception $e) {
            error_log("OTP Verify Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Verification failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Resend OTP with rate limiting
     *
     * @param int $accountId Account ID
     * @param string $email User's email
     * @param string $context Context for email template ('registration' or 'password_reset')
     * @return array ['success' => bool, 'message' => string]
     */
    public function resendOTP($accountId, $email, $context = 'registration')
    {
        try {
            error_log("DEBUG: resendOTP called for accountId=$accountId, email=$email, context=$context");

            // Get the latest ACTIVE (non-used) OTP record
            $stmt = $this->pdo->prepare("
                SELECT * FROM email_verifications
                WHERE account_id = ? AND is_used = 0
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$accountId]);
            $otpRecord = $stmt->fetch(\PDO::FETCH_ASSOC);

            error_log("DEBUG: OTP Record found: " . ($otpRecord ? "YES (ID: {$otpRecord['id']}, resend_count: {$otpRecord['resend_count']}, last_resend_at: {$otpRecord['last_resend_at']})" : "NO"));

            if ($otpRecord) {
                // Check resend count
                if ($otpRecord['resend_count'] >= self::MAX_RESENDS) {
                    error_log("DEBUG: Max resend limit reached");
                    return [
                        'success' => false,
                        'message' => 'Maximum resend limit reached. Please contact support.'
                    ];
                }

                // Check cooldown period
                if ($otpRecord['last_resend_at']) {
                    $lastResendTime = strtotime($otpRecord['last_resend_at']);
                    $cooldownRemaining = self::RESEND_COOLDOWN_SECONDS - (time() - $lastResendTime);

                    if ($cooldownRemaining > 0) {
                        error_log("DEBUG: Cooldown active, $cooldownRemaining seconds remaining");
                        return [
                            'success' => false,
                            'message' => "Please wait $cooldownRemaining seconds before requesting a new OTP."
                        ];
                    }
                }
            }

            // Generate and send new OTP
            error_log("DEBUG: Calling sendOTP...");
            $result = $this->sendOTP($accountId, $email, $context);
            error_log("DEBUG: sendOTP result: " . json_encode($result));

            // If successful, update resend count on the NEW OTP record
            if ($result['success'] && isset($result['otp_id'])) {
                $newResendCount = $otpRecord ? ($otpRecord['resend_count'] + 1) : 1;
                $updateResend = $this->pdo->prepare("
                    UPDATE email_verifications
                    SET resend_count = ?, last_resend_at = NOW()
                    WHERE id = ?
                ");
                $updateResend->execute([$newResendCount, $result['otp_id']]);
                error_log("DEBUG: Updated resend count to $newResendCount for new OTP record ID: {$result['otp_id']}");
            }

            return $result;

        } catch (\Exception $e) {
            error_log("OTP Resend Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to resend OTP: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Invalidate existing OTPs for an account
     * 
     * @param int $accountId Account ID
     */
    private function invalidateExistingOTPs($accountId)
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE email_verifications 
                SET is_used = 1 
                WHERE account_id = ? AND is_used = 0
            ");
            $stmt->execute([$accountId]);
        } catch (\Exception $e) {
            error_log("OTP Invalidate Error: " . $e->getMessage());
        }
    }
    
    /**
     * Cleanup expired OTPs (for cron job)
     * 
     * @return int Number of records deleted
     */
    public function cleanupExpiredOTPs()
    {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM email_verifications 
                WHERE expires_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (\Exception $e) {
            error_log("OTP Cleanup Error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Check if account email is verified
     * 
     * @param int $accountId Account ID
     * @return bool True if email is verified
     */
    public function isEmailVerified($accountId)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT email_verified FROM accounts WHERE Id = ?
            ");
            $stmt->execute([$accountId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            return $result && $result['email_verified'] == 1;
        } catch (\Exception $e) {
            error_log("Email Verification Check Error: " . $e->getMessage());
            return false;
        }
    }
}

