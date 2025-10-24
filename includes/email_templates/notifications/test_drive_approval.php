<?php
/**
 * Test Drive Approval Email Template
 * 
 * Sent when a test drive request is approved
 * 
 * @param array $data Template data
 * @return array ['subject' => string, 'body' => string]
 */

function getTestDriveApprovalEmailTemplate($data) {
    $subject = '✅ Test Drive Approved - Gate Pass #' . $data['gate_pass'];
    
    $body = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Test Drive Approved</title>
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
            .gate-pass {
                background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
                color: #000;
                padding: 25px;
                border-radius: 10px;
                text-align: center;
                margin: 25px 0;
                border: 3px dashed #ff6f00;
            }
            .gate-pass h2 {
                margin: 0 0 10px 0;
                font-size: 16px;
                text-transform: uppercase;
            }
            .gate-pass .number {
                font-size: 32px;
                font-weight: bold;
                letter-spacing: 3px;
                margin: 10px 0;
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
            .important-notes {
                background: #fff3cd;
                border: 1px solid #ffc107;
                padding: 20px;
                border-radius: 8px;
                margin: 25px 0;
            }
            .important-notes h3 {
                margin: 0 0 15px 0;
                color: #856404;
            }
            .important-notes ul {
                margin: 0;
                padding-left: 20px;
            }
            .important-notes li {
                margin: 8px 0;
                color: #856404;
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
                    ✅ TEST DRIVE APPROVED!
                </div>
                
                <p style="font-size: 16px; color: #212529;">
                    Dear <strong>' . htmlspecialchars($data['customer_name']) . '</strong>,
                </p>
                
                <p style="font-size: 15px; color: #495057; line-height: 1.6;">
                    Great news! Your test drive request has been <strong style="color: #28a745;">APPROVED</strong>. 
                    We\'re excited to have you experience the performance and comfort of our vehicles firsthand.
                </p>
                
                <!-- Gate Pass -->
                <div class="gate-pass">
                    <h2>🎫 Your Gate Pass</h2>
                    <div class="number">' . htmlspecialchars($data['gate_pass']) . '</div>
                    <p style="margin: 10px 0 0 0; font-size: 14px;">
                        Please present this gate pass number at the showroom
                    </p>
                </div>
                
                <!-- Test Drive Details -->
                <div class="info-box">
                    <h3>📋 Test Drive Details</h3>
                    <div class="info-row">
                        <span class="info-label">Vehicle:</span>
                        <span class="info-value"><strong>' . htmlspecialchars($data['vehicle']) . '</strong></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Date:</span>
                        <span class="info-value">' . date('F j, Y', strtotime($data['date'])) . '</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Time:</span>
                        <span class="info-value">' . htmlspecialchars($data['time']) . '</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Location:</span>
                        <span class="info-value">' . htmlspecialchars($data['location']) . '</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Gate Pass:</span>
                        <span class="info-value"><strong>' . htmlspecialchars($data['gate_pass']) . '</strong></span>
                    </div>
                </div>
                
                <!-- Important Notes -->
                <div class="important-notes">
                    <h3>⚠️ Important Reminders</h3>
                    <ul>
                        <li><strong>Bring a valid driver\'s license</strong> (original copy required)</li>
                        <li>Arrive <strong>15 minutes before</strong> your scheduled time</li>
                        <li>Present your <strong>Gate Pass number</strong> at the reception</li>
                        <li>Wear comfortable clothing and closed-toe shoes</li>
                        <li>Our instructor will accompany you during the test drive</li>
                    </ul>
                </div>
                
                <p style="font-size: 15px; color: #495057; line-height: 1.6;">
                    If you need to reschedule or have any questions, please contact us at least 24 hours in advance.
                </p>
                
                <p style="font-size: 14px; color: #6c757d; margin-top: 30px;">
                    We look forward to seeing you! Enjoy your test drive experience.
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

