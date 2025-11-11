<?php
$pageTitle = "San Pablo City - Mitsubishi Motors";

// Set base paths for landing page (at root level)
$css_path = 'css/';
$js_path = 'js/';
$includes_path = 'includes/';
$pages_path = 'pages/';

require_once 'includes/init.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title><?php echo isset($pageTitle) ? $pageTitle : 'Mitsubishi Motors San Pablo City'; ?></title>
  <link rel="icon" type="image/png" href="includes/images/mitsubishi_logo.png">
  <link rel="icon" type="image/x-icon" href="includes/images/mitsubishi_logo.png">
  
  <?php 
  // Mobile Responsive Include - Optimized 2025 Standards
  include 'includes/components/mobile-responsive-include.php'; 
  ?>
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
      gap: 15px; /* Add gap between items */
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
      gap: 40px;
      order: 3; /* Place after hamburger */
    }
    
    .logo a {
      order: 1; /* Place first */
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
      padding: 10px;
      background: transparent;
      border: none;
      z-index: 1002;
      position: relative; /* Ensure it's in the stacking context */
      flex-shrink: 0; /* Don't let it shrink */
      order: 2; /* Place after logo */
    }
    
    .menu-toggle span {
      width: 24px;
      height: 2px;
      background-color: #ffd700;
      margin: 2px 0;
      transition: all 0.3s ease;
      transform-origin: center;
    }
    
    /* Menu Toggle Animation */
    .menu-toggle.active span:nth-child(1) {
      transform: translateY(6px) rotate(45deg);
    }
    
    .menu-toggle.active span:nth-child(2) {
      opacity: 0;
      transform: scale(0);
    }
    
    .menu-toggle.active span:nth-child(3) {
      transform: translateY(-6px) rotate(-45deg);
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
        flex-wrap: wrap; /* Allow wrapping if needed */
      }
      
      .menu-toggle {
        display: flex !important; /* Force display on mobile */
        cursor: pointer;
        flex-direction: column;
        margin-left: auto; /* Push to right */
        touch-action: manipulation; /* Better mobile click */
      }
      
      /* Hide desktop nav container on mobile */
      .nav-container {
        display: none !important; /* Hide completely on mobile */
      }

      /* Mobile navigation menu */
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
        z-index: 998; /* Below hamburger but above content */
      }
      
      nav.active {
        display: flex !important; /* Force display when active */
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
  
  <!-- Load Enhanced Mobile Fix JavaScript -->
  <script src="js/mobile-fix-enhanced.js" defer></script>
  <!-- Load Comprehensive Mobile Responsive Fix JavaScript -->
  <script src="js/mobile-responsive-fix.js" defer></script>
</head>
<body>
  <header>
    <a href="landingpage.php" style="text-decoration: none; color: inherit;">
      <div class="logo">
        <img src="includes/images/mitsubishi_logo.png" alt="Mitsubishi Logo">
        <div>
          <strong>MITSUBISHI MOTORS</strong><br>
          <small>Drive Your Ambition</small>
        </div>
      </div>
    </a>
    
    <!-- Hamburger menu button (mobile only) -->
    <button class="menu-toggle" onclick="toggleMenu()" aria-label="Toggle menu" type="button">
      <span></span>
      <span></span>
      <span></span>
    </button>
    
    <!-- Desktop navigation -->
    <div class="nav-container">
      <nav>
        <a href="pages/cars.php">CARS</a>
        <a href="pages/sales.php">SALES</a>
        <a href="pages/service.php">SERVICE</a>
        <a href="pages/about.php">ABOUT US</a>
      </nav>
      <div class="user-section">
        <a href="pages/login.php">LOG IN</a>
      </div>
    </div>
    
    <!-- Mobile navigation menu (hidden by default) -->
    <nav id="navMenu">
      <a href="pages/cars.php">CARS</a>
      <a href="pages/sales.php">SALES</a>
      <a href="pages/service.php">SERVICE</a>
      <a href="pages/about.php">ABOUT US</a>
      <a href="pages/login.php" class="mobile-login">LOG IN</a>
    </nav>
  </header>

  <script>
    function toggleMenu() {
      const nav = document.getElementById('navMenu');
      const toggle = document.querySelector('.menu-toggle');
      
      if (!nav || !toggle) {
        console.error('Navigation elements not found');
        return;
      }
      
      nav.classList.toggle('active');
      toggle.classList.toggle('active');
      
      console.log('Menu toggled - nav active:', nav.classList.contains('active'));
    }

    // Close menu when clicking on a link (mobile)
    document.querySelectorAll('nav a').forEach(link => {
      link.addEventListener('click', () => {
        const nav = document.getElementById('navMenu');
        const toggle = document.querySelector('.menu-toggle');
        if (nav && toggle) {
          nav.classList.remove('active');
          toggle.classList.remove('active');
        }
      });
    });

    // Close mobile menu on resize
    window.addEventListener('resize', () => {
      if (window.innerWidth > 1024) {
        const nav = document.getElementById('navMenu');
        const toggle = document.querySelector('.menu-toggle');
        if (nav && toggle) {
          nav.classList.remove('active');
          toggle.classList.remove('active');
        }
      }
    });

    // Header scroll effect
    window.addEventListener('scroll', () => {
      const header = document.querySelector('header');
      if (header) {
        if (window.scrollY > 50) {
          header.style.background = 'rgba(24, 24, 24, 0.95)';
          header.style.backdropFilter = 'blur(10px)';
        } else {
          header.style.background = '#181818';
          header.style.backdropFilter = 'none';
        }
      }
    });
    
    // Debug: Log when DOM is ready
    document.addEventListener('DOMContentLoaded', () => {
      console.log('Landing page loaded');
      console.log('Nav menu:', document.getElementById('navMenu'));
      console.log('Menu toggle:', document.querySelector('.menu-toggle'));
    });
  </script>

<style>
  /* Zoom out the entire page to 7% */
  body {
    zoom: 90%;
  }
  
  /* Hero Banner Section */
  .hero-banner {
    position: relative;
    height: 100vh;
    /* Changed from 70vh to 100vh */
    display: flex;
    align-items: center;
    justify-content: flex-start;
    padding: 0 32px;
    overflow: hidden;
  }

  /* Image Slider */
  .hero-slider {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1;
  }

  .slide {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    transition: opacity 1s ease-in-out;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
  }

  .slide.active {
    opacity: 1;
  }

  .slide::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(0, 0, 0, 0.3), rgba(0, 0, 0, 0.2));
    z-index: 1;
  }

  .slide1 {
    background-image: url('../includes/images/1.webp');
  }

  .slide2 {
    background-image: url('../includes/images/2.webp');
  }

  .slide3 {
    background-image: url('../includes/images/3.webp');
  }

  /* Slider Navigation */
  .slider-nav {
    position: absolute;
    bottom: 30px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 15px;
    z-index: 10;
  }

  .nav-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.4);
    border: 2px solid #ffd700;
    cursor: pointer;
    transition: all 0.3s ease;
  }

  .nav-dot.active {
    background: #ffd700;
    transform: scale(1.2);
  }

  .hero-content {
    max-width: 600px;
    z-index: 5;
    position: relative;
  }

  .hero-badge {
    display: inline-block;
    background: #ffd700;
    color: #111;
    padding: 8px 20px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: bold;
    margin-bottom: 20px;
    text-transform: uppercase;
    letter-spacing: 1px;
  }

  .hero-title {
    font-size: 3.2rem;
    font-weight: 700;
    line-height: 1.1;
    margin-bottom: 20px;
    color: #fff;
  }

  .hero-title .highlight {
    color: #ffd700;
  }

  .hero-description {
    font-size: 1.1rem;
    color: #ccc;
    margin-bottom: 30px;
    line-height: 1.6;
  }

  .hero-actions {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
  }

  .btn-primary {
    background: #ffd700;
    color: #111;
    padding: 14px 28px;
    border: none;
    border-radius: 6px;
    font-weight: 700;
    text-decoration: none;
    font-size: 1rem;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .btn-primary:hover {
    background: #e6c200;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(255, 215, 0, 0.3);
  }

  .btn-secondary {
    background: transparent;
    color: #ffd700;
    padding: 14px 28px;
    border: 2px solid #ffd700;
    border-radius: 6px;
    font-weight: 700;
    text-decoration: none;
    font-size: 1rem;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .btn-secondary:hover {
    background: #ffd700;
    color: #111;
  }

  /* Vehicle Showcase */
  .vehicle-showcase {
    padding: 80px 32px;
    background: rgba(24, 24, 24, 0.8);
  }

  .section-header {
    text-align: center;
    margin-bottom: 60px;
  }

  .section-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 15px;
  }

  .section-subtitle {
    font-size: 1.1rem;
    color: #ccc;
    max-width: 600px;
    margin: 0 auto;
  }

  .vehicle-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 30px;
    margin-top: 50px;
  }

  .vehicle-card {
    background: linear-gradient(145deg, rgba(139, 0, 0, 0.3), rgba(17, 17, 17, 0.9));
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
    border: 1px solid rgba(255, 215, 0, 0.2);
  }

  .vehicle-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 40px rgba(255, 215, 0, 0.2);
    border-color: #ffd700;
  }

  .vehicle-image {
    height: 200px;
    background: linear-gradient(45deg, #333, #555);
    position: relative;
    overflow: hidden;
  }

  .vehicle-info {
    padding: 25px;
  }

  .vehicle-name {
    font-size: 1.4rem;
    font-weight: 700;
    color: #ffd700;
    margin-bottom: 10px;
  }

  .vehicle-price {
    font-size: 1.1rem;
    color: #fff;
    font-weight: 600;
    margin-bottom: 15px;
  }

  .vehicle-features {
    color: #ccc;
    font-size: 0.9rem;
    line-height: 1.5;
    margin-bottom: 20px;
  }

  .view-details {
    color: #ffd700;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: gap 0.3s ease;
  }

  .view-details:hover {
    gap: 10px;
  }

  /* Services Section with Parallax */
  .services-section {
    padding: 80px 32px;
    background: url('../includes/images/parallax.webp') center/cover fixed;
    position: relative;
    background-attachment: fixed;
    background-repeat: no-repeat;
    background-size: cover;
    background-position: center;
    min-height: 600px;
  }

  .services-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1;
  }

  .services-section .section-header,
  .services-section .services-grid {
    position: relative;
    z-index: 2;
  }

  .services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
    margin-top: 50px;
  }

  .service-card {
    background: rgba(24, 24, 24, 0.9);
    padding: 40px 30px;
    border-radius: 12px;
    text-align: center;
    border: 1px solid rgba(255, 215, 0, 0.3);
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
  }

  .service-card:hover {
    transform: translateY(-5px);
    border-color: #ffd700;
    background: rgba(24, 24, 24, 0.95);
  }

  .service-icon {
    width: 70px;
    height: 70px;
    background: linear-gradient(45deg, #ffd700, #e6c200);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 25px;
    font-size: 1.8rem;
    color: #111;
  }

  .service-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 15px;
  }

  .service-description {
    color: #ccc;
    line-height: 1.6;
    font-size: 0.95rem;
  }

  /* News Section */
  .news-section is {
    padding: 80px 32px;
    background: rgba(24, 24, 24, 0.6);
  }

  .news-item {
    max-width: 800px;
    margin: 0 auto;
    background: linear-gradient(145deg, rgba(139, 0, 0, 0.3), rgba(17, 17, 17, 0.8));
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid rgba(255, 215, 0, 0.2);
    transition: all 0.3s ease;
  }

  .news-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(255, 215, 0, 0.2);
  }

  .news-image {
    height: 300px;
    background: url('../includes/images/SanPablo-MZM-2.webp') center/cover;
    /* Updated image */
    position: relative;
  }

  .news-content {
    padding: 40px;
  }

  .news-category {
    display: inline-block;
    background: #ffd700;
    color: #111;
    padding: 6px 15px;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 20px;
  }

  .news-title {
    font-size: 1.8rem;
    font-weight: 700;
    color: #fff;
    line-height: 1.3;
    margin-bottom: 15px;
  }

  .news-excerpt {
    color: #ccc;
    line-height: 1.6;
    margin-bottom: 25px;
  }

  .news-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 20px;
    border-top: 1px solid rgba(255, 215, 0, 0.2);
  }

  .news-date {
    color: #999;
    font-size: 0.9rem;
  }

  .read-more {
    color: #ffd700;
    text-decoration: none;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: gap 0.3s ease;
  }

  .read-more:hover {
    gap: 10px;
  }

  /* CTA Section with Parallax */
  .cta-section {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 80px 32px;
    background: url('../includes/images/parallax2.webp') center/cover fixed;
    position: relative;
    background-attachment: fixed;
    background-repeat: no-repeat;
    background-size: cover;
    background-position: center;
    min-height: 500px;
    border-radius: 0;
  }

  .cta-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    z-index: 1;
  }

  .cta-title,
  .cta-description,
  .cta-btn {
    position: relative;
    z-index: 2;
  }

  .cta-title {
    font-size: 2rem;
    font-weight: 700;
    color: #ffd700;
    margin-bottom: 15px;
    text-align: center;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
  }

  .cta-description {
    color: #ccc;
    text-align: center;
    margin-bottom: 30px;
    max-width: 500px;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
  }

  .cta-btn {
    background: #ffd700;
    color: #111;
    font-weight: bold;
    font-size: 1.1rem;
    border: none;
    border-radius: 6px;
    padding: 16px 40px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .cta-btn:hover {
    background: #e6c200;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(255, 215, 0, 0.3);
  }

  /* Responsive Design */
  @media (max-width: 991px) {

    .services-section,
    .cta-section {
      background-attachment: scroll;
    }
  }

  @media (max-width: 767px) {
    .hero-banner {
      height: 70vh;
      /* Changed from 50vh to 70vh for better mobile experience */
      padding: 0 15px;
      text-align: center;
      justify-content: center;
    }

    .hero-title {
      font-size: 2rem;
    }

    .hero-actions {
      justify-content: center;
    }

    .vehicle-showcase,
    .services-section,
    .news-section {
      padding: 50px 15px;
    }

    .vehicle-grid,
    .services-grid {
      grid-template-columns: 1fr;
    }

    .section-title {
      font-size: 1.8rem;
    }

    .cta-section {
      padding: 50px 15px;
    }

    .cta-title {
      font-size: 1.6rem;
    }
  }

  @media (max-width: 575px) {
    .hero-banner {
      height: 60vh;
      /* Changed from 45vh to 60vh for better mobile experience */
      padding: 0 10px;
    }

    .hero-title {
      font-size: 1.6rem;
    }

    .hero-description {
      font-size: 1rem;
    }

    .hero-actions {
      flex-direction: column;
      align-items: center;
      gap: 10px;
    }

    .btn-primary,
    .btn-secondary {
      width: 200px;
      text-align: center;
      padding: 12px 20px;
    }

    .vehicle-showcase,
    .services-section,
    .news-section {
      padding: 40px 10px;
    }

    .section-title {
      font-size: 1.5rem;
    }

    .vehicle-grid {
      gap: 20px;
    }

    .services-grid {
      gap: 20px;
    }

    .service-card,
    .vehicle-card {
      padding: 25px 20px;
    }

    .news-content {
      padding: 25px 20px;
    }

    .cta-section {
      padding: 40px 10px;
    }

    .cta-title {
      font-size: 1.4rem;
    }

    .cta-btn {
      width: 90%;
      font-size: 1rem;
      padding: 14px 0;
    }
  }
</style>

<!-- Hero Banner -->
<section class="hero-banner">
  <div class="hero-slider">
    <div class="slide slide1 active"></div>
    <div class="slide slide2"></div>
    <div class="slide slide3"></div>
  </div>

  <div class="slider-nav">
    <div class="nav-dot active" onclick="currentSlide(1)"></div>
    <div class="nav-dot" onclick="currentSlide(2)"></div>
    <div class="nav-dot" onclick="currentSlide(3)"></div>
  </div>

  <div class="hero-content">
    <div class="hero-badge">New Dealership</div>
    <h1 class="hero-title">Drive Your <span class="highlight">Ambition</span></h1>
    <p class="hero-description">Experience the perfect blend of innovation, performance, and reliability with Mitsubishi Motors Philippines</p>
    <div class="hero-actions">
      <a href="cars.php" class="btn-primary">Explore Vehicles</a>
      <a href="about.php" class="btn-secondary">Learn More</a>
    </div>
  </div>
</section>

<!-- Vehicle Showcase -->
<section class="vehicle-showcase">
  <div class="section-header">
    <h2 class="section-title">Our Vehicle Lineup</h2>
    <p class="section-subtitle">Discover our range of innovative and reliable vehicles designed for every journey</p>
  </div>
  <div class="vehicle-grid">
    <div class="vehicle-card">
      <div class="vehicle-image"></div>
      <div class="vehicle-info">
        <h3 class="vehicle-name">Montero Sport</h3>
        <p class="vehicle-price">Starting at ₱1,685,000</p>
        <p class="vehicle-features">7-seater SUV with advanced 4WD system and premium comfort</p>
        <a href="cars.php" class="view-details">View Details →</a>
      </div>
    </div>
    <div class="vehicle-card">
      <div class="vehicle-image"></div>
      <div class="vehicle-info">
        <h3 class="vehicle-name">Xpander</h3>
        <p class="vehicle-price">Starting at ₱1,035,000</p>
        <p class="vehicle-features">Spacious MPV perfect for families with flexible seating</p>
        <a href="cars.php" class="view-details">View Details →</a>
      </div>
    </div>
    <div class="vehicle-card">
      <div class="vehicle-image"></div>
      <div class="vehicle-info">
        <h3 class="vehicle-name">Mirage</h3>
        <p class="vehicle-price">Starting at ₱745,000</p>
        <p class="vehicle-features">Fuel-efficient compact car with modern features</p>
        <a href="cars.php" class="view-details">View Details →</a>
      </div>
    </div>
  </div>
</section>

<!-- Services Section -->
<section class="services-section">
  <div class="section-header">
    <h2 class="section-title">Our Services</h2>
    <p class="section-subtitle">Comprehensive automotive services to keep you moving forward</p>
  </div>
  <div class="services-grid">
    <div class="service-card">
      <div class="service-icon">🚗</div>
      <h3 class="service-title">Vehicle Sales</h3>
      <p class="service-description">Browse our complete lineup of new Mitsubishi vehicles with flexible financing options</p>
    </div>
    <div class="service-card">
      <div class="service-icon">🔧</div>
      <h3 class="service-title">After Sales Service</h3>
      <p class="service-description">Professional maintenance and repair services from certified technicians</p>
    </div>
    <div class="service-card">
      <div class="service-icon">🛡️</div>
      <h3 class="service-title">Warranty & Parts</h3>
      <p class="service-description">Genuine Mitsubishi parts and comprehensive warranty coverage</p>
    </div>
  </div>
</section>

<!-- News Section -->
<section class="news-section">
  <div class="section-header">
    <h2 class="section-title">Latest News</h2>
    <p class="section-subtitle">Stay updated with the latest from Mitsubishi Motors</p>
  </div>
  <div class="news-item">
    <div class="news-image"></div>
    <div class="news-content">
      <div class="news-category">Dealership News</div>
      <h3 class="news-title">San Pablo City Becomes Newest Home of MZM and 64th Mitsubishi Motors Outlet of the Philippines</h3>
      <p class="news-excerpt">We're excited to announce the opening of our newest dealership in San Pablo City, bringing world-class Mitsubishi vehicles and services closer to our valued customers in the region.</p>
      <div class="news-meta">
        <span class="news-date">December 2024</span>
        <a href="https://www.mitsubishi-motors.com.ph/articles/san-pablo-city-becomes-newest-home-of-mzm-and-64th-mitsubishi-motors-outlet-in-the-country" class="read-more" target="_blank" rel="noopener">Read More →</a>
      </div>
    </div>
  </div>
</section>

<!-- Call to Action with Parallax -->
<section class="cta-section">
  <h2 class="cta-title">Ready to Drive Your Ambition?</h2>
  <p class="cta-description">Visit our San Pablo City dealership or explore our services online to start your Mitsubishi journey today</p>
  <a href="login.php" class="cta-btn">Get Started</a>
</section>

<script>
  // Smooth scroll effect for buttons
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
      e.preventDefault();
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        target.scrollIntoView({
          behavior: 'smooth'
        });
      }
    });
  });

  let currentSlideIndex = 0;
  const slides = document.querySelectorAll('.slide');
  const dots = document.querySelectorAll('.nav-dot');
  const totalSlides = slides.length;

  function showSlide(index) {
    slides.forEach(slide => slide.classList.remove('active'));
    dots.forEach(dot => dot.classList.remove('active'));

    slides[index].classList.add('active');
    dots[index].classList.add('active');
  }

  function changeSlide(direction) {
    currentSlideIndex += direction;

    if (currentSlideIndex >= totalSlides) {
      currentSlideIndex = 0;
    } else if (currentSlideIndex < 0) {
      currentSlideIndex = totalSlides - 1;
    }

    showSlide(currentSlideIndex);
  }

  function currentSlide(index) {
    currentSlideIndex = index - 1;
    showSlide(currentSlideIndex);
  }

  function autoSlide() {
    currentSlideIndex = (currentSlideIndex + 1) % totalSlides;
    showSlide(currentSlideIndex);
  }

  let slideInterval = setInterval(autoSlide, 5000);

  const heroSection = document.querySelector('.hero-banner');
  heroSection.addEventListener('mouseenter', () => {
    clearInterval(slideInterval);
  });

  heroSection.addEventListener('mouseleave', () => {
    slideInterval = setInterval(autoSlide, 5000);
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft') {
      changeSlide(-1);
    } else if (e.key === 'ArrowRight') {
      changeSlide(1);
    }
  });
</script>

</body>
</html>