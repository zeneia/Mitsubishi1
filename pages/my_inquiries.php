<?php
session_start();

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Use the common app initializer (provides $pdo mapped to $connect)
include_once(dirname(__DIR__) . '/includes/init.php');

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Customer') {
    header("Location: login.php");
    exit;
}

// CSRF token bootstrap
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}

// AJAX detection helper
$isAjax = (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')
    || (isset($_POST['as_json']) && $_POST['as_json'] == '1');

// JSON responder
if (!function_exists('send_json_and_exit')) {
    function send_json_and_exit(array $payload, int $status = 200): void {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
            header('Vary: Accept');
            http_response_code($status);
        }
        echo json_encode($payload);
        exit;
    }
}

// Handle Mark-as-Read POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    // Validate CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        if ($isAjax) { send_json_and_exit(['success' => false, 'message' => 'Invalid CSRF token'], 403); }
        $_SESSION['flash_message'] = 'Security check failed.';
        $_SESSION['flash_type'] = 'error';
        header('Location: my_inquiries.php');
        exit;
    }

    // Validate inquiry id
    $inquiryId = filter_input(INPUT_POST, 'inquiry_id', FILTER_VALIDATE_INT);
    if (!$inquiryId || $inquiryId < 1) {
        if ($isAjax) { send_json_and_exit(['success' => false, 'message' => 'Invalid inquiry id'], 400); }
        $_SESSION['flash_message'] = 'Invalid inquiry id.';
        $_SESSION['flash_type'] = 'error';
        header('Location: my_inquiries.php');
        exit;
    }

    try {
        // Ensure ownership
        $stmtChk = $connect->prepare("SELECT Id, AccountId, COALESCE(is_read,0) AS is_read, read_at FROM inquiries WHERE Id = ?");
        $stmtChk->execute([$inquiryId]);
        $row = $stmtChk->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            if ($isAjax) { send_json_and_exit(['success' => false, 'message' => 'Inquiry not found'], 404); }
            $_SESSION['flash_message'] = 'Inquiry not found.';
            $_SESSION['flash_type'] = 'error';
            header('Location: my_inquiries.php');
            exit;
        }
        if ((int)$row['AccountId'] !== (int)$_SESSION['user_id']) {
            if ($isAjax) { send_json_and_exit(['success' => false, 'message' => 'Forbidden'], 403); }
            $_SESSION['flash_message'] = 'You are not allowed to modify this inquiry.';
            $_SESSION['flash_type'] = 'error';
            header('Location: my_inquiries.php');
            exit;
        }

        // Conditional update (no race)
        $stmtUpd = $connect->prepare("
            UPDATE inquiries
            SET is_read = 1, read_at = NOW()
            WHERE Id = ? AND AccountId = ? AND (is_read = 0 OR is_read IS NULL)
        ");
        $stmtUpd->execute([$inquiryId, $_SESSION['user_id']]);

        // Fetch current state
        $stmtGet = $connect->prepare("SELECT COALESCE(is_read,0) AS is_read, read_at FROM inquiries WHERE Id = ?");
        $stmtGet->execute([$inquiryId]);
        $state = $stmtGet->fetch(PDO::FETCH_ASSOC);
        $isReadNow = isset($state['is_read']) ? (int)$state['is_read'] : 0;
        $readAt = $state['read_at'] ?? null;

        // Unread count
        $stmtCnt = $connect->prepare("SELECT COUNT(*) FROM inquiries WHERE AccountId = ? AND (is_read = 0 OR is_read IS NULL)");
        $stmtCnt->execute([$_SESSION['user_id']]);
        $unreadCount = (int)$stmtCnt->fetchColumn();

        if ($isAjax) {
            $readAtIso = $readAt ? (new DateTime($readAt))->format(DateTime::ATOM) : null;
            send_json_and_exit([
                'success' => true,
                'inquiry_id' => (int)$inquiryId,
                'unread_count' => $unreadCount,
                'is_read' => $isReadNow,
                'read_at' => $readAtIso,
            ]);
        } else {
            $_SESSION['flash_message'] = 'Inquiry marked as read.';
            $_SESSION['flash_type'] = 'success';
            header('Location: my_inquiries.php');
            exit;
        }
    } catch (PDOException $e) {
        error_log('Mark as read DB error: ' . $e->getMessage());
        if ($isAjax) {
            send_json_and_exit(['success' => false, 'message' => 'Database error'], 500);
        } else {
            $_SESSION['flash_message'] = 'An error occurred. Please try again.';
            $_SESSION['flash_type'] = 'error';
            header('Location: my_inquiries.php');
            exit;
        }
    }
}

// Compute unread count for badge on GET
try {
    $stmtUnread = $connect->prepare("SELECT COUNT(*) FROM inquiries WHERE AccountId = ? AND (is_read = 0 OR is_read IS NULL)");
    $stmtUnread->execute([$_SESSION['user_id']]);
    $unread_count = (int)$stmtUnread->fetchColumn();
} catch (PDOException $e) {
    error_log('Unread count error: ' . $e->getMessage());
    $unread_count = 0;
}

// Helper: compute amortization schedule
if (!function_exists('computeAmortization')) {
    function computeAmortization(float $principal, float $annualRatePct, int $termMonths, string $startDate, ?float $fixedMonthlyPayment = null): array {
        $schedule = [];
        if ($principal <= 0 || $termMonths <= 0) {
            return $schedule;
        }
        $r = max(0.0, $annualRatePct) / 100.0 / 12.0; // monthly rate
        // If monthly payment not provided, compute using standard amortization formula
        if ($fixedMonthlyPayment === null || $fixedMonthlyPayment <= 0) {
            if ($r > 0) {
                $fixedMonthlyPayment = $principal * ($r * pow(1 + $r, $termMonths)) / (pow(1 + $r, $termMonths) - 1);
            } else {
                $fixedMonthlyPayment = $principal / $termMonths;
            }
        }
        $balance = $principal;
        $date = new DateTime($startDate ?: 'now');
        $dateIter = clone $date;
        for ($n = 1; $n <= $termMonths; $n++) {
            // Payment date: one month increments from application date
            $dateIter->modify('+1 month');
            $interest = $r > 0 ? $balance * $r : 0.0;
            $principalPortion = $fixedMonthlyPayment - $interest;
            // On the last scheduled payment, adjust to fully clear any residual balance
            if ($n === $termMonths) {
                $principalPortion = $balance;
                $fixedPaymentThis = $principalPortion + $interest; // last payment may differ slightly
                $balance = 0.0;
            } else {
                if ($principalPortion > $balance) {
                    // Early payoff adjustment (rare due to rounding)
                    $principalPortion = $balance;
                    $fixedPaymentThis = $principalPortion + $interest;
                    $balance = 0.0;
                } else {
                    $fixedPaymentThis = $fixedMonthlyPayment;
                    $balance = max(0.0, $balance - $principalPortion);
                }
            }
            $schedule[] = [
                'n' => $n,
                'date' => $dateIter->format('M j, Y'),
                'payment' => round($fixedPaymentThis, 2),
                'principal' => round($principalPortion, 2),
                'interest' => round($interest, 2),
                'balance' => round($balance, 2),
            ];
            if ($balance <= 0.000001) { // Stop early if fully paid due to rounding
                $balance = 0.0;
                // If we paid off early before reaching termMonths, that's fine; break.
                if ($n < $termMonths) {
                    break;
                }
            }
        }
        return $schedule;
    }
}

// Check if customer information is filled out
$stmt_check_info = $connect->prepare("SELECT cusID FROM customer_information WHERE account_id = ?");
$stmt_check_info->execute([$_SESSION['user_id']]);
if ($stmt_check_info->rowCount() == 0) {
    header("Location: verification.php");
    exit;
}

// Fetch user details
$stmt = $connect->prepare("SELECT * FROM accounts WHERE Id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$displayName = !empty($user['FirstName']) ? $user['FirstName'] : $user['Username'];

// Prepare profile image HTML
$profile_image_html = '';
if (!empty($user['ProfileImage'])) {
    $imageData = base64_encode($user['ProfileImage']);
    $imageMimeType = 'image/jpeg';
    $profile_image_html = '<img src="data:' . $imageMimeType . ';base64,' . $imageData . '" alt="User Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">';
} else {
    // Show initial if no profile image
    $profile_image_html = strtoupper(substr($displayName, 0, 1));
}

// Fetch customer's inquiries with responses
try {
    $stmt_inquiries = $connect->prepare("
        SELECT
            i.*,
            (SELECT COUNT(*) FROM inquiry_responses ir WHERE ir.InquiryId = i.Id) as response_count,
            (SELECT ir.ResponseDate FROM inquiry_responses ir WHERE ir.InquiryId = i.Id ORDER BY ir.ResponseDate DESC LIMIT 1) as last_response_date
        FROM inquiries i
        WHERE i.AccountId = ?
        ORDER BY COALESCE(i.is_read, 0) ASC, i.InquiryDate DESC
    ");
    $stmt_inquiries->execute([$_SESSION['user_id']]);
    $inquiries = $stmt_inquiries->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $inquiries = [];
    error_log("Database error: " . $e->getMessage());
}

// Fetch customer's loan applications
try {
    $stmt_loans = $connect->prepare("
        SELECT 
            la.*, 
            v.model_name as vehicle_model, 
            v.variant as vehicle_variant
        FROM loan_applications la
        LEFT JOIN vehicles v ON la.vehicle_id = v.id
        WHERE la.customer_id = ?
        ORDER BY la.application_date DESC
    ");
    $stmt_loans->execute([$_SESSION['user_id']]);
    $loan_applications = $stmt_loans->fetchAll(PDO::FETCH_ASSOC);
    
    // Log the number of loan applications found for debugging
    error_log("Found " . count($loan_applications) . " loan applications for user " . $_SESSION['user_id']);
} catch (PDOException $e) {
    $loan_applications = [];
    error_log("Database error fetching loan applications: " . $e->getMessage());
}

// Fetch customer's test drive requests with explicit fields and aliases
try {
    // Prefer $pdo from includes/init.php; fallback to $connect
    $dbh = isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO ? $GLOBALS['pdo'] : (isset($connect) ? $connect : null);
    if (!$dbh) { throw new PDOException('No database handle available'); }

    $sql_tdr = "
        SELECT 
            tdr.id,
            tdr.account_id,
            tdr.vehicle_id,
            tdr.gate_pass_number,
            tdr.customer_name,
            tdr.mobile_number,
            tdr.selected_date AS scheduled_date,
            tdr.selected_time_slot AS scheduled_time,
            tdr.test_drive_location,
            tdr.instructor_agent,
            tdr.status AS test_drive_status,
            tdr.requested_at AS created_at,
            tdr.approved_at,
            tdr.notes,
            v.model_name AS vehicle_model,
            v.variant AS vehicle_variant,
            v.main_image AS vehicle_image
        FROM test_drive_requests tdr
        LEFT JOIN vehicles v ON tdr.vehicle_id = v.id
        WHERE tdr.account_id = ?
        ORDER BY tdr.requested_at DESC
    ";
    $stmt_test_drives = $dbh->prepare($sql_tdr);
    $stmt_test_drives->execute([$_SESSION['user_id']]);
    $test_drives = $stmt_test_drives->fetchAll(PDO::FETCH_ASSOC);

    // Log the number of test drives found
    error_log("Found " . count($test_drives) . " test drives for user " . $_SESSION['user_id']);
    error_log("Test drives data (trimmed): " . print_r(array_map(function($td){ 
        unset($td['notes']); 
        return $td; 
    }, $test_drives), true));
    // Additional diagnostic: total count directly from table using the same handle
    try {
        $stmt_cnt = $dbh->prepare("SELECT COUNT(*) FROM test_drive_requests WHERE account_id = ?");
        $stmt_cnt->execute([$_SESSION['user_id']]);
        $td_total_count = (int)$stmt_cnt->fetchColumn();
        error_log("Diagnostic: test_drive_requests COUNT for user " . $_SESSION['user_id'] . " = " . $td_total_count);
    } catch (Exception $ie) {
        error_log("Diagnostic COUNT failed: " . $ie->getMessage());
        $td_total_count = null;
    }
} catch (PDOException $e) {
    $test_drives = [];
    error_log("Database error fetching test drives: " . $e->getMessage());
}

// Function to get responses for a specific inquiry
function getInquiryResponses($connect, $inquiryId) {
    try {
        $stmt = $connect->prepare("
            SELECT 
                ir.*,
                a.FirstName,
                a.LastName,
                a.Username
            FROM inquiry_responses ir
            LEFT JOIN accounts a ON ir.RespondedBy = a.Id
            WHERE ir.InquiryId = ?
            ORDER BY ir.ResponseDate ASC
        ");
        $stmt->execute([$inquiryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        return [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Inquiries - Mitsubishi Motors</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', 'Segoe UI', sans-serif; }
        body { background: #ffffff; min-height: 100vh; color: white; }
        
        .header { background: #000000; padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; backdrop-filter: blur(20px); border-bottom: 1px solid rgba(255, 215, 0, 0.2); position: relative; z-index: 10; }
        .logo-section { display: flex; align-items: center; gap: 20px; }
        .logo { width: 60px; height: auto; filter: drop-shadow(0 0 10px rgba(255, 215, 0, 0.3)); }
        .brand-text { font-size: 1.4rem; font-weight: 700; background: linear-gradient(45deg, #ffd700, #ffed4e); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .user-section { display: flex; align-items: center; gap: 20px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(45deg, #ffd700, #ffed4e); display: flex; align-items: center; justify-content: center; font-weight: bold; color: #b80000; font-size: 1.2rem; }
        .welcome-text { font-size: 1rem; font-weight: 500; }
        .logout-btn { background: linear-gradient(45deg, #d60000, #b30000); color: white; border: none; padding: 12px 24px; border-radius: 25px; cursor: pointer; font-size: 0.9rem; font-weight: 600; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(214, 0, 0, 0.3); }
        .logout-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(214, 0, 0, 0.5); }

        .container { max-width: 1200px; margin: 0 auto; padding: 30px 20px; position: relative; z-index: 5; }
        .back-btn { display: inline-block; margin-bottom: 20px; background: #E60012; color: #ffffff; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s ease; font-size: 0.9rem; }
        .back-btn:hover { background: #ffd700; color: #1a1a1a; }

        .page-header { text-align: center; margin-bottom: 40px; }
        .page-title { font-size: 2.5rem; font-weight: 800; color: #E60012; margin-bottom: 10px; }
        .page-subtitle { color: #000000; font-size: 1.1rem; }

        .inquiries-container { display: grid; gap: 25px; }
        .inquiry-card { background: #8080803a; border-radius: 16px; backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3); overflow: hidden; transition: all 0.3s ease; }
        .inquiry-card:hover { transform: translateY(-2px); box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4); }

        .inquiry-header { padding: 20px; border-bottom: 1px solid rgba(0, 0, 0, 0.1); }
        .inquiry-id { color: #E60012; font-weight: 600; font-size: 0.9rem; margin-bottom: 5px; }
        .inquiry-vehicle { color: #000000; font-size: 1.2rem; font-weight: 600; margin-bottom: 8px; }
        .inquiry-date { color: rgba(0, 0, 0, 0.6); font-size: 0.9rem; }

        .inquiry-status { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 0.8rem; font-weight: 600; margin-top: 8px; }
        .status-new { background: rgba(255, 193, 7, 1); color: #000000; border: 1px solid rgba(255, 193, 7, 0.3); }
        .status-responded { background: rgba(40, 167, 70, 1); color: #000000; border: 1px solid rgba(40, 167, 69, 0.3); }

        .inquiry-body { padding: 20px; }
        .inquiry-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .detail-label { color: rgba(0, 0, 0, 0.6); font-size: 0.8rem; display: block; margin-bottom: 3px; }
        .detail-value { color: #000000; font-weight: 500; }

        .inquiry-comments { margin-top: 15px; }
        .comments-label { color: rgba(0, 0, 0, 0.6); font-size: 0.8rem; margin-bottom: 5px; }
        .comments-text { color: #000000; background: rgba(44, 44, 44, 0.14); padding: 12px; border-radius: 8px; border-left: 3px solid #3d3d3dff; font-style: italic; }

        .responses-section { margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(255, 255, 255, 0.1); }
        .responses-header { color: #000000ff; font-weight: 600; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
        .response-item { background: rgba(255, 255, 255, 0.3); border: 1px solid rgba(40, 167, 69, 0.2); border-radius: 8px; padding: 15px; margin-bottom: 10px; }
        .response-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .response-type { background: rgba(40, 167, 70, 1); color: #000000; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
        .response-date { color: rgba(0, 0, 0, 0.6); font-size: 0.8rem; }
        .response-agent { color: rgba(0, 0, 0, 0.7); font-size: 0.8rem; margin-bottom: 8px; }
        .response-message { line-height: 1.6; color: #000000;}
        .follow-up { margin-top: 10px; padding: 8px; background: rgba(255, 193, 7, 0.1); border: 1px solid rgba(255, 193, 7, 0.2); border-radius: 4px; }
        .follow-up-label { color: #ffc107; font-size: 0.8rem; font-weight: 600; }

        .no-inquiries { text-align: center; padding: 60px 20px; color: rgba(255, 255, 255, 0.6); }
        .no-inquiries i { font-size: 4rem; color: #E60012; margin-bottom: 20px; }
        .no-inquiries h3 { color: #E60012; margin-bottom: 10px; }

        /* Amortization schedule */
        .amortization-panel { display: none; margin-top: 15px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 15px; }
        .amortization-title { color: #ffd700; font-weight: 600; margin-bottom: 10px; }
        .amortization-table { width: 100%; border-collapse: collapse; background: rgba(255,255,255,0.02); }
        .amortization-table th, .amortization-table td { border: 1px solid rgba(255,255,255,0.08); padding: 8px 10px; font-size: 0.92rem; }
        .amortization-table th { background: rgba(255,215,0,0.08); color: #ffd700; font-weight: 600; text-align: left; }
        .amortization-table tr:nth-child(even) { background: rgba(255,255,255,0.02); }

        .new-inquiry-btn { display: inline-block; 
            justify-content: center;
            align-items: center;
 
            background: #E60012; 
            color: #ffffff;
            width:250px;
            height: 50px;
 
            padding: 12px 24px; 
            border-radius: 25px; 
            text-decoration: none;
            font-weight: 600; 
            transition: all 0.3s ease; }

        .new-inquiry-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(255, 215, 0, 0.5); }

        .action-buttons { 
            margin-top: 20px; 
            display: flex; 
            gap: 10px; 
            flex-wrap: wrap; 
            align-items: center;
        }
        .btn { 
            padding: 10px 16px; 
            border-radius: 6px; 
            border: none; 
            cursor: pointer; 
            font-size: 0.9rem; 
            font-weight: 500; 
            transition: all 0.3s ease; 
            text-decoration: none; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center;
            gap: 8px;
            min-width: 120px;
            text-align: center;
            white-space: nowrap;
            line-height: 1.2;
        }
        .btn i { 
            color: #E60012;
            font-size: 0.9em; 
            width: 16px;
            text-align: center;
        }
        .btn-primary { 
            background: linear-gradient(45deg, #ffd700); 
            color: #1a1a1a; 
        }
        .btn-primary:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.3); 
        }
        .btn-secondary { 
            background: #E60012; 
            color: white; 
            border: 1px solid rgba(255, 255, 255, 0.2); 
        }
        .btn-secondary:hover { 
            background: rgba(255, 255, 255, 0.2); 
            transform: translateY(-2px);
        }
        .btn-success { 
            background: linear-gradient(45deg, #28a745, #34ce57); 
            color: white; 
        }
        .btn-success:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3); 
        }
        .btn-danger { 
            background: linear-gradient(45deg, #dc3545, #e4606d); 
            color: white; 
        }
        .btn-danger:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 4px 15px rgba(255, 254, 254, 0.3); 
        }
        .btn:disabled,
        .btn[disabled] {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }
        @media (max-width: 576px) {
            .action-buttons {
                flex-direction: column;
                width: 100%;
            }
            .btn {
                width: 100%;
            }
        }
        
        /* Tabs */
        .tabs { display: flex; margin-bottom: 20px; border-bottom: 1px solid rgba(5, 5, 5, 0.32); }
        .tab { padding: 12px 20px; cursor: pointer; font-weight: 500; color: #000000; 
               border-bottom: 2px solid transparent; transition: all 0.3s ease; }
        .tab.active { color: #E60012; border-bottom-color: #ff0000ff; }
        .tab:hover:not(.active) { color: #E60012; }
        .tab-badge { background: rgba(255, 0, 0, 0.2); color: #00000086; border-radius: 10px; 
                    padding: 2px 8px; font-size: 0.7rem; margin-left: 5px; }
        
        /* Status badges */
        .status-badge { 
            display: inline-flex; 
            align-items: center;
            gap: 5px;
            padding: 4px 12px; 
            border-radius: 12px; 
            font-size: 0.8rem; 
            font-weight: 600; 
            text-transform: capitalize;
        }
        .status-pending, .status-Pending { 
            background-color: #ffc107; 
            color: #000;
            border: 1px solid #d4a000;
        }
        .status-approved, .status-Approved { 
            background-color: #28a745; 
            color: #fff;
            border: 1px solid #1e7e34;
        }
        .status-rejected, .status-Rejected { 
            background-color: #dc3545; 
            color: #fff;
            border: 1px solid #bd2130;
        }
        
        .status-completed, .status-Completed {
            background-color: #17a2b8;
            color: #fff;
        }
        
        .status-cancelled, .status-Cancelled {
            background-color: #6c757d;
            color: #fff;
        }
        
        .status-under-review {
            background: rgba(23, 162, 184, 0.2);
            color: #17a2b8;
            border: 1px solid #17a2b8;
        }
        
        /* Gatepass specific styles */
        .gatepass-info {
            background: rgba(40, 167, 69, 0.1);
            border-radius: 8px;
            padding: 12px;
            margin: 15px 0 5px;
            border-left: 3px solid #28a745;
            grid-column: 1 / -1;
        }
        
        .gatepass-number {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            font-size: 1.1em;
            color: #28a745;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.9);
            padding: 5px 10px;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
            color: #1a1a1a;
        }
        
        .copy-btn {
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 4px;
            transition: all 0.2s;
            font-size: 0.9em;
        }
        
        .copy-btn:hover {
            background: rgba(0, 0, 0, 0.05);
            color: #28a745;
        }
        
        .gatepass-available {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-left: 8px;
            font-size: 0.8em;
            background: rgba(40, 167, 69, 0.2);
            padding: 2px 8px 2px 6px;
            border-radius: 10px;
            color: #28a745;
        }
        
        .detail-item {
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .detail-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        @media (max-width: 575px) {
            .header {
                flex-direction: column;
                gap: 12px;
                padding: 15px 20px;
            }
            .user-section {
                flex-direction: column;
                gap: 10px;
                text-align: center;
                width: 100%;
            }
            .page-title {
                font-size: 2rem;
            }
        }

        @media (max-width: 768px) {
            .container { padding: 20px 15px; }
            .page-title { font-size: 2rem; }
            .inquiry-details { grid-template-columns: 1fr; }
            .response-header { flex-direction: column; align-items: flex-start; gap: 5px; }
        }

        /* Unread visual treatment */
        .inquiry-card.unread { border-left: 4px solid #ffd700; }
        .inquiry-card.unread .inquiry-vehicle { font-weight: 800; }

        /* Screen reader only utility */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo-section">
            <img src="../includes/images/mitsubishi_logo.png" alt="Mitsubishi Logo" class="logo">
            <div class="brand-text">MITSUBISHI MOTORS</div>
        </div>
        <div class="user-section">
            <div class="user-avatar">
                <?php echo $profile_image_html; ?>
            </div>
            <span class="welcome-text">Welcome, <?php echo htmlspecialchars($displayName); ?>!</span>
            <button class="logout-btn" onclick="window.location.href='logout.php'">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </div>
    </header>

    <div class="container">
        <div id="live-region" class="sr-only" aria-live="polite" aria-atomic="true"></div>
        <a href="customer.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>

        <div class="page-header">
            <h1 class="page-title">My Account</h1>
            <p class="page-subtitle">Manage your inquiries, loan applications, and test drives</p>
        </div>

        <?php if (isset($_SESSION['flash_message'])): ?>
            <div role="alert" class="flash <?php echo (($_SESSION['flash_type'] ?? '') === 'error') ? 'error' : 'success'; ?>" style="margin: 10px 0; padding: 10px 14px; border-radius: 6px; background: rgba(255,255,255,0.1); color: #fff; border: 1px solid rgba(255,255,255,0.2);">
                <?php echo htmlspecialchars($_SESSION['flash_message']); ?>
            </div>
            <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
        <?php endif; ?>

        <!-- Tabs Navigation -->
        <div class="tabs">
            <div class="tab active" onclick="showTab('inquiries', event)">
                My Inquiries
                <?php if (isset($unread_count) && (int)$unread_count > 0): ?>
                    <span id="unread-count-badge" class="tab-badge"><?php echo (int)$unread_count; ?></span>
                <?php else: ?>
                    <span id="unread-count-badge" class="tab-badge" style="display:none">0</span>
                <?php endif; ?>
            </div>
            <div class="tab" onclick="showTab('loans', event)">
                Loan Applications
                <?php if (!empty($loan_applications)): ?>
                    <span class="tab-badge"><?php echo count($loan_applications); ?></span>
                <?php endif; ?>
            </div>
            <div class="tab" onclick="showTab('test-drives', event)">
                Test Drives
                <?php if (!empty($test_drives)): ?>
                    <span class="tab-badge"><?php echo count($test_drives); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Inquiries Tab -->
        <div id="inquiries-tab" class="tab-content">
            <div class="inquiries-container">
            <?php if (empty($inquiries)): ?>
                <div class="no-inquiries">
                    <i class="fas fa-question-circle"></i>
                    <h3>No Inquiries Yet</h3>
                    <p>You haven't submitted any vehicle inquiries yet.</p>
                    <a href="inquiry.php" class="new-inquiry-btn">
                        Submit New Inquiry
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($inquiries as $inquiry): ?>
                    <div class="inquiry-card <?php echo ((int)($inquiry['is_read'] ?? 0) === 0 ? 'unread' : ''); ?>" id="inquiry-card-<?php echo (int)$inquiry['Id']; ?>">
                        <div class="inquiry-header">
                            <div class="inquiry-id">INQ-<?php echo str_pad($inquiry['Id'], 5, '0', STR_PAD_LEFT); ?></div>
                            <div class="inquiry-vehicle">
                                <?php echo htmlspecialchars($inquiry['VehicleModel']); ?>
                                <?php if (!empty($inquiry['VehicleVariant'])): ?>
                                    <?php echo htmlspecialchars($inquiry['VehicleVariant']); ?>
                                <?php endif; ?>
                                - <?php echo $inquiry['VehicleYear']; ?>
                            </div>
                            <div class="inquiry-date">
                                Submitted on <?php echo date('F j, Y \a\t g:i A', strtotime($inquiry['InquiryDate'])); ?>
                            </div>
                            <div class="inquiry-status <?php echo $inquiry['response_count'] > 0 ? 'status-responded' : 'status-new'; ?>">
                                <?php if ($inquiry['response_count'] > 0): ?>
                                    <i class="fas fa-check-circle"></i> <?php echo $inquiry['response_count']; ?> Response(s)
                                <?php else: ?>
                                    <i class="fas fa-clock"></i> Awaiting Response
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="inquiry-body">
                            <div class="inquiry-details">
                                <div class="detail-item">
                                    <span class="detail-label">Vehicle Color</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($inquiry['VehicleColor']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Contact Email</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($inquiry['Email']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Phone Number</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($inquiry['PhoneNumber'] ?? 'Not provided'); ?></span>
                                </div>
                                <?php if (!empty($inquiry['FinancingRequired'])): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Financing</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($inquiry['FinancingRequired']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($inquiry['TradeInVehicleDetails'])): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Trade-in Vehicle</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($inquiry['TradeInVehicleDetails']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($inquiry['Comments'])): ?>
                                <div class="inquiry-comments">
                                    <div class="comments-label">Your Message:</div>
                                    <div class="comments-text"><?php echo nl2br(htmlspecialchars($inquiry['Comments'])); ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if ($inquiry['response_count'] > 0): ?>
                                <div class="responses-section">
                                    <div class="responses-header">
                                        <i class="fas fa-reply"></i> Responses from Sales Team
                                    </div>
                                    <?php 
                                    $responses = getInquiryResponses($connect, $inquiry['Id']);
                                    foreach ($responses as $response): 
                                    ?>
                                        <div class="response-item">
                                            <div class="response-header">
                                                <span class="response-type"><?php echo ucfirst(htmlspecialchars($response['ResponseType'])); ?></span>
                                                <span class="response-date"><?php echo date('M j, Y \a\t g:i A', strtotime($response['ResponseDate'])); ?></span>
                                            </div>
                                            <div class="response-agent">
                                                <i class="fas fa-user"></i> 
                                                Response by: <?php echo htmlspecialchars($response['FirstName'] . ' ' . $response['LastName']); ?>
                                            </div>
                                            <div class="response-message">
                                                <?php echo nl2br(htmlspecialchars($response['ResponseMessage'])); ?>
                                            </div>
                                            <?php if (!empty($response['FollowUpDate'])): ?>
                                                <div class="follow-up">
                                                    <div class="follow-up-label">
                                                        <i class="fas fa-calendar-alt"></i> Follow-up scheduled for: 
                                                        <?php echo date('F j, Y', strtotime($response['FollowUpDate'])); ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="action-buttons">
                                <?php if ((int)($inquiry['is_read'] ?? 0) === 0): ?>
                                    <form method="POST" class="mark-read-form" data-inquiry-id="<?php echo (int)$inquiry['Id']; ?>">
                                        <input type="hidden" name="mark_read" value="1">
                                        <input type="hidden" name="inquiry_id" value="<?php echo (int)$inquiry['Id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                        <button type="submit" class="btn btn-primary mark-read-btn" aria-label="Mark inquiry INQ-<?php echo str_pad($inquiry['Id'], 5, '0', STR_PAD_LEFT); ?> as read">
                                            <i class="fas fa-envelope-open"></i> Mark as read
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="btn btn-secondary" aria-disabled="true">
                                        <i class="fas fa-check" style="color: #ffffff;"></i> Read
                                    </span>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>
        </div>

        <!-- Loan Applications Tab -->
        <div id="loans-tab" class="tab-content" style="display: none;">
            <div class="inquiries-container">
                <?php if (empty($loan_applications)): ?>
                    <div class="no-inquiries">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <h3>No Loan Applications</h3>
                        <p>You haven't submitted any loan applications yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($loan_applications as $loan): ?>
                        <div class="inquiry-card">
                            <div class="inquiry-header">
                                <div class="inquiry-id">LOAN-<?php echo str_pad($loan['id'], 5, '0', STR_PAD_LEFT); ?></div>
                                <div class="inquiry-vehicle">
                                    <?php echo htmlspecialchars($loan['vehicle_model']); ?>
                                    <?php if (!empty($loan['vehicle_variant'])): ?>
                                        <?php echo htmlspecialchars($loan['vehicle_variant']); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="inquiry-date">
                                    Applied on <?php echo date('F j, Y', strtotime($loan['application_date'])); ?>
                                </div>
                                <div class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $loan['status'])); ?>">
                                    <?php echo $loan['status']; ?>
                                </div>
                            </div>
                            <div class="inquiry-body">
                                <div class="inquiry-details">
                                    <div class="detail-item">
                                        <span class="detail-label">Vehicle Price</span>
                                        <?php
                                        // Prefer effective > promotional > base
                                        $vEff = isset($loan['vehicle_effective_price']) ? (float)$loan['vehicle_effective_price'] : null;
                                        $vPromo = isset($loan['vehicle_promotional_price']) ? (float)$loan['vehicle_promotional_price'] : null;
                                        $vBase = isset($loan['vehicle_base_price']) ? (float)$loan['vehicle_base_price'] : null;
                                        $vPrice = $vEff ?: ($vPromo ?: $vBase);
                                        ?>
                                        <span class="detail-value"><?php echo $vPrice !== null ? '₱' . number_format($vPrice, 2) : 'N/A'; ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Loan Amount</span>
                                        <?php
                                        // Use total_amount if available
                                        $loanTotal = isset($loan['total_amount']) ? (float)$loan['total_amount'] : null;
                                        ?>
                                        <span class="detail-value"><?php echo $loanTotal !== null ? '₱' . number_format($loanTotal, 2) : 'N/A'; ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Term</span>
                                        <span class="detail-value">
                                            <?php echo isset($loan['financing_term']) && (int)$loan['financing_term'] > 0 ? (int)$loan['financing_term'] . ' months' : 'N/A'; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <?php if (!empty($loan['approval_notes'])): ?>
                                    <div class="inquiry-comments">
                                        <div class="comments-label">Approval Notes</div>
                                        <div class="comments-text"><?php echo nl2br(htmlspecialchars($loan['approval_notes'])); ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="action-buttons">
                                    <button type="button" class="btn btn-secondary" onclick="toggleLoanDetails(<?php echo $loan['id']; ?>, this)">
                                        <i style = "color: #ffffff" class="fas fa-eye"></i> View Details
                                    </button>
                                </div>

                                <?php
                                    // Use consistent calculation method as agent side
                                    // Vehicle price precedence: effective > promo > base
                                    $vEff = isset($loan['vehicle_effective_price']) ? (float)$loan['vehicle_effective_price'] : null;
                                    $vPromo = isset($loan['vehicle_promotional_price']) ? (float)$loan['vehicle_promotional_price'] : null;
                                    $vBase = isset($loan['vehicle_base_price']) ? (float)$loan['vehicle_base_price'] : null;
                                    $vehiclePrice = $vEff ?: ($vPromo ?: $vBase);
                                    $down = isset($loan['down_payment']) ? (float)$loan['down_payment'] : 0.0;

                                    // Calculate principal (loan amount) consistently
                                    $principal = max(0.0, (float)$vehiclePrice - $down);

                                    $rate = isset($loan['interest_rate']) ? (float)$loan['interest_rate'] : 0.0; // APR %
                                    $term = isset($loan['financing_term']) ? (int)$loan['financing_term'] : 0;
                                    $startAt = !empty($loan['application_date']) ? $loan['application_date'] : date('Y-m-d');

                                    // Use centralized calculation (no reverse-calculation from monthly payment)
                                    $schedule = computeAmortization((float)$principal, (float)$rate, (int)$term, $startAt, null);
                                ?>
                                <div id="amortization-<?php echo $loan['id']; ?>" class="amortization-panel">
                                    <div class="amortization-title">Amortization Schedule</div>
                                    <?php if (!empty($schedule)): ?>
                                    <table class="amortization-table">
                                        <thead>
                                            <tr>
                                                <th>Payment #</th>
                                                <th>Payment Date</th>
                                                <th>Payment Amount</th>
                                                <th>Principal</th>
                                                <th>Interest</th>
                                                <th>Balance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($schedule as $row): ?>
                                                <tr>
                                                    <td><?php echo (int)$row['n']; ?></td>
                                                    <td><?php echo htmlspecialchars($row['date']); ?></td>
                                                    <td>₱<?php echo number_format($row['payment'], 2); ?></td>
                                                    <td>₱<?php echo number_format($row['principal'], 2); ?></td>
                                                    <td>₱<?php echo number_format($row['interest'], 2); ?></td>
                                                    <td>₱<?php echo number_format($row['balance'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php else: ?>
                                        <div style="color: rgba(255,255,255,0.7);">No schedule available.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Test Drives Tab -->
        <div id="test-drives-tab" class="tab-content" style="display: none;">
            <div class="inquiries-container">
                <?php if (empty($test_drives)): ?>
                    <div class="no-inquiries">
                        <i class="fas fa-car"></i>
                        <h3>No Test Drive Requests</h3>
                        <p>You haven't scheduled any test drives yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($test_drives as $test_drive): ?>
                        <div class="inquiry-card">
                            <div class="inquiry-header">
                                <div class="inquiry-id">TD-<?php echo str_pad($test_drive['id'], 5, '0', STR_PAD_LEFT); ?></div>
                                <div class="inquiry-vehicle">
                                    <?php echo htmlspecialchars($test_drive['vehicle_model']); ?>
                                    <?php if (!empty($test_drive['vehicle_variant'])): ?>
                                        <?php echo htmlspecialchars($test_drive['vehicle_variant']); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="inquiry-date">
                                    <?php
                                    $hdrDate = $test_drive['scheduled_date'] ?? '';
                                    $hdrTime = $test_drive['scheduled_time'] ?? '';
                                    $hdrDateText = !empty($hdrDate) ? date('F j, Y', strtotime($hdrDate)) : 'Date not set';
                                    // If time is a range like "8:00 - 9:00", show as-is; otherwise, try to format
                                    if (!empty($hdrTime)) {
                                        if (strpos($hdrTime, '-') !== false) {
                                            $hdrTimeText = $hdrTime;
                                        } else {
                                            $t = strtotime($hdrTime);
                                            $hdrTimeText = $t ? date('g:i A', $t) : $hdrTime;
                                        }
                                    } else {
                                        $hdrTimeText = 'Time not set';
                                    }
                                    ?>
                                    Scheduled for <?php echo $hdrDateText; ?> at <?php echo htmlspecialchars($hdrTimeText); ?>
                                </div>
                                <div class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $test_drive['test_drive_status'])); ?>">
                                    <?php echo $test_drive['test_drive_status']; ?>
                                </div>
                            </div>
                            <div class="inquiry-body">
                                <div class="inquiry-details">
                                    <div class="detail-item">
                                        <span class="detail-label">Status</span>
                                        <span class="detail-value status-<?php echo strtolower($test_drive['test_drive_status']); ?>">
                                            <?php echo $test_drive['test_drive_status']; ?>
                                            <?php if ($test_drive['test_drive_status'] === 'Approved' && !empty($test_drive['gate_pass_number'])): ?>
                                                <span class="gatepass-available"><i class="fas fa-check-circle"></i> Gatepass Ready</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Scheduled Date</span>
                                        <span class="detail-value">
                                            <?php 
                                            $scheduledDate = $test_drive['scheduled_date'];
                                            echo !empty($scheduledDate) ? date('F j, Y', strtotime($scheduledDate)) : 'Not set';
                                            ?>
                                        </span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Scheduled Time</span>
                                        <span class="detail-value">
                                            <?php 
                                            $scheduledTime = $test_drive['scheduled_time'];
                                            if (!empty($scheduledTime)) {
                                                if (strpos($scheduledTime, '-') !== false) {
                                                    echo htmlspecialchars($scheduledTime);
                                                } else {
                                                    $ts = strtotime($scheduledTime);
                                                    echo $ts ? date('g:i A', $ts) : htmlspecialchars($scheduledTime);
                                                }
                                            } else {
                                                echo 'Not set';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <?php if ($test_drive['test_drive_status'] === 'Approved' && !empty($test_drive['gate_pass_number'])): ?>
                                    <div class="detail-item gatepass-info">
                                        <span class="detail-label">Gatepass Number</span>
                                        <span class="detail-value gatepass-number">
                                            <?php echo htmlspecialchars($test_drive['gate_pass_number']); ?>
                                            <button onclick="copyToClipboard('<?php echo htmlspecialchars($test_drive['gate_pass_number']); ?>')" 
                                                    class="copy-btn" 
                                                    title="Copy to clipboard">
                                                <i class="far fa-copy"></i>
                                            </button>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="action-buttons">
                                    <?php if ($test_drive['test_drive_status'] === 'Approved'): ?>
                                        <?php if (!empty($test_drive['gate_pass_number'])): ?>
                                            <a href="test_drive_pdf.php?request_id=<?php echo $test_drive['id']; ?>" class="btn btn-primary" target="_blank">
                                                <i class="fas fa-print"></i> Print Gatepass
                                            </a>
                                            <a href="test_drive_success.php?request_id=<?php echo $test_drive['id']; ?>" class="btn btn-secondary">
                                                <i style = "color: #ffffff" class="fas fa-eye"></i> View Details
                                            </a>
                                        <?php else: ?>
                                            <span class="btn btn-secondary" style="opacity: 0.7; cursor: not-allowed;" title="Gatepass will be generated after approval">
                                                <i class="fas fa-clock"></i> Awaiting Gatepass
                                            </span>
                                        <?php endif; ?>
                                    <?php elseif ($test_drive['test_drive_status'] === 'Pending'): ?>
                                        <span style ="background: #FF8C00;" class="btn btn-warning" style="opacity: 0.9; cursor: default;">
                                            <i style = "color: #ffffff" class="fas fa-clock"></i> Under Review
                                        </span>
                                    <?php elseif ($test_drive['test_drive_status'] === 'Rejected'): ?>
                                        <?php
                                        // Check if it was cancelled by customer
                                        $isCancelledByCustomer = !empty($test_drive['notes']) && strpos($test_drive['notes'], '[CUSTOMER_CANCELLED]') !== false;
                                        ?>
                                        <span class="btn btn-danger" style="opacity: 0.9; cursor: default;">
                                            <i style = "color: #ffffff" class="fas fa-times-circle"></i> <?php echo $isCancelledByCustomer ? 'Request Cancelled' : 'Request Rejected'; ?>
                                        </span>
                                    <?php elseif ($test_drive['test_drive_status'] === 'Completed'): ?>
                                        <span class="btn btn-success" style="opacity: 0.9; cursor: default;">
                                            <i class="fas fa-check-circle"></i> Completed
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if (strtotime($test_drive['scheduled_date'] . ' ' . $test_drive['scheduled_time']) > time() && 
                                              in_array($test_drive['test_drive_status'], ['Pending', 'Approved'])): ?>
                                        <button class="btn btn-danger" onclick="cancelTestDrive(<?php echo $test_drive['id']; ?>)">
                                            <i style = "color: #ffffff" class="fas fa-times"></i> Cancel
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    // Make showTab globally available
    window.showTab = function(tabName, event) {
        console.log('Showing tab:', tabName);
        
        // Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.style.display = 'none';
        });
        
        // Remove active class from all tabs
        document.querySelectorAll('.tab').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Show the selected tab content
        const tabContent = document.getElementById(tabName + '-tab');
        if (tabContent) {
            tabContent.style.display = 'block';
            console.log('Tab content found and shown');
        } else {
            console.error('Tab content not found for:', tabName);
        }
        
        // Add active class to the clicked tab
        if (event && event.currentTarget) {
            event.currentTarget.classList.add('active');
        }
        
        // Prevent default link behavior
        if (event) {
            event.preventDefault();
        }
        
        // Update URL hash
        window.location.hash = tabName;
    };
    
    // Toggle amortization schedule visibility for a loan card
    function toggleLoanDetails(loanId, btnEl) {
        const panel = document.getElementById(`amortization-${loanId}`);
        if (!panel) return;
        const isHidden = (panel.style.display === '' || panel.style.display === 'none');
        panel.style.display = isHidden ? 'block' : 'none';
        if (btnEl) {
            const iconMatch = btnEl.querySelector('i');
            if (isHidden) {
                btnEl.innerHTML = `<i class="fas fa-eye-slash"></i> Hide Schedule`;
            } else {
                btnEl.innerHTML = `<i class="fas fa-eye"></i> View Details`;
            }
        }
        if (isHidden) {
            // Smooth scroll into view when opening
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
    
    // Copy to clipboard function
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            // Show success toast
            const toast = document.createElement('div');
            toast.textContent = 'Copied to clipboard!';
            toast.style.position = 'fixed';
            toast.style.bottom = '20px';
            toast.style.right = '20px';
            toast.style.backgroundColor = '#28a745';
            toast.style.color = 'white';
            toast.style.padding = '10px 15px';
            toast.style.borderRadius = '4px';
            toast.style.zIndex = '1000';
            toast.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
            document.body.appendChild(toast);
            
            // Remove the toast after 2 seconds
            setTimeout(() => {
                document.body.removeChild(toast);
            }, 2000);
        }).catch(err => {
            console.error('Could not copy text: ', err);
        });
    }
    
    // Initialize tabs on page load
    document.addEventListener('DOMContentLoaded', () => {
        console.log('DOM loaded, initializing tabs');
        
        // Show tab based on URL hash
        if (window.location.hash) {
            const tabName = window.location.hash.substring(1);
            const tabElement = document.querySelector(`.tab[onclick*="${tabName}"]`);
            if (tabElement) {
                tabElement.click();
            } else {
                // If no matching tab found, default to inquiries
                showTab('inquiries');
            }
        } else {
            // Default to showing inquiries tab
            showTab('inquiries');
        }
        
        // Debug: Log test drives data
        console.log('Test drives data:', <?php echo json_encode($test_drives); ?>);
        console.log('Test drives array length (PHP count):', <?php echo isset($test_drives) ? count($test_drives) : 0; ?>);
        // Diagnostic: total rows in table as seen by PHP
        console.log('Test drive table total rows (COUNT(*)):', <?php echo isset($td_total_count) ? (int)$td_total_count : 'null'; ?>);

        // Progressive enhancement: Mark as read via AJAX
        document.querySelectorAll('.mark-read-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const fd = new FormData(form);
                fetch('my_inquiries.php', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: fd
                })
                .then(resp => {
                    if (!resp.ok) return resp.json().then(j => { throw new Error(j.message || ('HTTP ' + resp.status)); });
                    return resp.json();
                })
                .then(data => {
                    if (!data || !data.success) {
                        throw new Error((data && data.message) || 'Unknown error');
                    }
                    const id = data.inquiry_id;
                    const card = document.getElementById('inquiry-card-' + id);
                    if (card) {
                        card.classList.remove('unread');
                        // Replace control with "Read" indicator
                        const wrapper = form.parentElement;
                        if (wrapper) {
                            wrapper.innerHTML = '<span class="btn btn-secondary" aria-disabled="true"><i class="fas fa-check"></i> Read</span>';
                        }
                        // Focus management
                        const header = card.querySelector('.inquiry-header');
                        if (header) {
                            header.setAttribute('tabindex', '-1');
                            header.focus({ preventScroll: false });
                        }
                    }
                    // Update unread badge
                    const badge = document.getElementById('unread-count-badge');
                    if (badge) {
                        const c = Number(data.unread_count || 0);
                        badge.textContent = c;
                        badge.style.display = c > 0 ? '' : 'none';
                    }
                    // Live region announce
                    const lr = document.getElementById('live-region');
                    if (lr) lr.textContent = 'Inquiry marked as read';
                })
                .catch(err => {
                    console.error('Mark as read failed:', err);
                    alert('Failed to mark as read: ' + err.message);
                });
            });
        });
    });

    // Function to cancel test drive
    function cancelTestDrive(testDriveId) {
        Swal.fire({
            title: 'Cancel Test Drive?',
            text: 'Are you sure you want to cancel this test drive booking?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d60000',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, cancel it',
            cancelButtonText: 'No, keep it',
            allowOutsideClick: true,
            allowEscapeKey: true,
            backdrop: true,
            heightAuto: false,
            width: '400px'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'Cancelling...',
                    text: 'Please wait while we process your request.',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                fetch('cancel_test_drive.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'test_drive_id=' + encodeURIComponent(testDriveId)
                })
                .then(response => {
                    // Log the response for debugging
                    console.log('Response status:', response.status);
                    console.log('Response headers:', response.headers);

                    // Always try to parse the response as JSON, even if status is not ok
                    return response.text().then(text => {
                        console.log('Response text:', text);
                        try {
                            const data = JSON.parse(text);
                            // Return both the data and the status
                            return { data: data, status: response.status, ok: response.ok };
                        } catch (e) {
                            console.error('JSON parse error:', e);
                            console.error('Response was:', text.substring(0, 500));
                            throw new Error('Invalid JSON response from server: ' + text.substring(0, 100));
                        }
                    });
                })
                .then(result => {
                    const data = result.data;

                    if (data.success) {
                        Swal.fire({
                            title: 'Cancelled!',
                            text: 'Your test drive has been cancelled successfully.',
                            icon: 'success',
                            confirmButtonColor: '#28a745',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        // Show the actual error message from the server
                        let errorMsg = data.message || 'Unknown error';
                        Swal.fire({
                            title: 'Cancellation Failed',
                            text: errorMsg,
                            icon: 'error',
                            confirmButtonColor: '#d60000',
                            confirmButtonText: 'OK'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        title: 'Error',
                        text: 'An error occurred while processing your request: ' + error.message,
                        icon: 'error',
                        confirmButtonColor: '#d60000',
                        confirmButtonText: 'OK'
                    });
                });
            }
        });
    }
    </script>
  </body>
  </html>