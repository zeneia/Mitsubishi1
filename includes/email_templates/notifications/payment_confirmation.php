<?php
/**
 * Payment Confirmation Email Template
 */

function getPaymentConfirmationEmailTemplate($data) {
    $subject = '✅ Payment Confirmed - Receipt #' . $data['payment_number'];
    
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
            .success-badge { background: #28a745; color: white; padding: 15px 25px; border-radius: 8px; text-align: center; font-size: 20px; font-weight: bold; margin-bottom: 30px; }
            .receipt-box { background: #f8f9fa; border: 2px solid #28a745; padding: 25px; margin: 20px 0; border-radius: 8px; }
            .receipt-box h3 { margin: 0 0 20px 0; color: #28a745; text-align: center; }
            .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #dee2e6; }
            .info-row:last-child { border-bottom: none; }
            .info-label { font-weight: 600; color: #495057; }
            .info-value { color: #212529; text-align: right; }
            .total-row { background: #e7f3ff; padding: 15px; margin-top: 15px; border-radius: 5px; }
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
                <div class="success-badge">✅ PAYMENT CONFIRMED!</div>
                <p style="font-size: 16px; color: #212529;">Dear <strong>' . htmlspecialchars($data['customer_name']) . '</strong>,</p>
                <p style="font-size: 15px; color: #495057; line-height: 1.6;">
                    Thank you! Your payment has been successfully confirmed and processed.
                </p>
                <div class="receipt-box">
                    <h3>🧾 Payment Receipt</h3>
                    <div class="info-row">
                        <span class="info-label">Receipt Number:</span>
                        <span class="info-value"><strong>' . htmlspecialchars($data['payment_number']) . '</strong></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Order Number:</span>
                        <span class="info-value">' . htmlspecialchars($data['order_number']) . '</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Payment Date:</span>
                        <span class="info-value">' . date('F j, Y', strtotime($data['payment_date'])) . '</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Payment Method:</span>
                        <span class="info-value">' . htmlspecialchars($data['payment_method']) . '</span>
                    </div>
                    <div class="total-row">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 18px; font-weight: bold; color: #0066cc;">Amount Paid:</span>
                            <span style="font-size: 24px; font-weight: bold; color: #0066cc;">₱' . number_format($data['amount'], 2) . '</span>
                        </div>
                    </div>
                </div>
                <p style="font-size: 15px; color: #495057; line-height: 1.6;">
                    This payment has been applied to your account. You can view your payment history and remaining balance in your customer portal.
                </p>
                <p style="font-size: 14px; color: #6c757d; margin-top: 30px;">
                    Thank you for your payment. We appreciate your business!
                </p>
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

