<?php
session_start();
include_once(dirname(__DIR__) . '/includes/database/db_conn.php');

$reset_error = '';
$reset_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    // Check if email exists
    $stmt = $connect->prepare("SELECT * FROM accounts WHERE Email = ?");
    $stmt->execute([$email]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($account) {
        // Generate and send OTP for password reset
        require_once(dirname(__DIR__) . '/includes/services/OTPService.php');
        $otpService = new \Mitsubishi\Services\OTPService($connect);
        $otpResult = $otpService->sendOTP($account['Id'], $email, 'password_reset');

        if ($otpResult['success']) {
            // Set session variables for password reset
            $_SESSION['pending_password_reset_user_id'] = $account['Id'];
            $_SESSION['pending_password_reset_email'] = $email;

            // Redirect to OTP verification page
            header("Location: verify_reset_otp.php");
            exit;
        } else {
            $reset_error = "Failed to send verification code. Please try again.";
        }
    } else {
        $reset_error = "No account found with that email address.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Forgot Password - Mitsubishi Motors</title>
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
      background: linear-gradient(to bottom, #1c1c1c, #b80000);
      color: white;
      zoom: 80%;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .container {
      display: flex;
      justify-content: center;
      align-items: center;
      width: 100%;
      height: 100%;
    }

    .login-box {
      background-color: rgba(255, 255, 255, 0.1);
      padding: 28px 24px;
      border-radius: 15px;
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
      text-align: center;
      width: 100%;
      max-width: 400px;
      min-width: 280px;
    }
    .logo {
      width: 80px;
      margin-bottom: 20px;
    }
    h2 {
      font-size: 1.5rem;
      margin-bottom: 30px;
      line-height: 1.2;
    }
    form {
      display: flex;
      flex-direction: column;
      gap: 10px;
      width: 100%;
      margin: 0 auto;
      /* Remove max-width restriction */
    }
    form > div {
      display: flex;
      flex-direction: column;
      align-items: stretch;
      width: 100%;
    }
    label {
      text-align: left;
      font-size: 0.9rem;
      margin-bottom: 5px;
      margin-left: 2px;
    }

    input {
      padding: 10px 12px;
      border: none;
      border-radius: 5px;
      font-size: 1rem;
      margin-bottom: 10px;
      background: #fff;
      color: #333;
      outline: none;
      transition: box-shadow 0.2s;
      box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }

    input:focus {
      box-shadow: 0 0 0 2px #b80000;
    }
    button {
      padding: 10px;
      font-size: 0.97rem;
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
    .register {
      margin-top: 10px;
      font-size: 0.85rem;
    }
    .register a {
      color: #ffd700;
      text-decoration: none;
    }
    .register a:hover {
      text-decoration: underline;
    }
    @media (max-width: 575px) {
      .login-box {
        padding: 14px 6vw;
        width: 95vw;
        max-width: 95vw;
        min-width: unset;
      }
      .logo {
        width: 60px;
      }
      h2 {
        font-size: 1.1rem;
      }
      form {
        gap: 7px;
        width: 100%;
        /* Remove max-width restriction */
      }
      input {
        padding: 8px 10px;
        font-size: 0.95rem;
      }
      button {
        font-size: 0.95rem;
        padding: 9px;
      }
    }
    @media (min-width: 576px) and (max-width: 767px) {
      .login-box {
        padding: 30px;
        max-width: 350px;
      }
      .logo {
        width: 70px;
      }
      h2 {
        font-size: 1.25rem;
      }
    }
    @media (min-width: 768px) and (max-width: 991px) {
      .login-box {
        padding: 35px;
        max-width: 380px;
      }
      .logo {
        width: 75px;
      }
      h2 {
        font-size: 1.4rem;
      }
    }
    @media (min-width: 992px) and (max-width: 1199px) {
      .login-box {
        padding: 40px;
        max-width: 400px;
      }
      .logo {
        width: 80px;
      }
      h2 {
        font-size: 1.5rem;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="login-box">
      <img src="../includes/images/mitsubishi_logo.png" alt="Mitsubishi Logo" class="logo"/>
      <h2>Forgot Password</h2>
      <?php if (!empty($reset_error)): ?>
        <div style="color:#ffd700;margin-bottom:10px;"><?php echo htmlspecialchars($reset_error); ?></div>
      <?php endif; ?>
      <?php if (!empty($reset_success)): ?>
        <div style="color:#ffd700;"><?php echo $reset_success; ?></div>
      <?php else: ?>
      <form method="post" autocomplete="off">
        <div>
          <label for="email">Email</label>
          <input type="email" id="email" name="email" placeholder="Enter your email" required value="<?php echo isset($email) ? htmlspecialchars($email, ENT_QUOTES) : ''; ?>" />
        </div>
        <button type="submit">Send Verification Code</button>
        <button type="button" style="background:#ffd700;color:#b80000;font-weight:bold;padding:12px 0;width:100%;border-radius:8px;border:none;cursor:pointer;font-size:1rem;" onclick="window.location.href='landingpage.php';return false;">
          Return to Landing Page
        </button>
        <p class="register">
          Remembered your password? <a href="login.php">Log In</a>
        </p>
      </form>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
