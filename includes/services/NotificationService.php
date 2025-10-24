<?php
/**
 * NotificationService
 * 
 * Centralized service for sending email and SMS notifications
 * Handles all customer notifications for loans, test drives, and payments
 * 
 * Usage:
 *   $service = new NotificationService($pdo);
 *   $service->sendLoanApprovalNotification($loanId, $orderNumber);
 */

class NotificationService
{
    private $pdo;
    private $mailer;
    
    /**
     * Constructor
     * 
     * @param PDO $pdo Database connection
     */
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        
        // Load GmailMailer
        require_once dirname(__DIR__) . '/backend/GmailMailer.php';
        $this->mailer = new \Mitsubishi\Backend\GmailMailer();
        
        // Load PhilSmsSender
        require_once dirname(__DIR__) . '/backend/PhilSmsSender.php';
        
        // Load email templates
        require_once dirname(__DIR__) . '/email_templates/notifications/loan_approval.php';
        require_once dirname(__DIR__) . '/email_templates/notifications/loan_rejection.php';
        require_once dirname(__DIR__) . '/email_templates/notifications/test_drive_approval.php';
        require_once dirname(__DIR__) . '/email_templates/notifications/test_drive_rejection.php';
        require_once dirname(__DIR__) . '/email_templates/notifications/payment_confirmation.php';
        require_once dirname(__DIR__) . '/email_templates/notifications/payment_rejection.php';
        require_once dirname(__DIR__) . '/email_templates/notifications/payment_reminder.php';
        
        // Load SMS templates
        require_once dirname(__DIR__) . '/sms_templates/templates.php';
    }
    
    /**
     * Send Loan Approval Notification
     * 
     * @param int $loanId Loan application ID
     * @param string $orderNumber Order number
     * @return array Result
     */
    public function sendLoanApprovalNotification($loanId, $orderNumber)
    {
        try {
            // Get loan details
            $loan = $this->getLoanDetails($loanId);
            if (!$loan) {
                throw new \Exception("Loan application not found: $loanId");
            }
            
            // Get customer contact info
            $customer = $this->getCustomerContactInfo($loan['customer_id']);
            if (!$customer) {
                throw new \Exception("Customer not found: {$loan['customer_id']}");
            }
            
            // Prepare template data
            $data = [
                'customer_name' => $customer['firstname'] . ' ' . $customer['lastname'],
                'loan_id' => $loanId,
                'order_number' => $orderNumber,
                'vehicle' => $loan['model_name'] . ' ' . $loan['variant'],
                'amount' => $loan['loan_amount'],
                'monthly_payment' => $loan['monthly_payment'],
                'term' => $loan['term_months']
            ];
            
            // Send notifications
            return $this->sendNotification(
                $customer,
                'loan_approval',
                $data,
                $loanId,
                'loan_applications'
            );
            
        } catch (\Exception $e) {
            error_log("Loan approval notification error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Send Loan Rejection Notification
     * 
     * @param int $loanId Loan application ID
     * @param string $rejectionReason Reason for rejection
     * @return array Result
     */
    public function sendLoanRejectionNotification($loanId, $rejectionReason = '')
    {
        try {
            $loan = $this->getLoanDetails($loanId);
            if (!$loan) {
                throw new \Exception("Loan application not found: $loanId");
            }
            
            $customer = $this->getCustomerContactInfo($loan['customer_id']);
            if (!$customer) {
                throw new \Exception("Customer not found: {$loan['customer_id']}");
            }
            
            $data = [
                'customer_name' => $customer['firstname'] . ' ' . $customer['lastname'],
                'loan_id' => $loanId,
                'vehicle' => $loan['model_name'] . ' ' . $loan['variant'],
                'rejection_reason' => $rejectionReason
            ];
            
            return $this->sendNotification(
                $customer,
                'loan_rejection',
                $data,
                $loanId,
                'loan_applications'
            );
            
        } catch (\Exception $e) {
            error_log("Loan rejection notification error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Send Test Drive Approval Notification
     * 
     * @param int $requestId Test drive request ID
     * @return array Result
     */
    public function sendTestDriveApprovalNotification($requestId)
    {
        try {
            $testDrive = $this->getTestDriveDetails($requestId);
            if (!$testDrive) {
                throw new \Exception("Test drive request not found: $requestId");
            }
            
            $customer = $this->getCustomerContactInfo($testDrive['customer_id']);
            if (!$customer) {
                throw new \Exception("Customer not found: {$testDrive['customer_id']}");
            }
            
            $data = [
                'customer_name' => $customer['firstname'] . ' ' . $customer['lastname'],
                'request_id' => $requestId,
                'vehicle' => $testDrive['model_name'] . ' ' . $testDrive['variant'],
                'date' => $testDrive['preferred_date'],
                'time' => $testDrive['preferred_time'],
                'gate_pass' => $testDrive['gate_pass_number'],
                'location' => 'San Pablo Branch'
            ];
            
            return $this->sendNotification(
                $customer,
                'test_drive_approval',
                $data,
                $requestId,
                'test_drive_requests'
            );
            
        } catch (\Exception $e) {
            error_log("Test drive approval notification error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Send Test Drive Rejection Notification
     * 
     * @param int $requestId Test drive request ID
     * @param string $rejectionReason Reason for rejection
     * @return array Result
     */
    public function sendTestDriveRejectionNotification($requestId, $rejectionReason = '')
    {
        try {
            $testDrive = $this->getTestDriveDetails($requestId);
            if (!$testDrive) {
                throw new \Exception("Test drive request not found: $requestId");
            }
            
            $customer = $this->getCustomerContactInfo($testDrive['customer_id']);
            if (!$customer) {
                throw new \Exception("Customer not found: {$testDrive['customer_id']}");
            }
            
            $data = [
                'customer_name' => $customer['firstname'] . ' ' . $customer['lastname'],
                'request_id' => $requestId,
                'vehicle' => $testDrive['model_name'] . ' ' . $testDrive['variant'],
                'rejection_reason' => $rejectionReason
            ];
            
            return $this->sendNotification(
                $customer,
                'test_drive_rejection',
                $data,
                $requestId,
                'test_drive_requests'
            );
            
        } catch (\Exception $e) {
            error_log("Test drive rejection notification error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Send Payment Confirmation Notification
     * 
     * @param int $paymentId Payment history ID
     * @return array Result
     */
    public function sendPaymentConfirmationNotification($paymentId)
    {
        try {
            $payment = $this->getPaymentDetails($paymentId);
            if (!$payment) {
                throw new \Exception("Payment not found: $paymentId");
            }
            
            $customer = $this->getCustomerContactInfo($payment['customer_id']);
            if (!$customer) {
                throw new \Exception("Customer not found: {$payment['customer_id']}");
            }
            
            $data = [
                'customer_name' => $customer['firstname'] . ' ' . $customer['lastname'],
                'payment_number' => $payment['payment_number'],
                'order_number' => $payment['order_number'],
                'amount' => $payment['amount'],
                'payment_date' => $payment['payment_date'],
                'payment_method' => $payment['payment_method']
            ];
            
            return $this->sendNotification(
                $customer,
                'payment_confirmation',
                $data,
                $paymentId,
                'payment_history'
            );
            
        } catch (\Exception $e) {
            error_log("Payment confirmation notification error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Send Payment Rejection Notification
     * 
     * @param int $paymentId Payment history ID
     * @param string $rejectionReason Reason for rejection
     * @return array Result
     */
    public function sendPaymentRejectionNotification($paymentId, $rejectionReason = '')
    {
        try {
            $payment = $this->getPaymentDetails($paymentId);
            if (!$payment) {
                throw new \Exception("Payment not found: $paymentId");
            }
            
            $customer = $this->getCustomerContactInfo($payment['customer_id']);
            if (!$customer) {
                throw new \Exception("Customer not found: {$payment['customer_id']}");
            }
            
            $data = [
                'customer_name' => $customer['firstname'] . ' ' . $customer['lastname'],
                'payment_number' => $payment['payment_number'],
                'order_number' => $payment['order_number'],
                'amount' => $payment['amount'],
                'rejection_reason' => $rejectionReason
            ];
            
            return $this->sendNotification(
                $customer,
                'payment_rejection',
                $data,
                $paymentId,
                'payment_history'
            );
            
        } catch (\Exception $e) {
            error_log("Payment rejection notification error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send Payment Reminder Notification
     *
     * @param int $scheduleId Payment schedule ID
     * @param int $daysUntilDue Days until due date (negative if overdue)
     * @return array Result
     */
    public function sendPaymentReminderNotification($scheduleId, $daysUntilDue)
    {
        try {
            $schedule = $this->getPaymentScheduleDetails($scheduleId);
            if (!$schedule) {
                throw new \Exception("Payment schedule not found: $scheduleId");
            }

            $customer = $this->getCustomerContactInfo($schedule['customer_id']);
            if (!$customer) {
                throw new \Exception("Customer not found: {$schedule['customer_id']}");
            }

            $data = [
                'customer_name' => $customer['firstname'] . ' ' . $customer['lastname'],
                'order_number' => $schedule['order_number'],
                'payment_num' => $schedule['payment_number'],
                'amount' => $schedule['amount'],
                'due_date' => $schedule['due_date'],
                'days_until_due' => $daysUntilDue
            ];

            return $this->sendNotification(
                $customer,
                'payment_reminder',
                $data,
                $scheduleId,
                'payment_schedule'
            );

        } catch (\Exception $e) {
            error_log("Payment reminder notification error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send Payment Reminders (Batch)
     * Called by cron job to send all due reminders
     *
     * @return array Statistics
     */
    public function sendPaymentReminders()
    {
        $stats = ['sent' => 0, 'failed' => 0, 'skipped' => 0];

        try {
            // Get all pending payment schedules that need reminders
            $stmt = $this->pdo->prepare("
                SELECT
                    ps.*,
                    DATEDIFF(ps.due_date, CURDATE()) as days_until_due
                FROM payment_schedule ps
                WHERE ps.status = 'Pending'
                AND (
                    DATEDIFF(ps.due_date, CURDATE()) = 7 OR
                    DATEDIFF(ps.due_date, CURDATE()) = 3 OR
                    DATEDIFF(ps.due_date, CURDATE()) = 1 OR
                    DATEDIFF(ps.due_date, CURDATE()) = 0 OR
                    DATEDIFF(ps.due_date, CURDATE()) = -1
                )
                ORDER BY ps.due_date ASC
            ");
            $stmt->execute();
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($schedules as $schedule) {
                // Check if reminder already sent today
                if ($this->wasReminderSentToday($schedule['id'])) {
                    $stats['skipped']++;
                    continue;
                }

                $result = $this->sendPaymentReminderNotification(
                    $schedule['id'],
                    $schedule['days_until_due']
                );

                if ($result['success']) {
                    $stats['sent']++;
                } else {
                    $stats['failed']++;
                }
            }

        } catch (\Exception $e) {
            error_log("Batch payment reminders error: " . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Core notification sending method
     *
     * @param array $customer Customer contact info
     * @param string $notificationType Type of notification
     * @param array $data Template data
     * @param int $relatedId Related record ID
     * @param string $relatedTable Related table name
     * @return array Result
     */
    private function sendNotification($customer, $notificationType, $data, $relatedId, $relatedTable)
    {
        $emailStatus = 'skipped';
        $smsStatus = 'skipped';
        $emailError = null;
        $smsError = null;
        $emailSubject = null;
        $smsPreview = null;

        // Send Email
        if (!empty($customer['Email'])) {
            try {
                $emailTemplate = $this->getEmailTemplate($notificationType, $data);
                $emailSubject = $emailTemplate['subject'];

                $result = $this->mailer->sendEmail(
                    $customer['Email'],
                    $emailTemplate['subject'],
                    $emailTemplate['body']
                );

                // Check if email was actually sent
                if ($result['success']) {
                    $emailStatus = 'sent';
                } else {
                    $emailStatus = 'failed';
                    $emailError = $result['error'] ?? 'Unknown email error';
                    error_log("Email send failed: " . $emailError);
                }
            } catch (\Exception $e) {
                $emailStatus = 'failed';
                $emailError = $e->getMessage();
                error_log("Email send error: " . $e->getMessage());
            }
        }

        // Send SMS
        if (!empty($customer['mobile_number'])) {
            try {
                $smsMessage = $this->getSMSTemplate($notificationType, $data);
                $smsPreview = substr($smsMessage, 0, 160);

                $result = \PhilSmsSender::sendSms(
                    $customer['mobile_number'],
                    $smsMessage
                );

                if ($result['success']) {
                    $smsStatus = 'sent';
                } else {
                    $smsStatus = 'failed';
                    $smsError = $result['error'] ?? 'Unknown SMS error';
                }
            } catch (\Exception $e) {
                $smsStatus = 'failed';
                $smsError = $e->getMessage();
                error_log("SMS send error: " . $e->getMessage());
            }
        }

        // Log notification
        $this->logNotification(
            $customer['customer_id'],
            $notificationType,
            $emailStatus,
            $smsStatus,
            $customer['Email'],
            $customer['mobile_number'],
            $emailSubject,
            $smsPreview,
            $emailError,
            $smsError,
            $relatedId,
            $relatedTable
        );

        return [
            'success' => ($emailStatus === 'sent' || $smsStatus === 'sent'),
            'email_status' => $emailStatus,
            'sms_status' => $smsStatus,
            'email_error' => $emailError,
            'sms_error' => $smsError
        ];
    }

    /**
     * Get email template for notification type
     */
    private function getEmailTemplate($type, $data)
    {
        switch ($type) {
            case 'loan_approval':
                return getLoanApprovalEmailTemplate($data);
            case 'loan_rejection':
                return getLoanRejectionEmailTemplate($data);
            case 'test_drive_approval':
                return getTestDriveApprovalEmailTemplate($data);
            case 'test_drive_rejection':
                return getTestDriveRejectionEmailTemplate($data);
            case 'payment_confirmation':
                return getPaymentConfirmationEmailTemplate($data);
            case 'payment_rejection':
                return getPaymentRejectionEmailTemplate($data);
            case 'payment_reminder':
                return getPaymentReminderEmailTemplate($data);
            default:
                throw new \Exception("Unknown email template type: $type");
        }
    }

    /**
     * Get SMS template for notification type
     */
    private function getSMSTemplate($type, $data)
    {
        switch ($type) {
            case 'loan_approval':
                return getLoanApprovalSMS($data);
            case 'loan_rejection':
                return getLoanRejectionSMS($data);
            case 'test_drive_approval':
                return getTestDriveApprovalSMS($data);
            case 'test_drive_rejection':
                return getTestDriveRejectionSMS($data);
            case 'payment_confirmation':
                return getPaymentConfirmationSMS($data);
            case 'payment_rejection':
                return getPaymentRejectionSMS($data);
            case 'payment_reminder':
                // Determine which reminder template based on days
                $days = $data['days_until_due'];
                if ($days < 0) {
                    return getPaymentOverdueSMS($data);
                } elseif ($days == 0) {
                    return getPaymentDueTodaySMS($data);
                } elseif ($days == 1) {
                    return getPaymentReminder1DaySMS($data);
                } elseif ($days == 3) {
                    return getPaymentReminder3DaysSMS($data);
                } else {
                    return getPaymentReminder7DaysSMS($data);
                }
            default:
                throw new \Exception("Unknown SMS template type: $type");
        }
    }

    /**
     * Get customer contact information
     */
    private function getCustomerContactInfo($customerId)
    {
        $stmt = $this->pdo->prepare("
            SELECT
                a.Id as customer_id,
                a.Email,
                ci.mobile_number,
                ci.firstname,
                ci.lastname
            FROM accounts a
            INNER JOIN customer_information ci ON a.Id = ci.account_id
            WHERE a.Id = ?
        ");
        $stmt->execute([$customerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get loan application details
     */
    private function getLoanDetails($loanId)
    {
        $stmt = $this->pdo->prepare("
            SELECT
                la.*,
                v.model_name,
                v.variant,
                a.Id as customer_id
            FROM loan_applications la
            INNER JOIN vehicles v ON la.vehicle_id = v.id
            INNER JOIN accounts a ON la.customer_id = a.Id
            WHERE la.id = ?
        ");
        $stmt->execute([$loanId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get test drive request details
     */
    private function getTestDriveDetails($requestId)
    {
        $stmt = $this->pdo->prepare("
            SELECT
                tdr.*,
                v.model_name,
                v.variant,
                a.Id as customer_id
            FROM test_drive_requests tdr
            INNER JOIN vehicles v ON tdr.vehicle_id = v.id
            INNER JOIN accounts a ON tdr.account_id = a.Id
            WHERE tdr.id = ?
        ");
        $stmt->execute([$requestId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get payment details
     */
    private function getPaymentDetails($paymentId)
    {
        $stmt = $this->pdo->prepare("
            SELECT
                ph.*,
                o.order_number,
                a.Id as customer_id
            FROM payment_history ph
            INNER JOIN orders o ON ph.order_id = o.order_id
            INNER JOIN accounts a ON ph.customer_id = a.Id
            WHERE ph.id = ?
        ");
        $stmt->execute([$paymentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get payment schedule details
     */
    private function getPaymentScheduleDetails($scheduleId)
    {
        $stmt = $this->pdo->prepare("
            SELECT
                ps.*,
                o.order_number,
                a.Id as customer_id
            FROM payment_schedule ps
            INNER JOIN orders o ON ps.order_id = o.order_id
            INNER JOIN accounts a ON ps.customer_id = a.Id
            WHERE ps.id = ?
        ");
        $stmt->execute([$scheduleId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Check if reminder was already sent today
     */
    private function wasReminderSentToday($scheduleId)
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM notification_logs
            WHERE related_id = ?
            AND related_table = 'payment_schedule'
            AND notification_type = 'payment_reminder'
            AND DATE(sent_at) = CURDATE()
        ");
        $stmt->execute([$scheduleId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }

    /**
     * Log notification to database
     */
    private function logNotification(
        $customerId,
        $notificationType,
        $emailStatus,
        $smsStatus,
        $emailRecipient,
        $smsRecipient,
        $emailSubject,
        $smsPreview,
        $emailError,
        $smsError,
        $relatedId,
        $relatedTable
    ) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO notification_logs (
                    customer_id,
                    notification_type,
                    channel,
                    email_status,
                    email_recipient,
                    email_subject,
                    email_error,
                    sms_status,
                    sms_recipient,
                    sms_message_preview,
                    sms_error,
                    related_id,
                    related_table
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $channel = 'both';
            if (empty($emailRecipient)) $channel = 'sms';
            if (empty($smsRecipient)) $channel = 'email';

            $stmt->execute([
                $customerId,
                $notificationType,
                $channel,
                $emailStatus,
                $emailRecipient,
                $emailSubject,
                $emailError,
                $smsStatus,
                $smsRecipient,
                $smsPreview,
                $smsError,
                $relatedId,
                $relatedTable
            ]);
        } catch (\Exception $e) {
            error_log("Failed to log notification: " . $e->getMessage());
        }
    }
}
