<?php
session_start();
include_once(dirname(__DIR__) . '/includes/database/db_conn.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'Customer';
    $firstName = $_POST['first_name'] ?? null;
    $lastName = $_POST['last_name'] ?? null;
    $dob = $_POST['dob'] ?? null;

    // Hash the password for security
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Check for existing username or email
    $stmt = $connect->prepare("SELECT 1 FROM accounts WHERE Username = ? OR Email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        echo "Account already exists!";
        exit;
    }

    // Insert new account with email_verified = 0 for customers
    $emailVerified = ($role === 'Customer') ? 0 : 1;
    $stmt = $connect->prepare("INSERT INTO accounts (Username, Email, PasswordHash, Role, FirstName, LastName, DateOfBirth, email_verified, CreatedAt, UpdatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    if ($stmt->execute([$username, $email, $passwordHash, $role, $firstName, $lastName, $dob, $emailVerified])) {
        // Get the newly created user ID
        $newUserId = $connect->lastInsertId();

        // For customers, send OTP instead of auto-login
        if ($role === 'Customer') {
            require_once(dirname(__DIR__) . '/includes/services/OTPService.php');
            $otpService = new \Mitsubishi\Services\OTPService($connect);
            $otpResult = $otpService->sendOTP($newUserId, $email);

            if ($otpResult['success']) {
                $_SESSION['pending_verification_user_id'] = $newUserId;
                $_SESSION['pending_verification_email'] = $email;
                header("Location: verify_otp.php");
                exit;
            } else {
                echo "Account created but failed to send verification email. Please contact support.";
                exit;
            }
        }

        // For non-customers, send notifications as before
        require_once(dirname(__DIR__) . '/includes/api/notification_api.php');
        $notifTitle = 'New Account Registered';
        $notifMsg = "A new user has registered: $username ($role).";
        createNotification(null, 'Admin', $notifTitle, $notifMsg, 'account', $newUserId);
        createNotification(null, 'SalesAgent', $notifTitle, $notifMsg, 'account', $newUserId);
        // Automatically log in the user if they're a Customer
        if ($role === 'Customer') {
            $_SESSION['user_id'] = $newUserId;
            $_SESSION['user_role'] = $role;
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            // Redirect to verification page
            header("Location: verification.php");
            exit;
        } else {
            echo "Account created successfully!";
        }
    } else {
        echo "Failed to create account!";
    }
}
?>
