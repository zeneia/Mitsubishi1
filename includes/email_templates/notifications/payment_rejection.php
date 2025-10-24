<?php
/**
 * Payment Rejection Email Template
 */

function getPaymentRejectionEmailTemplate($data) {
    $subject = 'Payment Update Required - Mitsubishi Motors';
    
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
            .warning-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; margin: 20px 0; border-radius: 4px; }
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
                    We received your payment submission but were unable to confirm it. Your payment requires attention.
                </p>
                <div class="warning-box">
                    <h3 style="margin: 0 0 10px 0; color: #856404;">⚠️ Action Required</h3>
                    <p style="margin: 0; color: #856404;">
                        <strong>Reason:</strong> ' . htmlspecialchars($data['rejection_reason']) . '
                    </p>
                </div>
                <div class="info-box">
                    <p style="margin: 0; color: #495057;">
                        <strong>Payment Reference:</strong> ' . htmlspecialchars($data['payment_number']) . '<br>
                        <strong>Amount:</strong> ₱' . number_format($data['amount'], 2) . '<br>
                        <strong>Order Number:</strong> ' . htmlspecialchars($data['order_number']) . '
                    </p>
                </div>
                <p style="font-size: 15px; color: #495057; line-height: 1.6;">
                    Please contact us or resubmit your payment with the correct information. Our team is ready to assist you.
                </p>
                <center><a href="tel:+63123456789" class="cta-button">📞 Contact Support</a></center>
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

