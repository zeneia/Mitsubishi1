<?php
/**
 * Loan Approval Email Template
 * 
 * Sent when a loan application is approved
 * 
 * @param array $data Template data
 * @return array ['subject' => string, 'body' => string]
 */

function getLoanApprovalEmailTemplate($data) {
    $subject = '🎉 Loan Application Approved - Mitsubishi Motors';
    
    $body = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Loan Application Approved</title>
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
            .success-badge {
                background: #28a745;
                color: white;
                padding: 15px 25px;
                border-radius: 8px;
                text-align: center;
                font-size: 20px;
                font-weight: bold;
                margin-bottom: 30px;
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
            .info-row {
                display: flex;
                justify-content: space-between;
                padding: 10px 0;
                border-bottom: 1px solid #dee2e6;
            }
            .info-row:last-child {
                border-bottom: none;
            }
            .info-label {
                font-weight: 600;
                color: #495057;
            }
            .info-value {
                color: #212529;
                text-align: right;
            }
            .next-steps {
                background: #fff3cd;
                border: 1px solid #ffc107;
                padding: 20px;
                border-radius: 8px;
                margin: 25px 0;
            }
            .next-steps h3 {
                margin: 0 0 15px 0;
                color: #856404;
            }
            .next-steps ul {
                margin: 0;
                padding-left: 20px;
            }
            .next-steps li {
                margin: 8px 0;
                color: #856404;
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
                <div class="success-badge">
                    ✅ LOAN APPLICATION APPROVED!
                </div>
                
                <p style="font-size: 16px; color: #212529;">
                    Dear <strong>' . htmlspecialchars($data['customer_name']) . '</strong>,
                </p>
                
                <p style="font-size: 15px; color: #495057; line-height: 1.6;">
                    Congratulations! We are pleased to inform you that your loan application has been 
                    <strong style="color: #28a745;">APPROVED</strong>. Your order has been created and 
                    is now being processed.
                </p>
                
                <!-- Loan Details -->
                <div class="info-box">
                    <h3>📋 Loan Application Details</h3>
                    <div class="info-row">
                        <span class="info-label">Application ID:</span>
                        <span class="info-value">#' . htmlspecialchars($data['loan_id']) . '</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Order Number:</span>
                        <span class="info-value"><strong>#' . htmlspecialchars($data['order_number']) . '</strong></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Vehicle:</span>
                        <span class="info-value">' . htmlspecialchars($data['vehicle']) . '</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Loan Amount:</span>
                        <span class="info-value"><strong>₱' . number_format($data['amount'], 2) . '</strong></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Monthly Payment:</span>
                        <span class="info-value">₱' . number_format($data['monthly_payment'], 2) . '</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Term:</span>
                        <span class="info-value">' . htmlspecialchars($data['term']) . ' months</span>
                    </div>
                </div>
                
                <!-- Next Steps -->
                <div class="next-steps">
                    <h3>📌 Next Steps</h3>
                    <ul>
                        <li>Visit our showroom to complete the documentation</li>
                        <li>Bring valid IDs and required documents</li>
                        <li>Our sales team will guide you through the process</li>
                        <li>Schedule your vehicle delivery date</li>
                    </ul>
                </div>
                
                <p style="font-size: 15px; color: #495057; line-height: 1.6;">
                    Our team will contact you shortly to schedule your visit. If you have any questions, 
                    please don\'t hesitate to reach out to us.
                </p>
                
                <center>
                    <a href="tel:+63123456789" class="cta-button">📞 Contact Us</a>
                </center>
                
                <p style="font-size: 14px; color: #6c757d; margin-top: 30px;">
                    Thank you for choosing Mitsubishi Motors. We look forward to serving you!
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

