<?php
session_start();
include_once(dirname(__DIR__) . '/includes/database/db_conn.php');

$register_error = '';
$register_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $confirm_password = $_POST['confirm_password'] ?? '';

  // Check if passwords match
  if ($password !== $confirm_password) {
    $register_error = "Passwords do not match.";
  } else {
    // Split name into first and last (simple logic)
    $nameParts = explode(' ', $name, 2);
    $firstName = $nameParts[0] ?? '';
    $lastName = $nameParts[1] ?? '';

    $username = explode('@', $email)[0];

    // Check if email or username already exists
    $stmt = $connect->prepare("SELECT COUNT(*) FROM accounts WHERE Email = ? OR Username = ?");
    $stmt->execute([$email, $username]);
    if ($stmt->fetchColumn() > 0) {
      $register_error = "Email or username already exists.";
    } else {
      $passwordHash = password_hash($password, PASSWORD_DEFAULT);
      $sql = "INSERT INTO accounts (Username, Email, PasswordHash, Role, FirstName, LastName, CreatedAt, UpdatedAt)
                    VALUES (?, ?, ?, 'Customer', ?, ?, NOW(), NOW())";
      $stmt = $connect->prepare($sql);
      if ($stmt->execute([$username, $email, $passwordHash, $firstName, $lastName])) {
        // Get the newly created user ID
        $newUserId = $connect->lastInsertId();
        // Send notifications to agents and admins
        require_once(dirname(__DIR__) . '/includes/api/notification_api.php');
        $notifTitle = 'New Account Registered';
        $notifMsg = "A new user has registered: $username (Customer).";
        createNotification(null, 'Admin', $notifTitle, $notifMsg, 'account', $newUserId);
        createNotification(null, 'SalesAgent', $notifTitle, $notifMsg, 'account', $newUserId);
        // Automatically log in the user
        $_SESSION['user_id'] = $newUserId;
        $_SESSION['user_role'] = 'Customer';
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;
        // Redirect to verification page
        header("Location: verification.php");
        exit;
      } else {
        $register_error = "Failed to create account. Please try again.";
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Create Account - Mitsubishi Motors</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Segoe UI', sans-serif;
    }

    html,
    body {
      height: 100%;
      width: 100%;
      margin: 0;
      padding: 0;
      overflow: hidden;
    }

    body {
      background: #dbdbdbff;
      color: white;
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
      background-color: #5f5c5cb0;
      margin: 0 auto;
      padding: 28px 24px;
      border-radius: 15px;
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
      text-align: center;

      max-width: 800px;
      min-width: 800px;
      max-height: 950px;

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



    form>div {
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
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
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

    @media (max-width: 575px) {

      html,
      body {
        zoom: 90%;
      }

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

      html,
      body {
        zoom: 85%;
      }

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

      html,
      body {
        zoom: 85%;
      }

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

      html,
      body {
        zoom: 80%;
      }

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

    @media (min-width: 1199px) and (max-width: 1440px) {

      html,
      body {
        zoom: 85%;
      }

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
      <img src="../includes/images/mitsubishi_logo.png" alt="Mitsubishi Logo" class="logo" />
      <h2>Create Your Account</h2>
      <?php if (!empty($register_error)): ?>
        <div style="color:#ffd700;margin-bottom:10px;"><?php echo htmlspecialchars($register_error); ?></div>
      <?php endif; ?>
      <form method="post" autocomplete="off">
        <div>
          <label for="name">Full Name</label>
          <input type="text" id="name" name="name" placeholder="Enter your full name" required />
        </div>
        <div>
          <label for="email">Email</label>
          <input type="email" id="email" name="email" placeholder="Enter your email" required />
        </div>
        <div>
          <label for="password">Password</label>
          <input type="password" id="password" name="password" placeholder="Create a password" required />
        </div>
        <div>
          <label for="confirm_password">Confirm Password</label>
          <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required />
          <div class="password-toggle">
            <input type="checkbox" id="showPasswords" onchange="togglePasswords(this)">
            <label for="showPasswords">Show passwords</label>
          </div>
        </div>
        <button type="submit">Create Account</button>
        <button type="button" style="background:#ffd700;color:#b80000;font-weight:bold;padding:12px 0;width:100%;border-radius:8px;border:none;cursor:pointer;font-size:1rem;" onclick="window.location.href='landingpage.php';return false;">
          Return to Landing Page
        </button>
        <p class="register">
          Already have an account? <a href="login.php">Log In</a>
        </p>
      </form>
    </div>
  </div>

  <script>
    function togglePasswords(checkbox) {
      const passwordInput = document.getElementById('password');
      const confirmPasswordInput = document.getElementById('confirm_password');

      if (checkbox.checked) {
        passwordInput.type = 'text';
        confirmPasswordInput.type = 'text';
      } else {
        passwordInput.type = 'password';
        confirmPasswordInput.type = 'password';
      }
    }
  </script>
</body>

</html>