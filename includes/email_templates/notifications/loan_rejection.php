<?php
/**
 * Loan Rejection Email Template
 * 
 * Sent when a loan application is rejected
 * 
 * @param array $data Template data
 * @return array ['subject' => string, 'body' => string]
 */

function getLoanRejectionEmailTemplate($data) {
    $subject = 'Loan Application Update - Mitsubishi Motors';
    
    $body = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Loan Application Update</title>
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
            }
            .info-box {
                background: #f8f9fa;
                border-left: 4px solid #d60000;
                padding: 20px;
                margin: 20px 0;
                border-radius: 4px;
            }
            .info-box h3 {
                margin: 0 0 15px 0;
                color: #d60000;
                font-size: 18px;
            }
            .alternatives {
                background: #e7f3ff;
                border: 1px solid #0066cc;
                padding: 20px;
                border-radius: 8px;
                margin: 25px 0;
            }
            .alternatives h3 {
                margin: 0 0 15px 0;
                color: #0066cc;
            }
            .alternatives ul {
                margin: 0;
                padding-left: 20px;
            }
            .alternatives li {
                margin: 8px 0;
                color: #004080;
            }
            .cta-button {
                display: inline-block;
                background: #d60000;
                color: white;
                padding: 15px 40px;
                text-decoration: none;
                border-radius: 5px;
                font-weight: bold;
                margin: 20px 0;
            }
            .footer {
                background: #343a40;
                color: #ffffff;
                padding: 30px;
                text-align: center;
                font-size: 13px;
            }
            .footer p {
                margin: 5px 0;
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
                <p style="font-size: 16px; color: #212529;">
                    Dear <strong>' . htmlspecialchars($data['customer_name']) . '</strong>,
                </p>
                
                <p style="font-size: 15px; color: #495057; line-height: 1.6;">
                    Thank you for your interest in financing your vehicle purchase with Mitsubishi Motors. 
                    After careful review of your loan application <strong>#' . htmlspecialchars($data['loan_id']) . '</strong>, 
                    we regret to inform you that we are unable to approve your application at this time.
                </p>
                
                <!-- Application Details -->
                <div class="info-box">
                    <h3>📋 Application Details</h3>
                    <p style="margin: 0; color: #495057;">
                        <strong>Application ID:</strong> #' . htmlspecialchars($data['loan_id']) . '<br>
                        <strong>Vehicle:</strong> ' . htmlspecialchars($data['vehicle']) . '<br>
                        <strong>Status:</strong> Not Approved
                    </p>
                </div>
                
                <!-- Alternative Options -->
                <div class="alternatives">
                    <h3>💡 Alternative Options</h3>
                    <p style="margin: 0 0 10px 0; color: #004080;">
                        We understand this may be disappointing. Here are some alternatives:
                    </p>
                    <ul>
                        <li>Consider a different vehicle model with lower financing requirements</li>
                        <li>Increase your down payment amount</li>
                        <li>Explore alternative financing options with our partner banks</li>
                        <li>Reapply after improving your credit profile</li>
                    </ul>
                </div>
                
                <p style="font-size: 15px; color: #495057; line-height: 1.6;">
                    Our sales team is here to help you explore other options. Please contact us to discuss 
                    alternative financing solutions or vehicle options that may better suit your needs.
                </p>
                
                <center>
                    <a href="tel:+63123456789" class="cta-button">📞 Contact Our Team</a>
                </center>
                
                <p style="font-size: 14px; color: #6c757d; margin-top: 30px;">
                    We appreciate your interest in Mitsubishi Motors and hope to serve you in the future.
                </p>
            </div>
            
            <!-- Footer -->
            <div class="footer">
                <p><strong>Mitsubishi Motors - San Pablo Branch</strong></p>
                <p>📍 San Pablo City, Laguna, Philippines</p>
                <p>📞 Phone: +63 123 456 7890 | 📧 Email: info@mitsubishi-sanpablo.com</p>
                <p style="margin-top: 15px; opacity: 0.8;">
                    This is an automated notification. Please do not reply to this email.
                </p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    return [
        'subject' => $subject,
        'body' => $body
    ];
}

