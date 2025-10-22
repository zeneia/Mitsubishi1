<?php
session_start();
include_once(dirname(__DIR__) . '/includes/database/db_conn.php');

// Check if password reset was verified
if (!isset($_SESSION['password_reset_verified']) ||
    !isset($_SESSION['pending_password_reset_user_id']) ||
    !isset($_SESSION['pending_password_reset_email'])) {
    header("Location: forgot_password.php");
    exit;
}

// Check if verification is still valid (10 minutes)
$verificationTime = $_SESSION['password_reset_verified'];
if (time() - $verificationTime > 600) { // 10 minutes
    unset($_SESSION['password_reset_verified']);
    unset($_SESSION['pending_password_reset_user_id']);
    unset($_SESSION['pending_password_reset_email']);
    header("Location: forgot_password.php?error=expired");
    exit;
}

$accountId = $_SESSION['pending_password_reset_user_id'];
$email = $_SESSION['pending_password_reset_email'];
$reset_error = '';
$reset_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (!$password || !$confirm) {
        $reset_error = "Please fill in all fields.";
    } elseif ($password !== $confirm) {
        $reset_error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $reset_error = "Password must be at least 6 characters.";
    } else {
        // Check if account exists
        $stmt = $connect->prepare("SELECT * FROM accounts WHERE Id = ?");
        $stmt->execute([$accountId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($account) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $update = $connect->prepare("UPDATE accounts SET PasswordHash = ? WHERE Id = ?");
            $update->execute([$hash, $accountId]);

            // Clear all password reset sessions
            unset($_SESSION['password_reset_verified']);
            unset($_SESSION['pending_password_reset_user_id']);
            unset($_SESSION['pending_password_reset_email']);

            $reset_success = "Password has been reset successfully. <a href='login.php' style='color:#ffd700;'>Log in</a>";
        } else {
            $reset_error = "Account not found.";
        }
    }
}

// Function to mask email for display
function maskEmail($email) {
    $parts = explode('@', $email);
    if (count($parts) !== 2) {
        return $email;
    }

    $username = $parts[0];
    $domain = $parts[1];

    if (strlen($username) <= 2) {
        $masked = $username[0] . '***';
    } else {
        $masked = $username[0] . '***';
    }

    return $masked . '@' . $domain;
}

$maskedEmail = maskEmail($email);

// Calculate remaining time for session expiration
$remainingSeconds = 600 - (time() - $verificationTime);
$remainingMinutes = floor($remainingSeconds / 60);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reset Password - Mitsubishi Motors</title>
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
      background-color: #2B952B;
      color: white;
      border-radius: 8px;
      font-weight: bold;
      cursor: pointer;
      transition: background-color 0.3s ease;
      width: 100%;
    }
    button:hover {
      background-color: #217821;
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
    
    .password-toggle {
      display: flex;
      align-items: center;
      justify-content: flex-start;
      margin-top: 5px;
      margin-bottom: 10px;
    }
    
    .password-toggle input[type="checkbox"] {
      margin: 0 8px 0 0;
      width: auto;
      box-shadow: none;
    }
    
    .password-toggle label {
      margin: 0;
      font-size: 0.8rem;
      cursor: pointer;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="login-box">
      <img src="../includes/images/mitsubishi_logo.png" alt="Mitsubishi Logo" class="logo"/>
      <h2>Reset Password</h2>
      <?php if (!empty($reset_error)): ?>
        <div style="color:#ffd700;margin-bottom:10px;"><?php echo htmlspecialchars($reset_error); ?></div>
      <?php endif; ?>
      <?php if (!empty($reset_success)): ?>
        <div style="color:#ffd700;margin-bottom:10px;"><?php echo $reset_success; ?></div>
      <?php endif; ?>
      <?php if (empty($reset_success)): ?>
      <div style="background:rgba(255,215,0,0.2);border:1px solid #ffd700;padding:10px;border-radius:5px;margin-bottom:15px;font-size:0.85rem;">
        <p style="margin:0;color:#ffd700;">Resetting password for: <strong><?php echo htmlspecialchars($maskedEmail); ?></strong></p>
        <p style="margin:5px 0 0 0;color:#ffd700;font-size:0.8rem;">⏱️ You have <?php echo $remainingMinutes; ?> minute(s) to complete this</p>
      </div>
      <form method="post" autocomplete="off">
        <div>
          <label for="password">New Password</label>
          <input type="password" id="password" name="password" placeholder="Enter new password" required minlength="6" />
          <div class="password-toggle">
            <input type="checkbox" id="showPassword" onchange="togglePassword('password', this)">
            <label for="showPassword">Show password</label>
          </div>
        </div>
        <div>
          <label for="confirm">Confirm Password</label>
          <input type="password" id="confirm" name="confirm" placeholder="Confirm new password" required minlength="6" />
          <div class="password-toggle">
            <input type="checkbox" id="showConfirm" onchange="togglePassword('confirm', this)">
            <label for="showConfirm">Show password</label>
          </div>
        </div>
        <button type="submit">Reset Password</button>
        <button type="button" style="background:#ffd700;color:#b80000;font-weight:bold;padding:12px 0;width:100%;border-radius:8px;border:none;cursor:pointer;font-size:1rem;" onclick="window.location.href='landingpage.php';return false;">
          Return to Landing Page
        </button>
      </form>
      <?php endif; ?>
    </div>
  </div>
  
  <script>
    function togglePassword(inputId, checkbox) {
      const passwordInput = document.getElementById(inputId);
      if (checkbox.checked) {
        passwordInput.type = 'text';
      } else {
        passwordInput.type = 'password';
      }
    }
  </script>
</body>
</html>
