<?php
/**
 * Test Drive Rejection Email Template
 */

function getTestDriveRejectionEmailTemplate($data) {
    $subject = 'Test Drive Request Update - Mitsubishi Motors';
    
    $body = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <style>
            body { margin: 0; padding: 0; font-family: "Segoe UI", Arial, sans-serif; background-color: #f4f4f4; }
            .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
            .header { background: linear-gradient(135deg, #d60000 0%, #b30000 100%); color: white; padding: 30px 20px; text-align: center; }
            .header h1 { margin: 0; font-size: 28px; font-weight: bold; }
            .content { padding: 40px 30px; }
            .info-box { background: #f8f9fa; border-left: 4px solid #d60000; padding: 20px; margin: 20px 0; border-radius: 4px; }
            .cta-button { display: inline-block; background: #d60000; color: white; padding: 15px 40px; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 20px 0; }
            .footer { background: #343a40; color: #ffffff; padding: 30px; text-align: center; font-size: 13px; }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="header">
                <h1>🚗 MITSUBISHI MOTORS</h1>
                <p>Excellence in Motion</p>
            </div>
            <div class="content">
                <p style="font-size: 16px; color: #212529;">Dear <strong>' . htmlspecialchars($data['customer_name']) . '</strong>,</p>
                <p style="font-size: 15px; color: #495057; line-height: 1.6;">
                    Thank you for your interest in test driving the <strong>' . htmlspecialchars($data['vehicle']) . '</strong>. 
                    Unfortunately, we are unable to accommodate your test drive request at the scheduled time.
                </p>
                <div class="info-box">
                    <p style="margin: 0; color: #495057;">
                        <strong>Request ID:</strong> #' . htmlspecialchars($data['request_id']) . '<br>
                        <strong>Vehicle:</strong> ' . htmlspecialchars($data['vehicle']) . '
                    </p>
                </div>
                <p style="font-size: 15px; color: #495057; line-height: 1.6;">
                    We apologize for any inconvenience. Please contact us to reschedule your test drive at a more convenient time.
                </p>
                <center><a href="tel:+63123456789" class="cta-button">📞 Reschedule Now</a></center>
            </div>
            <div class="footer">
                <p><strong>Mitsubishi Motors - San Pablo Branch</strong></p>
                <p>📞 Phone: +63 123 456 7890 | 📧 Email: info@mitsubishi-sanpablo.com</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    return ['subject' => $subject, 'body' => $body];
}

