<?php
session_start();
include_once(dirname(__DIR__) . '/includes/database/db_conn.php');

// Check if user has pending verification
if (!isset($_SESSION['pending_verification_user_id']) || !isset($_SESSION['pending_verification_email'])) {
    // Check if user is already logged in and verified
    if (isset($_SESSION['user_id'])) {
        header("Location: customer.php");
        exit;
    }
    // No pending verification, redirect to login
    header("Location: login.php");
    exit;
}

$accountId = $_SESSION['pending_verification_user_id'];
$email = $_SESSION['pending_verification_email'];
$error_message = '';
$success_message = '';
$resend_message = '';

// Load OTP Service
require_once dirname(__DIR__) . '/includes/services/OTPService.php';
$otpService = new \Mitsubishi\Services\OTPService($connect);

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $otpCode = trim($_POST['otp_code'] ?? '');
    
    if (empty($otpCode)) {
        $error_message = 'Please enter the OTP code.';
    } elseif (!preg_match('/^\d{6}$/', $otpCode)) {
        $error_message = 'OTP must be 6 digits.';
    } else {
        $result = $otpService->verifyOTP($accountId, $otpCode);
        
        if ($result['success']) {
            // Clear pending verification session
            unset($_SESSION['pending_verification_user_id']);
            unset($_SESSION['pending_verification_email']);
            
            // Create full session
            $_SESSION['user_id'] = $accountId;
            $_SESSION['user_role'] = 'Customer';
            
            // Get account details
            $stmt = $connect->prepare("SELECT Username, Email FROM accounts WHERE Id = ?");
            $stmt->execute([$accountId]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($account) {
                $_SESSION['username'] = $account['Username'];
                $_SESSION['email'] = $account['Email'];
            }
            
            // Send notifications to Admin and SalesAgent
            require_once(dirname(__DIR__) . '/includes/api/notification_api.php');
            $notifTitle = 'New Account Registered';
            $notifMsg = "A new user has registered: " . ($account['Username'] ?? 'Unknown') . " (Customer).";
            createNotification(null, 'Admin', $notifTitle, $notifMsg, 'account', $accountId);
            createNotification(null, 'SalesAgent', $notifTitle, $notifMsg, 'account', $accountId);
            
            // Redirect to verification page (customer information form)
            header("Location: verification.php");
            exit;
        } else {
            $error_message = $result['message'];
        }
    }
}

// Handle OTP resend
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_otp'])) {
    $result = $otpService->resendOTP($accountId, $email);
    
    if ($result['success']) {
        $resend_message = $result['message'];
    } else {
        $error_message = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email - Mitsubishi Motors</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif;
        }

        html, body {
            height: 100%;
            width: 100%;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }

        body {
            background-image: url(../includes/images/logbg.jpg);
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            zoom: 90%;
        }

        .container {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 100%;
        }

        .verification-box {
            background-color: #5f5c5cd8;
            margin: 0 auto;
            padding: 28px 24px;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
            text-align: center;
            width: 100%;
            max-width: 450px;
            min-width: 280px;
        }

        .logo {
            width: 80px;
            margin-bottom: 20px;
        }

        h2 {
            font-size: 1.5rem;
            margin-bottom: 15px;
            line-height: 1.2;
            color: #ffffff;
        }

        .subtitle {
            color: #ffd700;
            font-size: 0.9rem;
            margin-bottom: 20px;
            line-height: 1.4;
        }

        .email-display {
            background: rgba(255, 255, 255, 0.1);
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            color: #ffd700;
            font-size: 0.9rem;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
            width: 100%;
            margin: 0 auto;
        }

        .otp-input-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
        }

        .otp-input {
            padding: 15px;
            border: 2px solid #ffd700;
            border-radius: 8px;
            font-size: 24px;
            text-align: center;
            letter-spacing: 8px;
            font-family: 'Courier New', monospace;
            font-weight: bold;
            background: #fff;
            color: #333;
            outline: none;
            width: 100%;
            max-width: 280px;
        }

        .otp-input:focus {
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.3);
            border-color: #d60000;
        }

        button {
            padding: 12px;
            font-size: 1rem;
            margin-top: 6px;
            border: none;
            background-color: #d60000;
            color: white;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease;
            width: 100%;
        }

        button:hover {
            background-color: #b30000;
        }

        button:disabled {
            background-color: #999;
            cursor: not-allowed;
        }

        .resend-button {
            background-color: #ffd700;
            color: #b80000;
        }

        .resend-button:hover:not(:disabled) {
            background-color: #ffed4e;
        }

        .message {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }

        .error {
            background-color: rgba(220, 53, 69, 0.2);
            border: 1px solid #dc3545;
            color: #ffd700;
        }

        .success {
            background-color: rgba(40, 167, 69, 0.2);
            border: 1px solid #28a745;
            color: #90ee90;
        }

        .info {
            background-color: rgba(255, 215, 0, 0.2);
            border: 1px solid #ffd700;
            color: #ffd700;
            font-size: 0.85rem;
            margin-top: 15px;
        }

        .countdown {
            color: #ffd700;
            font-size: 0.85rem;
            margin-top: 10px;
        }

        .back-link {
            margin-top: 15px;
            font-size: 0.85rem;
        }

        .back-link a {
            color: #ffd700;
            text-decoration: none;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        @media (max-width: 575px) {
            html, body {
                zoom: 100%;
            }

            .verification-box {
                padding: 20px;
                width: 90vw;
                max-width: 90vw;
            }

            .logo {
                width: 50px;
            }

            h2 {
                font-size: 1.2rem;
            }

            .otp-input {
                font-size: 20px;
                letter-spacing: 6px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="verification-box">
            <img src="../includes/images/mitsubishi_logo.png" alt="Mitsubishi Logo" class="logo" />
            <h2>Verify Your Email</h2>
            <p class="subtitle">We've sent a 6-digit code to:</p>
            <div class="email-display"><?php echo htmlspecialchars($email); ?></div>

            <?php if (!empty($error_message)): ?>
                <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <?php if (!empty($resend_message)): ?>
                <div class="message success"><?php echo htmlspecialchars($resend_message); ?></div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <div class="otp-input-container">
                    <input 
                        type="text" 
                        class="otp-input" 
                        name="otp_code" 
                        id="otp_code" 
                        placeholder="000000" 
                        maxlength="6" 
                        pattern="\d{6}" 
                        required 
                        autofocus
                    />
                </div>
                <button type="submit" name="verify_otp">Verify Email</button>
            </form>

            <form method="post" id="resendForm">
                <button type="submit" name="resend_otp" class="resend-button" id="resendButton">
                    Resend Code
                </button>
                <div class="countdown" id="countdown"></div>
            </form>

            <div class="message info">
                ⏱️ Code expires in 10 minutes<br>
                🔒 Never share this code with anyone
            </div>

            <p class="back-link">
                <a href="logout.php">← Back to Login</a>
            </p>
        </div>
    </div>

    <script>
        // Auto-format OTP input (digits only)
        document.getElementById('otp_code').addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '');
        });

        // Resend cooldown timer
        let cooldownSeconds = 60;
        let countdownInterval = null;

        function startCooldown() {
            const resendButton = document.getElementById('resendButton');
            const countdownDiv = document.getElementById('countdown');
            
            resendButton.disabled = true;
            cooldownSeconds = 60;
            
            countdownInterval = setInterval(function() {
                cooldownSeconds--;
                countdownDiv.textContent = `Wait ${cooldownSeconds} seconds before resending`;
                
                if (cooldownSeconds <= 0) {
                    clearInterval(countdownInterval);
                    resendButton.disabled = false;
                    countdownDiv.textContent = '';
                }
            }, 1000);
        }

        // Start cooldown on page load if resend was clicked
        <?php if (isset($_POST['resend_otp'])): ?>
        startCooldown();
        <?php endif; ?>

        // Handle resend form submission
        document.getElementById('resendForm').addEventListener('submit', function() {
            startCooldown();
        });
    </script>
</body>
</html>

