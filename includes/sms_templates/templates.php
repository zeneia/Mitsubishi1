<?php
/**
 * SMS Message Templates
 * 
 * All SMS templates for the notification system.
 * Messages are kept under 160 characters to fit in a single SMS segment.
 * 
 * Template variables are replaced using simple string replacement:
 * {customer_name}, {vehicle}, {amount}, {date}, etc.
 */

/**
 * Get Loan Approval SMS Template
 * 
 * @param array $data ['customer_name', 'vehicle', 'order_number', 'amount']
 * @return string SMS message
 */
function getLoanApprovalSMS($data) {
    $template = "Congratulations {customer_name}! Your loan for {vehicle} is APPROVED. Order #{order_number} created. Visit us for next steps. - Mitsubishi Motors";
    
    return replaceSMSVariables($template, $data);
}

/**
 * Get Loan Rejection SMS Template
 * 
 * @param array $data ['customer_name', 'loan_id']
 * @return string SMS message
 */
function getLoanRejectionSMS($data) {
    $template = "Dear {customer_name}, your loan application #{loan_id} was not approved. Please contact us for alternative options. - Mitsubishi Motors";
    
    return replaceSMSVariables($template, $data);
}

/**
 * Get Test Drive Approval SMS Template
 * 
 * @param array $data ['customer_name', 'vehicle', 'date', 'time', 'gate_pass']
 * @return string SMS message
 */
function getTestDriveApprovalSMS($data) {
    $template = "Test drive APPROVED! {vehicle} on {date} at {time}. Gate Pass: {gate_pass}. Bring valid ID. See you! - Mitsubishi Motors";
    
    return replaceSMSVariables($template, $data);
}

/**
 * Get Test Drive Rejection SMS Template
 * 
 * @param array $data ['customer_name', 'vehicle']
 * @return string SMS message
 */
function getTestDriveRejectionSMS($data) {
    $template = "Dear {customer_name}, your test drive request for {vehicle} cannot be accommodated. Please contact us to reschedule. - Mitsubishi Motors";
    
    return replaceSMSVariables($template, $data);
}

/**
 * Get Payment Submission Acknowledgment SMS Template
 * 
 * @param array $data ['customer_name', 'amount', 'payment_number']
 * @return string SMS message
 */
function getPaymentSubmissionSMS($data) {
    $template = "Payment received! ₱{amount} (Ref: {payment_number}). Under review. You'll be notified once confirmed. Thank you! - Mitsubishi Motors";
    
    return replaceSMSVariables($template, $data);
}

/**
 * Get Payment Confirmation SMS Template
 * 
 * @param array $data ['customer_name', 'amount', 'payment_number', 'order_number']
 * @return string SMS message
 */
function getPaymentConfirmationSMS($data) {
    $template = "Payment CONFIRMED! ₱{amount} received for Order #{order_number}. Receipt: {payment_number}. Thank you! - Mitsubishi Motors";
    
    return replaceSMSVariables($template, $data);
}

/**
 * Get Payment Rejection SMS Template
 * 
 * @param array $data ['customer_name', 'amount', 'payment_number']
 * @return string SMS message
 */
function getPaymentRejectionSMS($data) {
    $template = "Payment {payment_number} (₱{amount}) was not confirmed. Please contact us or resubmit with correct details. - Mitsubishi Motors";
    
    return replaceSMSVariables($template, $data);
}

/**
 * Get Payment Reminder SMS Template (7 days before)
 * 
 * @param array $data ['customer_name', 'amount', 'due_date', 'order_number', 'payment_num']
 * @return string SMS message
 */
function getPaymentReminder7DaysSMS($data) {
    $template = "Reminder: Payment #{payment_num} of ₱{amount} due on {due_date} (7 days). Order #{order_number}. Pay early to avoid penalties. - Mitsubishi Motors";
    
    return replaceSMSVariables($template, $data);
}

/**
 * Get Payment Reminder SMS Template (3 days before)
 * 
 * @param array $data ['customer_name', 'amount', 'due_date', 'order_number', 'payment_num']
 * @return string SMS message
 */
function getPaymentReminder3DaysSMS($data) {
    $template = "URGENT: Payment #{payment_num} of ₱{amount} due on {due_date} (3 days). Order #{order_number}. Please pay soon. - Mitsubishi Motors";
    
    return replaceSMSVariables($template, $data);
}

/**
 * Get Payment Reminder SMS Template (1 day before)
 * 
 * @param array $data ['customer_name', 'amount', 'due_date', 'order_number', 'payment_num']
 * @return string SMS message
 */
function getPaymentReminder1DaySMS($data) {
    $template = "URGENT: Payment #{payment_num} of ₱{amount} due TOMORROW ({due_date}). Order #{order_number}. Please pay now. - Mitsubishi Motors";
    
    return replaceSMSVariables($template, $data);
}

/**
 * Get Payment Due Today SMS Template
 * 
 * @param array $data ['customer_name', 'amount', 'order_number', 'payment_num']
 * @return string SMS message
 */
function getPaymentDueTodaySMS($data) {
    $template = "URGENT: Payment #{payment_num} of ₱{amount} is DUE TODAY. Order #{order_number}. Please pay immediately to avoid penalties. - Mitsubishi Motors";
    
    return replaceSMSVariables($template, $data);
}

/**
 * Get Payment Overdue SMS Template
 * 
 * @param array $data ['customer_name', 'amount', 'due_date', 'order_number', 'payment_num']
 * @return string SMS message
 */
function getPaymentOverdueSMS($data) {
    $template = "OVERDUE: Payment #{payment_num} of ₱{amount} was due {due_date}. Order #{order_number}. Please pay immediately. Contact us ASAP. - Mitsubishi Motors";
    
    return replaceSMSVariables($template, $data);
}

/**
 * Replace variables in SMS template
 * 
 * @param string $template Template with {variable} placeholders
 * @param array $data Associative array of variable values
 * @return string Message with variables replaced
 */
function replaceSMSVariables($template, $data) {
    foreach ($data as $key => $value) {
        // Format currency values
        if (in_array($key, ['amount', 'monthly_payment', 'down_payment'])) {
            $value = number_format((float)$value, 2);
        }
        
        // Format dates
        if (in_array($key, ['date', 'due_date']) && strtotime($value)) {
            $value = date('M j, Y', strtotime($value));
        }
        
        $template = str_replace('{' . $key . '}', $value, $template);
    }
    
    return $template;
}

/**
 * Validate SMS message length
 * 
 * @param string $message SMS message
 * @return array ['valid' => bool, 'length' => int, 'segments' => int]
 */
function validateSMSLength($message) {
    $length = mb_strlen($message);
    $isUnicode = !preg_match('/^[\x00-\x7F]*$/', $message);
    
    // Calculate segments
    if ($isUnicode) {
        $segments = $length <= 70 ? 1 : ceil($length / 67);
        $maxLength = 70;
    } else {
        $segments = $length <= 160 ? 1 : ceil($length / 153);
        $maxLength = 160;
    }
    
    return [
        'valid' => $length <= $maxLength,
        'length' => $length,
        'segments' => $segments,
        'is_unicode' => $isUnicode,
        'max_length' => $maxLength
    ];
}

