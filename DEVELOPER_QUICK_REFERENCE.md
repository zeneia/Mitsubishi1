# Developer Quick Reference - Notification System

## 🚀 Quick Start

### Using NotificationService

```php
// 1. Include the service
require_once dirname(__DIR__) . '/includes/services/NotificationService.php';

// 2. Instantiate with PDO connection
$notificationService = new NotificationService($pdo);

// 3. Send notification
$notificationService->sendLoanApprovalNotification($loanId, $orderNumber);
```

---

## 📝 Available Methods

### Loan Notifications

```php
// Loan Approved
$notificationService->sendLoanApprovalNotification($loanId, $orderNumber);

// Loan Rejected
$notificationService->sendLoanRejectionNotification($loanId, $rejectionReason);
```

### Test Drive Notifications

```php
// Test Drive Approved
$notificationService->sendTestDriveApprovalNotification($requestId);

// Test Drive Rejected
$notificationService->sendTestDriveRejectionNotification($requestId, $rejectionReason);
```

### Payment Notifications

```php
// Payment Submitted (Acknowledgment)
$notificationService->sendPaymentSubmissionNotification($paymentId);

// Payment Confirmed
$notificationService->sendPaymentConfirmationNotification($paymentId);

// Payment Rejected
$notificationService->sendPaymentRejectionNotification($paymentId, $rejectionReason);
```

### Payment Reminders

```php
// Send reminder for specific payment schedule
$notificationService->sendPaymentReminderNotification($scheduleId, $daysUntilDue);

// Batch send reminders (used by cron)
$notificationService->sendPaymentReminders();
```

---

## 🔧 Integration Examples

### Example 1: Loan Approval

**File**: `api/loan-applications.php`

```php
function approveApplication($pdo) {
    try {
        $pdo->beginTransaction();
        
        // ... existing approval logic ...
        
        // Update loan status
        $stmt = $pdo->prepare("UPDATE loan_applications SET status = 'Approved' WHERE id = ?");
        $stmt->execute([$applicationId]);
        
        // Create order
        $orderNumber = generateOrderNumber();
        // ... order creation logic ...
        
        $pdo->commit();
        
        // ✅ SEND NOTIFICATION
        require_once dirname(__DIR__) . '/includes/services/NotificationService.php';
        $notificationService = new NotificationService($pdo);
        $notificationService->sendLoanApprovalNotification($applicationId, $orderNumber);
        
        echo json_encode(['success' => true, 'message' => 'Loan approved']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
```

### Example 2: Test Drive Approval

**File**: `pages/test/test_drive_management.php`

```php
case 'approve_request':
    $request_id = $_POST['request_id'];
    $instructor = $_POST['instructor'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    // Generate gate pass
    $gate_pass_number = 'MAG-' . strtoupper(substr(md5(time() . $request_id), 0, 8));
    
    // Update request
    $stmt = $pdo->prepare("
        UPDATE test_drive_requests 
        SET status = 'Approved', 
            approved_at = NOW(), 
            instructor_agent = ?, 
            notes = ?,
            gate_pass_number = ?,
            gatepass_generated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$instructor, $notes, $gate_pass_number, $request_id]);
    
    // ✅ SEND NOTIFICATION
    require_once '../../includes/services/NotificationService.php';
    $notificationService = new NotificationService($pdo);
    $notificationService->sendTestDriveApprovalNotification($request_id);
    
    echo json_encode(['success' => true, 'message' => 'Request approved']);
    break;
```

### Example 3: Payment Confirmation

**File**: `includes/backend/payment_backend.php`

```php
function processPayment($pdo) {
    try {
        $pdo->beginTransaction();
        
        $payment_id = $_POST['payment_id'];
        $action = $_POST['process_action']; // 'approve' or 'reject'
        
        if ($action === 'approve') {
            // Update payment status
            $stmt = $pdo->prepare("UPDATE payment_history SET status = 'Confirmed' WHERE id = ?");
            $stmt->execute([$payment_id]);
            
            // Update payment schedule
            // ... schedule update logic ...
            
            $pdo->commit();
            
            // ✅ SEND NOTIFICATION
            require_once dirname(__DIR__) . '/services/NotificationService.php';
            $notificationService = new NotificationService($pdo);
            $notificationService->sendPaymentConfirmationNotification($payment_id);
            
        } else {
            // Rejection logic
            $rejection_reason = $_POST['rejection_reason'] ?? 'Payment rejected';
            
            $stmt = $pdo->prepare("UPDATE payment_history SET status = 'Failed', rejection_reason = ? WHERE id = ?");
            $stmt->execute([$rejection_reason, $payment_id]);
            
            $pdo->commit();
            
            // ✅ SEND NOTIFICATION
            require_once dirname(__DIR__) . '/services/NotificationService.php';
            $notificationService = new NotificationService($pdo);
            $notificationService->sendPaymentRejectionNotification($payment_id, $rejection_reason);
        }
        
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
```

### Example 4: Payment Reminder Cron

**File**: `includes/cron/payment_reminder_cron.php`

```php
<?php
// Load dependencies
require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/services/NotificationService.php';

try {
    $notificationService = new NotificationService($pdo);
    
    // Send all due reminders
    $result = $notificationService->sendPaymentReminders();
    
    echo "Payment reminders sent: " . $result['sent'] . "\n";
    echo "Failed: " . $result['failed'] . "\n";
    
} catch (Exception $e) {
    error_log("Payment reminder cron error: " . $e->getMessage());
    exit(1);
}
```

---

## 🗄️ Database Queries

### Get Customer Contact Info

```php
// Method used internally by NotificationService
private function getCustomerContactInfo($customerId) {
    $stmt = $this->pdo->prepare("
        SELECT 
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
```

### Get Loan Details

```php
$stmt = $pdo->prepare("
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
$loan = $stmt->fetch(PDO::FETCH_ASSOC);
```

### Get Test Drive Details

```php
$stmt = $pdo->prepare("
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
$testDrive = $stmt->fetch(PDO::FETCH_ASSOC);
```

### Get Payment Details

```php
$stmt = $pdo->prepare("
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
$payment = $stmt->fetch(PDO::FETCH_ASSOC);
```

### Get Upcoming Payment Schedules

```php
$stmt = $pdo->prepare("
    SELECT 
        ps.*,
        o.order_number,
        a.Id as customer_id
    FROM payment_schedule ps
    INNER JOIN orders o ON ps.order_id = o.order_id
    INNER JOIN accounts a ON ps.customer_id = a.Id
    WHERE ps.status = 'Pending'
    AND ps.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY ps.due_date ASC
");
$stmt->execute();
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

---

## 📧 Template Variables

### Email Templates

All email templates receive a `$data` array with relevant information:

**Loan Approval**:
```php
$data = [
    'customer_name' => 'Juan Dela Cruz',
    'loan_id' => 123,
    'order_number' => 'ORD-2024-001',
    'vehicle' => 'Mitsubishi Montero Sport',
    'amount' => 1500000.00,
    'monthly_payment' => 25000.00,
    'term' => 60
];
```

**Test Drive Approval**:
```php
$data = [
    'customer_name' => 'Juan Dela Cruz',
    'request_id' => 456,
    'vehicle' => 'Mitsubishi Xpander',
    'date' => '2024-02-15',
    'time' => '10:00 AM',
    'gate_pass' => 'MAG-ABC12345',
    'location' => 'San Pablo Branch'
];
```

**Payment Confirmation**:
```php
$data = [
    'customer_name' => 'Juan Dela Cruz',
    'payment_number' => 'PAY-2024-001',
    'order_number' => 'ORD-2024-001',
    'amount' => 25000.00,
    'payment_date' => '2024-02-15',
    'payment_method' => 'Bank Transfer'
];
```

### SMS Templates

SMS templates use simple string replacement:

```php
$message = "Congratulations {customer_name}! Your loan for {vehicle} is APPROVED. Order #{order_number} created. Visit us for next steps.";

// Replace variables
$message = str_replace('{customer_name}', $data['customer_name'], $message);
$message = str_replace('{vehicle}', $data['vehicle'], $message);
$message = str_replace('{order_number}', $data['order_number'], $message);
```

---

## 🐛 Error Handling

### Graceful Degradation

```php
try {
    $notificationService->sendLoanApprovalNotification($loanId, $orderNumber);
} catch (Exception $e) {
    // Log error but don't fail the main operation
    error_log("Notification error: " . $e->getMessage());
    // Continue with success response
}
```

### Checking Notification Status

```php
// Query notification logs
$stmt = $pdo->prepare("
    SELECT * FROM notification_logs 
    WHERE customer_id = ? 
    AND notification_type = 'loan_approval'
    ORDER BY sent_at DESC 
    LIMIT 1
");
$stmt->execute([$customerId]);
$log = $stmt->fetch(PDO::FETCH_ASSOC);

if ($log['email_status'] === 'failed') {
    // Handle email failure
}

if ($log['sms_status'] === 'failed') {
    // Handle SMS failure
}
```

---

## 🧪 Testing

### Manual Testing

```php
// Test notification directly
require_once 'includes/services/NotificationService.php';
$notificationService = new NotificationService($pdo);

// Test with real data
$notificationService->sendLoanApprovalNotification(123, 'ORD-2024-001');

// Check logs
$stmt = $pdo->query("SELECT * FROM notification_logs ORDER BY sent_at DESC LIMIT 1");
$log = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($log);
```

### Unit Testing

```php
// Mock PDO for testing
$mockPdo = $this->createMock(PDO::class);
$notificationService = new NotificationService($mockPdo);

// Test methods
$this->assertTrue($notificationService->sendEmail(...));
$this->assertTrue($notificationService->sendSMS(...));
```

---

## 📊 Monitoring

### Check Notification Logs

```sql
-- Recent notifications
SELECT * FROM notification_logs 
ORDER BY sent_at DESC 
LIMIT 100;

-- Failed notifications
SELECT * FROM notification_logs 
WHERE email_status = 'failed' OR sms_status = 'failed'
ORDER BY sent_at DESC;

-- Notifications by type
SELECT 
    notification_type,
    COUNT(*) as total,
    SUM(CASE WHEN email_status = 'sent' THEN 1 ELSE 0 END) as email_sent,
    SUM(CASE WHEN sms_status = 'sent' THEN 1 ELSE 0 END) as sms_sent
FROM notification_logs
GROUP BY notification_type;
```

---

## 🔐 Security Notes

- ✅ API credentials stored in `.env` file
- ✅ Customer data validated before sending
- ✅ SQL injection prevented with prepared statements
- ✅ Error messages don't expose sensitive data
- ✅ Logs don't contain customer passwords or tokens

---

## 📞 Need Help?

- Review `plan.md` for detailed architecture
- Check `IMPLEMENTATION_ROADMAP.md` for step-by-step guide
- See `NOTIFICATION_SYSTEM_SUMMARY.md` for overview
- Contact development team for support

---

**Happy coding! 🚀**

