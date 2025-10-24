# Automatic Email & SMS Notification System - Implementation Plan

## 📋 Executive Summary

This plan outlines the implementation of a **centralized, automatic notification system** that sends email and SMS updates for:
- **Loan Applications** (Approval/Rejection)
- **Test Drive Bookings** (Confirmation/Rejection)
- **Payment Submissions** (Confirmation/Rejection)
- **Payment Due Date Reminders** (Upcoming payments)

The system will be **event-driven**, triggering notifications automatically when status changes occur, ensuring customers receive timely updates even when offline (via SMS).

---

## 🎯 Goals

1. **Centralized Architecture**: Single notification service reused across all modules
2. **Automatic Triggers**: No manual intervention required
3. **Multi-Channel**: Both Email (online) and SMS (offline) delivery
4. **Reliable**: Proper error handling and logging
5. **Maintainable**: Template-based messages for easy updates

---

## 📊 Current System Analysis

### Existing Infrastructure ✅

#### Email System
- **API**: `api/send_email_api.php`
- **Backend**: `includes/backend/GmailMailer.php` (PHPMailer with Gmail SMTP)
- **Configuration**: `config/email_config.php` (loads from `.env`)
- **Logging**: `email_logs` table
- **Templates**: `includes/email_templates/` directory

#### SMS System
- **API**: `api/send_sms.php`
- **Backend**: `includes/backend/PhilSmsSender.php` (PhilSMS API)
- **Configuration**: `config/philsms.php` (loads from `.env`)
- **Logging**: `sms_logs` table

#### Customer Data
- **Tables**: `accounts` (Email), `customer_information` (mobile_number)
- **Relationship**: `customer_information.account_id` → `accounts.Id`
- **Query Pattern**: 
  ```sql
  SELECT a.Email, ci.mobile_number, ci.firstname, ci.lastname
  FROM accounts a
  INNER JOIN customer_information ci ON a.Id = ci.account_id
  WHERE a.Id = ?
  ```

---

## 🔄 Workflows Identified

### 1. Loan Application Workflow

**Status Flow**: `Pending` → `Under Review` → `Approved`/`Rejected`

**Current Implementation**:
- **File**: `api/loan-applications.php`
- **Functions**: 
  - `approveApplication()` - Line 894-944
  - `approveApplicationEnhanced()` - Line 1084-1147
  - `rejectApplication()` - Line 1241-1270
- **Current Notifications**: In-app only (via `notification_api.php`)

**Trigger Points**:
- ✅ Loan Approved → Send Email + SMS
- ✅ Loan Rejected → Send Email + SMS
- ✅ Status Changed → Send Email + SMS

**Customer Data Available**:
- `loan_applications.customer_id` (references `accounts.Id`)
- Can join to get email and mobile number

---

### 2. Test Drive Booking Workflow

**Status Flow**: `Pending` → `Approved`/`Rejected` → `Completed`

**Current Implementation**:
- **File**: `pages/test/test_drive_management.php`
- **Functions**:
  - `approve_request` - Line 55-87
  - `reject_request` - Line 89-101
  - `complete_request` - Line 103-115
- **Current Notifications**: In-app only

**Trigger Points**:
- ✅ Test Drive Approved → Send Email + SMS (with gate pass details)
- ✅ Test Drive Rejected → Send Email + SMS
- ✅ Test Drive Completed → Send Email + SMS (thank you message)

**Customer Data Available**:
- `test_drive_requests.account_id` (references `accounts.Id`)
- Can join to get email and mobile number

---

### 3. Payment Submission Workflow

**Status Flow**: `Pending` → `Confirmed`/`Failed`

**Current Implementation**:
- **File**: `includes/backend/payment_backend.php`
- **Function**: `processPayment()` - Line 352-390
- **File**: `includes/api/payment_approval_api.php`
- **Function**: `approvePayment()` - Line 437-520
- **Current Notifications**: In-app only (via `createPaymentNotification()`)

**Trigger Points**:
- ✅ Payment Submitted → Send Email + SMS (acknowledgment)
- ✅ Payment Confirmed → Send Email + SMS (receipt)
- ✅ Payment Rejected → Send Email + SMS (with reason)

**Customer Data Available**:
- `payment_history.customer_id` (references `accounts.Id`)
- Can join to get email and mobile number

---

### 4. Payment Due Date Reminders

**Status Flow**: `payment_schedule.status` = `Pending` with upcoming `due_date`

**Current Implementation**:
- **Table**: `payment_schedule` (tracks expected payments)
- **No automated reminders currently exist**

**Trigger Points**:
- ✅ 7 days before due date → Send Email + SMS
- ✅ 3 days before due date → Send Email + SMS
- ✅ 1 day before due date → Send Email + SMS
- ✅ On due date → Send Email + SMS
- ✅ Overdue (1 day after) → Send Email + SMS

**Implementation Method**: Scheduled cron job or manual trigger

**Customer Data Available**:
- `payment_schedule.customer_id` (references `accounts.Id`)
- Can join to get email and mobile number

---

## 🏗️ Architecture Design

### Centralized Notification Service

**File**: `includes/services/NotificationService.php`

**Responsibilities**:
1. Fetch customer contact information (email + mobile)
2. Load appropriate message templates
3. Send email via existing `GmailMailer`
4. Send SMS via existing `PhilSmsSender`
5. Log all notifications
6. Handle errors gracefully

**Key Methods**:
```php
class NotificationService {
    // Main notification method
    public function sendNotification($customerId, $type, $data)
    
    // Specific notification types
    public function sendLoanApprovalNotification($loanId)
    public function sendLoanRejectionNotification($loanId, $reason)
    public function sendTestDriveApprovalNotification($requestId)
    public function sendTestDriveRejectionNotification($requestId, $reason)
    public function sendPaymentConfirmationNotification($paymentId)
    public function sendPaymentRejectionNotification($paymentId, $reason)
    public function sendPaymentReminderNotification($scheduleId, $daysUntilDue)
    
    // Helper methods
    private function getCustomerContactInfo($customerId)
    private function sendEmail($to, $subject, $body)
    private function sendSMS($to, $message)
    private function logNotification($type, $customerId, $status)
}
```

---

## 📝 Message Templates

### Template Structure

**Email Templates**: `includes/email_templates/notifications/`
- `loan_approval.php`
- `loan_rejection.php`
- `test_drive_approval.php`
- `test_drive_rejection.php`
- `payment_confirmation.php`
- `payment_rejection.php`
- `payment_reminder.php`

**SMS Templates**: `includes/sms_templates/`
- Simple text-based templates (160 chars max for single segment)
- Variables: `{customer_name}`, `{loan_id}`, `{amount}`, `{due_date}`, etc.

**Template Format**:
```php
// Email Template Example
function getLoanApprovalEmailTemplate($data) {
    // $data = ['customer_name', 'loan_id', 'order_number', 'vehicle', 'amount']
    return [
        'subject' => 'Loan Application Approved - Mitsubishi Motors',
        'body' => '...' // HTML content
    ];
}

// SMS Template Example
function getLoanApprovalSMSTemplate($data) {
    return "Congratulations {customer_name}! Your loan application #{loan_id} for {vehicle} has been APPROVED. Order #{order_number} created. Visit our showroom for next steps.";
}
```

---

## 🔧 Implementation Steps

### Phase 1: Core Service (Week 1)

**Task 1.1**: Create NotificationService class
- File: `includes/services/NotificationService.php`
- Implement customer data fetching
- Implement email/SMS sending wrappers
- Add error handling and logging

**Task 1.2**: Create notification logging table
- File: `includes/database/create_notification_logs_table.sql`
- Track all sent notifications (email + SMS)
- Fields: id, customer_id, type, channel, status, sent_at, error_message

**Task 1.3**: Create message templates
- Email templates (HTML)
- SMS templates (plain text)

---

### Phase 2: Loan Application Notifications (Week 2)

**Task 2.1**: Integrate with loan approval workflow
- Modify: `api/loan-applications.php`
- Add NotificationService calls in:
  - `approveApplication()` (line ~940)
  - `approveApplicationEnhanced()` (line ~1140)
  - `rejectApplication()` (line ~1260)

**Task 2.2**: Test loan notifications
- Test approval flow
- Test rejection flow
- Verify email delivery
- Verify SMS delivery

---

### Phase 3: Test Drive Notifications (Week 2)

**Task 3.1**: Integrate with test drive workflow
- Modify: `pages/test/test_drive_management.php`
- Add NotificationService calls in:
  - `approve_request` (line ~73)
  - `reject_request` (line ~99)

**Task 3.2**: Test test drive notifications
- Test approval flow
- Test rejection flow

---

### Phase 4: Payment Notifications (Week 3)

**Task 4.1**: Integrate with payment workflow
- Modify: `includes/backend/payment_backend.php`
- Modify: `includes/api/payment_approval_api.php`
- Add NotificationService calls in:
  - Payment submission (acknowledgment)
  - Payment approval (line ~377)
  - Payment rejection (line ~377)

**Task 4.2**: Test payment notifications
- Test submission acknowledgment
- Test approval flow
- Test rejection flow

---

### Phase 5: Payment Reminders (Week 4)

**Task 5.1**: Create reminder scheduler
- File: `includes/cron/payment_reminder_cron.php`
- Query upcoming due dates
- Send reminders based on schedule

**Task 5.2**: Setup cron job or manual trigger
- Option A: Server cron job (daily at 9 AM)
- Option B: Manual trigger page for admin
- Option C: Triggered on admin dashboard load

**Task 5.3**: Test reminder system
- Test 7-day reminder
- Test 3-day reminder
- Test 1-day reminder
- Test due date reminder
- Test overdue reminder

---

## 📁 Files to Create

### New Files
1. `includes/services/NotificationService.php` - Core service
2. `includes/database/create_notification_logs_table.sql` - Logging table
3. `includes/email_templates/notifications/loan_approval.php`
4. `includes/email_templates/notifications/loan_rejection.php`
5. `includes/email_templates/notifications/test_drive_approval.php`
6. `includes/email_templates/notifications/test_drive_rejection.php`
7. `includes/email_templates/notifications/payment_confirmation.php`
8. `includes/email_templates/notifications/payment_rejection.php`
9. `includes/email_templates/notifications/payment_reminder.php`
10. `includes/sms_templates/templates.php` - All SMS templates
11. `includes/cron/payment_reminder_cron.php` - Reminder scheduler
12. `api/trigger_payment_reminders.php` - Manual trigger endpoint

### Files to Modify
1. `api/loan-applications.php` - Add notification calls
2. `pages/test/test_drive_management.php` - Add notification calls
3. `includes/backend/payment_backend.php` - Add notification calls
4. `includes/api/payment_approval_api.php` - Add notification calls

---

## 🗄️ Database Schema

### New Table: `notification_logs`

```sql
CREATE TABLE IF NOT EXISTS notification_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    channel ENUM('email', 'sms', 'both') NOT NULL,
    email_status ENUM('sent', 'failed', 'skipped') DEFAULT 'skipped',
    sms_status ENUM('sent', 'failed', 'skipped') DEFAULT 'skipped',
    email_recipient VARCHAR(255),
    sms_recipient VARCHAR(20),
    subject VARCHAR(500),
    message_preview TEXT,
    error_message TEXT,
    related_id INT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_customer_id (customer_id),
    INDEX idx_type (notification_type),
    INDEX idx_sent_at (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## ✅ Success Criteria

1. ✅ All loan approvals/rejections trigger email + SMS
2. ✅ All test drive approvals/rejections trigger email + SMS
3. ✅ All payment confirmations/rejections trigger email + SMS
4. ✅ Payment reminders sent automatically at scheduled intervals
5. ✅ All notifications logged in database
6. ✅ Error handling prevents system crashes
7. ✅ Templates are easily editable
8. ✅ Service is reusable across all modules

---

## 🚀 Deployment Checklist

- [ ] Create NotificationService class
- [ ] Create notification_logs table
- [ ] Create all email templates
- [ ] Create all SMS templates
- [ ] Integrate with loan workflow
- [ ] Integrate with test drive workflow
- [ ] Integrate with payment workflow
- [ ] Create payment reminder scheduler
- [ ] Test all notification types
- [ ] Setup cron job (if applicable)
- [ ] Document usage for future developers
- [ ] Train admin users on notification system

---

## 📞 Contact Information Sources

### Customer Email
```sql
SELECT a.Email 
FROM accounts a 
WHERE a.Id = :customer_id
```

### Customer Mobile Number
```sql
SELECT ci.mobile_number 
FROM customer_information ci 
WHERE ci.account_id = :customer_id
```

### Combined Query
```sql
SELECT 
    a.Email,
    ci.mobile_number,
    ci.firstname,
    ci.lastname
FROM accounts a
INNER JOIN customer_information ci ON a.Id = ci.account_id
WHERE a.Id = :customer_id
```

---

## 🔐 Security Considerations

1. **API Credentials**: Already secured in `.env` file
2. **Rate Limiting**: Consider implementing for SMS to prevent abuse
3. **Opt-out**: Future enhancement - allow customers to opt-out of SMS
4. **Data Privacy**: Only send notifications to verified customers
5. **Error Logging**: Don't expose sensitive data in logs

---

## 📈 Future Enhancements

1. **Notification Preferences**: Let customers choose email/SMS/both
2. **Delivery Tracking**: Track email opens and SMS delivery status
3. **Retry Logic**: Retry failed notifications
4. **Batch Processing**: Send multiple notifications efficiently
5. **Admin Dashboard**: View notification history and statistics
6. **Template Editor**: Allow admins to edit templates via UI

---

---

## 📋 Quick Reference: Integration Points

### Loan Applications
**File**: `api/loan-applications.php`

**Integration Point 1** - Approve Application (Line ~940):
```php
// After successful approval and order creation
require_once dirname(__DIR__) . '/includes/services/NotificationService.php';
$notificationService = new NotificationService($pdo);
$notificationService->sendLoanApprovalNotification($applicationId, $orderNumber);
```

**Integration Point 2** - Reject Application (Line ~1260):
```php
// After successful rejection
require_once dirname(__DIR__) . '/includes/services/NotificationService.php';
$notificationService = new NotificationService($pdo);
$notificationService->sendLoanRejectionNotification($applicationId, $notes);
```

---

### Test Drive Requests
**File**: `pages/test/test_drive_management.php`

**Integration Point 1** - Approve Request (Line ~73):
```php
// After successful approval
require_once '../../includes/services/NotificationService.php';
$notificationService = new NotificationService($pdo);
$notificationService->sendTestDriveApprovalNotification($request_id);
```

**Integration Point 2** - Reject Request (Line ~99):
```php
// After successful rejection
require_once '../../includes/services/NotificationService.php';
$notificationService = new NotificationService($pdo);
$notificationService->sendTestDriveRejectionNotification($request_id, $notes);
```

---

### Payment Processing
**File**: `includes/backend/payment_backend.php`

**Integration Point 1** - Approve Payment (Line ~377):
```php
// After successful approval
require_once dirname(__DIR__) . '/services/NotificationService.php';
$notificationService = new NotificationService($pdo);
$notificationService->sendPaymentConfirmationNotification($payment_id);
```

**Integration Point 2** - Reject Payment (Line ~377):
```php
// After successful rejection
require_once dirname(__DIR__) . '/services/NotificationService.php';
$notificationService = new NotificationService($pdo);
$notificationService->sendPaymentRejectionNotification($payment_id, $rejection_reason);
```

---

### Payment Reminders
**File**: `includes/cron/payment_reminder_cron.php` (NEW)

**Cron Job Schedule**: Daily at 9:00 AM
```bash
0 9 * * * php /path/to/Mitsubishi/includes/cron/payment_reminder_cron.php
```

**Manual Trigger**: `api/trigger_payment_reminders.php` (NEW)
- Can be called by admin dashboard
- Can be triggered manually for testing

---

## 📊 Notification Types Summary

| Type | Trigger | Email | SMS | Template Files |
|------|---------|-------|-----|----------------|
| Loan Approved | Status → Approved | ✅ | ✅ | `loan_approval.php` |
| Loan Rejected | Status → Rejected | ✅ | ✅ | `loan_rejection.php` |
| Test Drive Approved | Status → Approved | ✅ | ✅ | `test_drive_approval.php` |
| Test Drive Rejected | Status → Rejected | ✅ | ✅ | `test_drive_rejection.php` |
| Payment Confirmed | Status → Confirmed | ✅ | ✅ | `payment_confirmation.php` |
| Payment Rejected | Status → Failed | ✅ | ✅ | `payment_rejection.php` |
| Payment Reminder (7d) | Due date - 7 days | ✅ | ✅ | `payment_reminder.php` |
| Payment Reminder (3d) | Due date - 3 days | ✅ | ✅ | `payment_reminder.php` |
| Payment Reminder (1d) | Due date - 1 day | ✅ | ✅ | `payment_reminder.php` |
| Payment Due Today | Due date = today | ✅ | ✅ | `payment_reminder.php` |
| Payment Overdue | Due date + 1 day | ✅ | ✅ | `payment_reminder.php` |

---

## 🎨 Sample Message Templates

### Loan Approval Email (Subject)
```
🎉 Loan Application Approved - Mitsubishi Motors
```

### Loan Approval SMS (160 chars)
```
Congratulations {name}! Your loan for {vehicle} is APPROVED. Order #{order} created. Amount: ₱{amount}. Visit us for next steps. - Mitsubishi Motors
```

### Test Drive Approval Email (Subject)
```
✅ Test Drive Approved - Gate Pass #{gate_pass}
```

### Test Drive Approval SMS (160 chars)
```
Test drive APPROVED! {vehicle} on {date} at {time}. Gate Pass: {gate_pass}. Bring valid ID. See you soon! - Mitsubishi Motors
```

### Payment Confirmation Email (Subject)
```
✅ Payment Confirmed - Receipt #{payment_number}
```

### Payment Confirmation SMS (160 chars)
```
Payment CONFIRMED! ₱{amount} received for Order #{order}. Receipt: {payment_number}. Thank you! - Mitsubishi Motors
```

### Payment Reminder Email (Subject)
```
⏰ Payment Reminder - Due in {days} days
```

### Payment Reminder SMS (160 chars)
```
Reminder: Payment #{payment_num} of ₱{amount} due on {due_date} ({days} days). Order #{order}. Pay early to avoid penalties. - Mitsubishi Motors
```

---

## 🔍 Testing Checklist

### Unit Testing
- [ ] NotificationService instantiation
- [ ] Customer contact info retrieval
- [ ] Email template loading
- [ ] SMS template loading
- [ ] Email sending (mock)
- [ ] SMS sending (mock)
- [ ] Notification logging

### Integration Testing
- [ ] Loan approval → Email + SMS sent
- [ ] Loan rejection → Email + SMS sent
- [ ] Test drive approval → Email + SMS sent
- [ ] Test drive rejection → Email + SMS sent
- [ ] Payment confirmation → Email + SMS sent
- [ ] Payment rejection → Email + SMS sent
- [ ] Payment reminder → Email + SMS sent

### End-to-End Testing
- [ ] Submit loan → Approve → Verify customer receives email + SMS
- [ ] Submit test drive → Approve → Verify customer receives email + SMS
- [ ] Submit payment → Approve → Verify customer receives email + SMS
- [ ] Trigger payment reminder → Verify customer receives email + SMS

### Error Handling Testing
- [ ] Customer has no email → SMS only sent
- [ ] Customer has no mobile → Email only sent
- [ ] Email fails → SMS still sent + error logged
- [ ] SMS fails → Email still sent + error logged
- [ ] Both fail → Error logged, system continues

---

## 📞 Support & Maintenance

### Monitoring
- Check `notification_logs` table daily for failed notifications
- Monitor `email_logs` and `sms_logs` for delivery issues
- Review error logs for system issues

### Common Issues
1. **Email not delivered**: Check Gmail SMTP credentials in `.env`
2. **SMS not delivered**: Check PhilSMS API token in `.env`
3. **Customer not receiving**: Verify email/mobile in `accounts` and `customer_information`
4. **Template errors**: Check template file syntax

### Updating Templates
1. Navigate to `includes/email_templates/notifications/`
2. Edit the appropriate template file
3. Test changes in staging environment
4. Deploy to production

---

**End of Plan**

