<?php
/**
 * OTP Email Verification Template
 * 
 * Branded email template for sending OTP codes to users
 * 
 * @package Mitsubishi\EmailTemplates
 * @author Mitsubishi Motors Development Team
 * @date 2025-10-21
 */

/**
 * Get OTP email template
 * 
 * @param string $otp 6-digit OTP code
 * @return string HTML email content
 */
function getOTPEmailTemplate($otp)
{
    $html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Email Verification - Mitsubishi Motors</title>
        <style>
            body {
                margin: 0;
                padding: 0;
                font-family: "Segoe UI", Arial, sans-serif;
                background-color: #f4f4f4;
            }
            .email-container {
                max-width: 600px;
                margin: 0 auto;
                background-color: #ffffff;
            }
            .header {
                background: linear-gradient(135deg, #d60000 0%, #b30000 100%);
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
                padding: 40px 30px;
                background-color: #ffffff;
            }
            .welcome-text {
                font-size: 18px;
                color: #333;
                margin-bottom: 20px;
            }
            .otp-container {
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                border: 2px dashed #d60000;
                border-radius: 10px;
                padding: 30px;
                text-align: center;
                margin: 30px 0;
            }
            .otp-label {
                font-size: 14px;
                color: #666;
                margin-bottom: 10px;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            .otp-code {
                font-size: 42px;
                font-weight: bold;
                color: #d60000;
                letter-spacing: 8px;
                font-family: "Courier New", monospace;
                margin: 10px 0;
            }
            .otp-expiry {
                font-size: 13px;
                color: #666;
                margin-top: 15px;
            }
            .instructions {
                background-color: #fff3cd;
                border-left: 4px solid #ffc107;
                padding: 15px 20px;
                margin: 20px 0;
                border-radius: 4px;
            }
            .instructions h3 {
                margin: 0 0 10px 0;
                font-size: 16px;
                color: #856404;
            }
            .instructions p {
                margin: 5px 0;
                font-size: 14px;
                color: #856404;
            }
            .security-notice {
                background-color: #f8d7da;
                border-left: 4px solid #dc3545;
                padding: 15px 20px;
                margin: 20px 0;
                border-radius: 4px;
            }
            .security-notice h3 {
                margin: 0 0 10px 0;
                font-size: 16px;
                color: #721c24;
            }
            .security-notice p {
                margin: 5px 0;
                font-size: 14px;
                color: #721c24;
            }
            .footer {
                background-color: #333;
                color: white;
                padding: 25px 20px;
                text-align: center;
                font-size: 13px;
            }
            .footer p {
                margin: 8px 0;
                line-height: 1.6;
            }
            .footer a {
                color: #ffd700;
                text-decoration: none;
            }
            .footer a:hover {
                text-decoration: underline;
            }
            .divider {
                height: 1px;
                background: linear-gradient(to right, transparent, #ddd, transparent);
                margin: 25px 0;
            }
            @media only screen and (max-width: 600px) {
                .content {
                    padding: 30px 20px;
                }
                .otp-code {
                    font-size: 36px;
                    letter-spacing: 6px;
                }
                .header h1 {
                    font-size: 24px;
                }
            }
        </style>
    </head>
    <body>
        <div class="email-container">
            <!-- Header -->
            <div class="header">
                <h1>🚗 MITSUBISHI MOTORS</h1>
                <p>Excellence in Motion</p>
            </div>
            
            <!-- Content -->
            <div class="content">
                <div class="welcome-text">
                    <strong>Welcome to Mitsubishi Motors!</strong>
                </div>
                
                <p style="color: #555; font-size: 15px; line-height: 1.6;">
                    Thank you for creating an account with us. To complete your registration and verify your email address, 
                    please use the One-Time Password (OTP) below:
                </p>
                
                <!-- OTP Box -->
                <div class="otp-container">
                    <div class="otp-label">Your Verification Code</div>
                    <div class="otp-code">' . htmlspecialchars($otp) . '</div>
                    <div class="otp-expiry">⏱️ This code expires in <strong>10 minutes</strong></div>
                </div>
                
                <!-- Instructions -->
                <div class="instructions">
                    <h3>📋 How to Use This Code:</h3>
                    <p>1. Return to the verification page</p>
                    <p>2. Enter the 6-digit code shown above</p>
                    <p>3. Click "Verify" to complete your registration</p>
                </div>
                
                <div class="divider"></div>
                
                <!-- Security Notice -->
                <div class="security-notice">
                    <h3>🔒 Security Notice:</h3>
                    <p><strong>Never share this code with anyone.</strong></p>
                    <p>Mitsubishi Motors staff will never ask for your verification code.</p>
                    <p>If you didn\'t request this code, please ignore this email or contact our support team.</p>
                </div>
                
                <div class="divider"></div>
                
                <p style="color: #666; font-size: 14px; line-height: 1.6;">
                    If you\'re having trouble with verification, you can request a new code on the verification page 
                    or contact our support team for assistance.
                </p>
                
                <p style="color: #666; font-size: 14px; margin-top: 20px;">
                    Best regards,<br>
                    <strong style="color: #d60000;">Mitsubishi Motors Team</strong>
                </p>
            </div>
            
            <!-- Footer -->
            <div class="footer">
                <p><strong>Mitsubishi Motors Philippines</strong></p>
                <p>This is an automated email. Please do not reply to this message.</p>
                <p>For support, contact us at <a href="mailto:mitsubishiautoxpress@gmail.com">mitsubishiautoxpress@gmail.com</a></p>
                <p style="margin-top: 15px; opacity: 0.7;">© ' . date('Y') . ' Mitsubishi Motors. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

