<?php
/**
 * Payment Reminder Email Template
 */

function getPaymentReminderEmailTemplate($data) {
    // Determine urgency level
    $daysUntilDue = $data['days_until_due'];
    $isOverdue = $daysUntilDue < 0;
    $urgencyColor = $isOverdue ? '#dc3545' : ($daysUntilDue <= 1 ? '#ffc107' : '#17a2b8');
    
    if ($isOverdue) {
        $subject = '🚨 OVERDUE: Payment Due - Order #' . $data['order_number'];
        $urgencyText = 'OVERDUE';
    } elseif ($daysUntilDue == 0) {
        $subject = '⚠️ Payment Due TODAY - Order #' . $data['order_number'];
        $urgencyText = 'DUE TODAY';
    } elseif ($daysUntilDue == 1) {
        $subject = '⚠️ Payment Due Tomorrow - Order #' . $data['order_number'];
        $urgencyText = 'DUE TOMORROW';
    } else {
        $subject = '📅 Payment Reminder - Due in ' . $daysUntilDue . ' days';
        $urgencyText = 'UPCOMING';
    }
    
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
            .urgency-badge { background: ' . $urgencyColor . '; color: white; padding: 15px 25px; border-radius: 8px; text-align: center; font-size: 20px; font-weight: bold; margin-bottom: 30px; }
            .payment-box { background: #f8f9fa; border: 2px solid ' . $urgencyColor . '; padding: 25px; margin: 20px 0; border-radius: 8px; }
            .payment-box h3 { margin: 0 0 20px 0; color: ' . $urgencyColor . '; text-align: center; }
            .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #dee2e6; }
            .info-row:last-child { border-bottom: none; }
            .info-label { font-weight: 600; color: #495057; }
            .info-value { color: #212529; text-align: right; }
            .amount-due { background: ' . $urgencyColor . '; color: white; padding: 20px; margin-top: 15px; border-radius: 5px; text-align: center; }
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
                <div class="urgency-badge">' . ($isOverdue ? '🚨' : '📅') . ' PAYMENT ' . $urgencyText . '</div>
                <p style="font-size: 16px; color: #212529;">Dear <strong>' . htmlspecialchars($data['customer_name']) . '</strong>,</p>
                <p style="font-size: 15px; color: #495057; line-height: 1.6;">
                    ' . ($isOverdue 
                        ? 'Your payment is now <strong style="color: #dc3545;">OVERDUE</strong>. Please make your payment immediately to avoid additional penalties.' 
                        : 'This is a friendly reminder that your payment is due soon. Please ensure timely payment to avoid any penalties.') . '
                </p>
                <div class="payment-box">
                    <h3>💳 Payment Details</h3>
                    <div class="info-row">
                        <span class="info-label">Order Number:</span>
                        <span class="info-value"><strong>' . htmlspecialchars($data['order_number']) . '</strong></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Payment Number:</span>
                        <span class="info-value">#' . htmlspecialchars($data['payment_num']) . '</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Due Date:</span>
                        <span class="info-value"><strong>' . date('F j, Y', strtotime($data['due_date'])) . '</strong></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Days ' . ($isOverdue ? 'Overdue' : 'Until Due') . ':</span>
                        <span class="info-value"><strong>' . abs($daysUntilDue) . ' day' . (abs($daysUntilDue) != 1 ? 's' : '') . '</strong></span>
                    </div>
                    <div class="amount-due">
                        <div style="font-size: 14px; margin-bottom: 5px;">Amount Due</div>
                        <div style="font-size: 32px; font-weight: bold;">₱' . number_format($data['amount'], 2) . '</div>
                    </div>
                </div>
                <p style="font-size: 15px; color: #495057; line-height: 1.6;">
                    You can make your payment through any of our accepted payment methods. Please contact us if you need assistance or have any questions.
                </p>
                <center><a href="tel:+63123456789" class="cta-button">💳 Make Payment Now</a></center>
                <p style="font-size: 14px; color: #6c757d; margin-top: 30px;">
                    ' . ($isOverdue 
                        ? 'Late payment penalties may apply. Please settle your account as soon as possible.' 
                        : 'Thank you for your prompt attention to this matter.') . '
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

