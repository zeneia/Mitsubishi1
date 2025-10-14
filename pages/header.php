<?php
require_once '../includes/init.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title><?php echo isset($pageTitle) ? $pageTitle : 'Mitsubishi Motors San Pablo City'; ?></title>
  <link rel="icon" type="image/png" href="../includes/images/mitsubishi_logo.png">
  <link rel="icon" type="image/x-icon" href="../includes/images/mitsubishi_logo.png">
  
  <!-- Mobile Fix CSS -->
  <link rel="stylesheet" href="../css/mobile-fix.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    body {
      font-family: 'Segoe UI', Arial, sans-serif;
      background: #ffffff;
      color: white;
      min-height: 100vh;
    }
    header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 18px 32px;
      background-color: #181818;
      box-shadow: 0 2px 8px rgba(0,0,0,0.15);
      position: sticky;
      top: 0;
      z-index: 1000;
    }
    .logo {
      display: flex;
      align-items: center;
      gap: 14px;
    }
    .logo img {
      width: 48px;
      filter: drop-shadow(0 2px 4px #b80000aa);
    }
    .logo strong {
      font-size: 1.15rem;
      letter-spacing: 1px;
    }
    .logo small {
      color: #ffd700;
      font-size: 0.85rem;
      font-weight: 500;
    }

    .nav-container {
      display: flex;
      align-items: center;
      gap: 40px; /* Space between menu and login */
    }

    nav {
      display: flex;
      gap: 28px;
    }
    
    nav a {
      color: #fff;
      text-decoration: none;
      font-weight: 600;
      font-size: 1rem;
      padding: 6px 0;
      border-bottom: 2px solid transparent;
      transition: border-color 0.2s, color 0.2s;
    }
    nav a:hover {
      color: #ffd700;
      border-bottom: 2px solid #ffd700;
    }
    .menu-toggle {
      display: none;
      flex-direction: column;
      cursor: pointer;
      padding: 5px;
      margin-left: auto;

    }
    .menu-toggle span {
      width: 25px;
      height: 3px;
      background-color: #ffd700;
      margin: 3px 0;
      transition: 0.3s;
    }
    .user-section {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .user-section a {
      color: #ffd700;
      text-decoration: none;
      font-weight: bold;
      font-size: 1rem;
      margin-right: 4px;
      transition: color 0.2s;
      background: rgba(255, 215, 0, 0.1);
      padding: 8px 16px;
      border-radius: 20px;
      border: 1px solid #ffd700;
    }
    .user-section a:hover {
      color: #111;
      background: #ffd700;
    }

    /* Menu Toggle Animation */
    .menu-toggle.active span:nth-child(1) {
      transform: rotate(-45deg) translate(-5px, 6px);
    }
    .menu-toggle.active span:nth-child(2) {
      opacity: 0;
    }
    .menu-toggle.active span:nth-child(3) {
      transform: rotate(45deg) translate(-5px, -6px);
    }
    
    /* Mobile Responsive */
    @media (max-width: 1024px) {
      .mobile-login {
        display: block;
        color: #ffd700;
        text-decoration: none;
        font-weight: bold;
        padding: 12px 0;
        text-align: center;
        border-top: 1px solid #333;
        margin-top: 8px;
      }
      header {
        position: relative;
        padding: 12px 20px;
      }
      .menu-toggle {
        display: flex;
        margin-left: auto;
        cursor: pointer;
        flex-direction: column;
      }

      nav {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        width: 100%;
        background-color: #181818;
        flex-direction: column;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
      }
      nav.active {
        display: flex;
      }
      nav a {
        padding: 12px 0;
        border-bottom: 1px solid #333;
        text-align: center;
      }

      .user-section {
        display: none;
      }
      .logo strong {
        font-size: 1rem;
      }
      .logo small {
        font-size: 0.8rem;
      }
    }
    
    @media (max-width: 575px) {
      .logo img {
        width: 36px;
      }
      .logo strong {
        font-size: 0.9rem;
      }
      .logo small {
        font-size: 0.75rem;
      }
    }
    
    .mobile-login {
      display: none;
    }
  </style>
  
  <!-- Load Mobile Fix JavaScript -->
  <script src="../js/mobile-fix.js" defer></script>
  
  <!-- Load Phone Validation JavaScript -->
  <script src="../js/phone-validation.js" defer></script>
  <?php includePhoneValidation(); ?>
</head>
<body>
  <header>
    <a href="../pages/landingpage.php" style="text-decoration: none; color: inherit;">
      <div class="logo">
        <img src="../includes/images/mitsubishi_logo.png" alt="Mitsubishi Logo">
        <div>
          <strong>MITSUBISHI MOTORS</strong><br>
          <small>Drive Your Ambition</small>
        </div>
      </div>
    </a>
    <div class="menu-toggle" onclick="toggleMenu()">
      <span></span>
      <span></span>
      <span></span>
    </div>
    
  <div class="nav-container">
    <nav id="navMenu">
      <a href="../pages/cars.php">CARS</a>
      <a href="../pages/sales.php">SALES</a>
      <a href="../pages/service.php">SERVICE</a>
      <a href="../pages/about.php">ABOUT US</a>
      <a href="../pages/login.php" class="mobile-login">LOG IN</a>
    </nav>
    <div class="user-section">
      <a href="../pages/login.php">LOG IN</a>
    </div>
  </div>
  </header>

  <script>
    function toggleMenu() {
      const nav = document.getElementById('navMenu');
      const toggle = document.querySelector('.menu-toggle');
      
      nav.classList.toggle('active');
      toggle.classList.toggle('active');
    }

    // Close menu when clicking on a link (mobile)
    document.querySelectorAll('nav a').forEach(link => {
      link.addEventListener('click', () => {
        const nav = document.getElementById('navMenu');
        const toggle = document.querySelector('.menu-toggle');
        nav.classList.remove('active');
        toggle.classList.remove('active');
      });
    });

    // Close mobile menu on resize
    window.addEventListener('resize', () => {
      if (window.innerWidth > 767) {
        const nav = document.getElementById('navMenu');
        const toggle = document.querySelector('.menu-toggle');
        nav.classList.remove('active');
        toggle.classList.remove('active');
      }
    });

    // Header scroll effect
    window.addEventListener('scroll', () => {
      const header = document.querySelector('header');
      if (window.scrollY > 50) {
        header.style.background = 'rgba(24, 24, 24, 0.95)';
        header.style.backdropFilter = 'blur(10px)';
      } else {
        header.style.background = '#181818';
        header.style.backdropFilter = 'none';
      }
    });
  </script>
