<?php 
$pageTitle = "Sales & Promotions - Mitsubishi Motors";
include 'header.php'; 
?>

<style>
  body {
    background-image: url(../includes/images/backbg.jpg); 
    background-size: cover; /* scales image to cover the whole area */
    background-position: center; /* centers the image */
    background-repeat: no-repeat;
    zoom: 90%;
  }
  .container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 32px;
  }
  .page-title {
    color: #ffffff;
    font-size: 2.5rem;
    font-weight: bold;
    margin-bottom: 24px;
    text-align: center;
  }
  .sales-section {
    margin: 32px 0;
  }
  .section-title {
    font-size: 1.8rem;
    color: #ff0000ef;
    margin-bottom: 16px;
  }
  .promo-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 24px;
    margin-top: 24px;
  }
  .promo-card {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 24px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 215, 0, 0.2);
  }
  .promo-title {
    font-size: 1.3rem;
    font-weight: bold;
    color: #ffd700;
    margin-bottom: 12px;
  }
  .promo-description {
    color: #ccc;
    margin-bottom: 16px;
    line-height: 1.5;
  }
  .promo-btn {
    background: #ffd700;
    color: #b80000;
    border: none;
    padding: 12px 24px;
    border-radius: 6px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
  }
  .promo-btn:hover {
    background: #b80000;
    color: #ffd700;
  }
  .contact-section {
    background: #5a5a5aec;
    border-radius: 12px;
    padding: 24px;
    margin-top: 32px;
    text-align: center;
  }
  .contact-info {
    margin: 16px 0;
    font-size: 1.1rem;
  }

  /* Large Devices: min-width = 992px and max-width = 1199px */
  @media (min-width: 992px) and (max-width: 1199px) {
    .container {
      padding: 28px;
    }
    .promo-grid {
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    }
  }

  /* Medium Devices: min-width = 768px and max-width = 991px */
  @media (min-width: 768px) and (max-width: 991px) {
    .container {
      padding: 24px 20px;
    }
    .page-title {
      font-size: 2.2rem;
    }
    .promo-grid {
      grid-template-columns: 1fr;
      gap: 20px;
    }
  }

  /* Small Devices: min-width = 576px and max-width = 767px */
  @media (min-width: 576px) and (max-width: 767px) {
    .container {
      padding: 20px 15px;
    }
    .page-title {
      font-size: 1.8rem;
    }
    .promo-grid {
      grid-template-columns: 1fr;
      gap: 18px;
    }
  }

  /* Extra Small Devices: max-width = 575px */
  @media (max-width: 575px) {
    .container {
      padding: 16px 10px;
    }
    .page-title {
      font-size: 1.5rem;
    }
    .section-title {
      font-size: 1.4rem;
    }
    .promo-card, .contact-section {
      padding: 18px;
    }
  }

  /* Modern UI Enhancements */
  .page-intro {
    color: #000000;
    text-align: center;
    margin-bottom: 40px;
  }

  .page-intro p {
    color: #ffffffff;
    max-width: 800px;
    margin: 15px auto 0;
    font-size: 1.1rem;
    line-height: 1.6;
  }

  .featured-promo {
    position: relative;
    background: linear-gradient(135deg, rgba(139, 0, 0, 0.4), rgba(0, 0, 0, 0.8));
    border-radius: 15px;
    overflow: hidden;
    margin-bottom: 50px;
    border: 1px solid rgba(255, 215, 0, 0.3);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    display: grid;
    grid-template-columns: 1fr;
  }

  @media (min-width: 992px) {
    .featured-promo {
      grid-template-columns: 1fr 1fr;
    }
  }

  .featured-promo-content {
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 40px;
    color: white;
  }

  .featured-promo-image {
    height: 300px;
    background: url('../../includes/images/promos/montero-promo.jpg') center/cover;
    position: relative;
  }

  .featured-promo-badge {
    position: absolute;
    top: 20px;
    right: 20px;
    background: #ffd700;
    color: #b80000;
    font-weight: bold;
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 0.9rem;
    text-transform: uppercase;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    transform: rotate(5deg);
  }

  .featured-promo-title {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 15px;
    background: linear-gradient(to right, #ffd700, #ffec8b);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
  }

  .featured-promo-subtitle {
    font-size: 1.4rem;
    margin-bottom: 20px;
    color: #ddd;
  }

  .featured-promo-description {
    font-size: 1.1rem;
    color: #ccc;
    margin-bottom: 30px;
    line-height: 1.6;
  }

  .countdown-container {
    margin: 20px 0 30px;
    text-align: center;
  }

  .countdown-title {
    font-size: 1rem;
    color: #ddd;
    margin-bottom: 10px;
    text-transform: uppercase;
    letter-spacing: 1px;
  }

  .countdown {
    display: flex;
    gap: 15px;
    justify-content: center;
  }

  .countdown-item {
    display: flex;
    flex-direction: column;
    align-items: center;
  }

  .countdown-value {
    font-size: 1.8rem;
    font-weight: bold;
    background: rgba(255, 215, 0, 0.2);
    color: #ffd700;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    margin-bottom: 5px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(255, 215, 0, 0.3);
  }

  .countdown-label {
    font-size: 0.8rem;
    color: #ddd;
    text-transform: uppercase;
    letter-spacing: 1px;
  }

  .promo-card {
    position: relative;
    background: rgba(0, 0, 0, 0.5);
    border-radius: 15px;
    padding: 30px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 215, 0, 0.2);
    transition: all 0.4s ease;
    overflow: hidden;
  }

  .promo-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle at top right, rgba(255, 215, 0, 0.1), transparent 70%);
    opacity: 0;
    transition: opacity 0.4s ease;
  }

  .promo-card:hover {
    transform: translateY(-10px);
    border-color: rgba(255, 215, 0, 0.5);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
  }

  .promo-card:hover::before {
    opacity: 1;
  }

  .promo-badge {
    display: inline-block;
    background: #ffd700;
    color: #b80000;
    font-size: 0.8rem;
    font-weight: bold;
    padding: 5px 12px;
    border-radius: 15px;
    margin-bottom: 15px;
    text-transform: uppercase;
  }

  .promo-title {
    font-size: 1.5rem;
    font-weight: 700;
    background: linear-gradient(to right, #ffd700, #ffec8b);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 15px;
    position: relative;
    z-index: 1;
  }

  .promo-description {
    color: #ddd;
    margin-bottom: 25px;
    line-height: 1.6;
    position: relative;
    z-index: 1;
  }

  .promo-details {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 20px;
  }

  .promo-detail {
    background: rgba(255, 255, 255, 0.1);
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 0.9rem;
    color: #ccc;
    display: flex;
    align-items: center;
    gap: 5px;
  }

  .promo-detail i {
    color: #ffd700;
  }

  .promo-btn {
    background: linear-gradient(45deg, #ffd700, #ffec8b, #ffd700);
    background-size: 200% 200%;
    color: #b80000;
    border: none;
    padding: 12px 24px;
    border-radius: 6px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.5s ease;
    position: relative;
    z-index: 1;
    overflow: hidden;
  }

  .promo-btn:hover {
    background-position: right center;
    color: #000;
    box-shadow: 0 5px 15px rgba(255, 215, 0, 0.4);
  }

  .promo-btn::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 150%;
    height: 200%;
    background: radial-gradient(circle, rgba(255, 215, 0, 0.4), transparent 60%);
    transform: translate(-50%, -50%) scale(0);
    opacity: 0;
    transition: transform 0.5s ease, opacity 0.5s ease;
    z-index: -1;
  }

  .promo-btn:hover::after {
    transform: translate(-50%, -50%) scale(1);
    opacity: 1;
  }

  .contact-section {
    position: relative;
    background: #5a5a5aec;
    border-radius: 15px;
    padding: 40px;
    margin-top: 50px;
    margin-bottom: 20px;
    text-align: center;
    border: 1px solid rgba(255, 215, 0, 0.3);
    overflow: hidden;
  }

  .contact-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: url('../../includes/images/pattern.png');
    opacity: 0.05;
    z-index: 0;
  }

  .test-drive-form {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
    max-width: 800px;
    margin: 30px auto 0;
    position: relative;
    z-index: 1;
  }

  @media (min-width: 768px) {
    .test-drive-form {
      grid-template-columns: 1fr 1fr;
    }
  }

  .form-group {
    position: relative;
  }

  .form-control {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid rgba(255, 215, 0, 0.3);
    border-radius: 8px;
    background: rgba(0, 0, 0, 0.2);
    color: white;
    font-size: 1rem;
    transition: all 0.3s ease;
  }

  .form-control:focus {
    border-color: #ffd700;
    box-shadow: 0 0 8px rgba(255, 215, 0, 0.4);
    outline: none;
  }

  .input-icon {
    position: absolute;
    right: 15px;
    top: 12px;
    color: #ffd700;
    font-size: 1.1rem;
  }

  .test-drive-btn {
    background: linear-gradient(45deg, #ffd700, #ffec8b);
    color: #b80000;
    border: none;
    padding: 14px 32px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 1.1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    grid-column: 1 / -1;
  }

  .test-drive-btn:hover {
    background: linear-gradient(45deg, #ffec8b, #ffd700);
    transform: translateY(-3px);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.3);
  }

  .contact-info {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 30px;
    margin: 25px 0;
    position: relative;
    z-index: 1;
  }

  .contact-item {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #ddd;
  }

  .contact-icon {
    width: 40px;
    height: 40px;
    background: rgba(255, 215, 0, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffd700;
    font-size: 1.2rem;
  }

  .financing-options {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    justify-content: center;
    margin: 40px 0 20px;
  }

  .financing-logo {
    height: 40px;
    filter: grayscale(100%) brightness(1.5);
    opacity: 0.7;
    transition: all 0.3s ease;
  }

  .financing-logo:hover {
    filter: grayscale(0%) brightness(1);
    opacity: 1;
    transform: scale(1.1);
  }

  @media (max-width: 575px) {
    .featured-promo-content {
      padding: 25px;
    }
    
    .featured-promo-title {
      font-size: 1.8rem;
    }
    
    .countdown-value {
      font-size: 1.5rem;
      width: 50px;
      height: 50px;
    }
    
    .contact-section {
      padding: 30px 20px;
    }
    
    .test-drive-form {
      gap: 15px;
    }
  }
</style>

<!-- Font Awesome for Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="container">
  <h1 class="page-title">Sales & Promotions</h1>
  
  <div class="page-intro">
    <p>Discover exclusive Mitsubishi deals and financing options at our San Pablo City dealership. Take advantage of our limited-time offers to drive home your dream car today.</p>
  </div>
  

  
  <div class="sales-section">
    <h2 class="section-title">Current Promotions</h2>
    
    <div class="promo-grid">
      <div class="promo-card">
        <div class="promo-badge">Popular</div>
        <div class="promo-title">Zero Interest Rate</div>
        <div class="promo-description">
          Get 0% interest rate for the first 2 years on selected Mitsubishi models with our partner banks.
        </div>
        <div class="promo-details">
          <div class="promo-detail"><i class="fas fa-car"></i> All Models</div>
          <div class="promo-detail"><i class="fas fa-calendar-alt"></i> Until Aug 31</div>
          <div class="promo-detail"><i class="fas fa-tag"></i> 20% DP</div>
        </div>
        <button class="promo-btn"><i class="fas fa-info-circle"></i> Learn More</button>
      </div>
      
      <div class="promo-card">
        <div class="promo-badge">Best Value</div>
        <div class="promo-title">Trade-In Program</div>
        <div class="promo-description">
          Trade in your old vehicle and get up to ₱100,000 discount on your new Mitsubishi purchase.
        </div>
        <div class="promo-details">
          <div class="promo-detail"><i class="fas fa-exchange-alt"></i> Any Brand</div>
          <div class="promo-detail"><i class="fas fa-calendar-alt"></i> Ongoing</div>
          <div class="promo-detail"><i class="fas fa-file-alt"></i> Free Appraisal</div>
        </div>
        <button class="promo-btn"><i class="fas fa-calculator"></i> Get Quote</button>
      </div>
      
      <div class="promo-card">
        <div class="promo-title">Fleet Discount</div>
        <div class="promo-description">
          Special pricing for fleet purchases. Buy 3 or more vehicles and enjoy exclusive corporate rates and benefits.
        </div>
        <div class="promo-details">
          <div class="promo-detail"><i class="fas fa-building"></i> For Businesses</div>
          <div class="promo-detail"><i class="fas fa-users"></i> 3+ Vehicles</div>
          <div class="promo-detail"><i class="fas fa-tools"></i> Service Package</div>
        </div>
        <button class="promo-btn"><i class="fas fa-handshake"></i> Contact Sales</button>
      </div>
      
      <div class="promo-card">
        <div class="promo-title">Student & Graduate Discount</div>
        <div class="promo-description">
          Fresh graduates and students get special pricing and flexible payment terms with minimal requirements.
        </div>
        <div class="promo-details">
          <div class="promo-detail"><i class="fas fa-graduation-cap"></i> With Valid ID</div>
          <div class="promo-detail"><i class="fas fa-percentage"></i> Low DP</div>
          <div class="promo-detail"><i class="fas fa-calendar-alt"></i> Extended Terms</div>
        </div>
        <button class="promo-btn"><i class="fas fa-file-signature"></i> Apply Now</button>
      </div>
    </div>
  </div>

  <div class="contact-section">
    <h2 class="section-title; color: #ffffffff;">Contact Or Visit Us</h2>
    <p style="color: #ddd; position: relative; z-index: 1;">
      Experience the performance and comfort of a Mitsubishi vehicle firsthand. 
      Fill out the form below and our sales team will contact you shortly.
    </p>
    
    <div class="contact-info">
      <div class="contact-item">
        <div class="contact-icon"><i class="fas fa-phone"></i></div>
        <span>(049) 503-9693</span>
      </div>
      <div class="contact-item">
        <div class="contact-icon"><i class="fas fa-envelope"></i></div>
        <span>smf.hr@yahoo.com</span>
      </div>
      <div class="contact-item">
        <div class="contact-icon"><i class="fas fa-map-marker-alt"></i></div>
        <span>Km 85.5 Maharlika Highway, Brgy.San Ignacio, San Pablo City Laguna</span>
      </div>
      <div class="contact-item">
        <div class="contact-icon"><i class="fas fa-clock"></i></div>
        <span>Mon-Sat: 8:00 AM - 6:00 PM</span>
      </div>
    </div>
    
    
    
</div>

<script>
  // Responsive UI/UX Detection and Adaptation System
  function detectScreenSize() {
    const width = window.innerWidth;
    const body = document.body;
    
    // Remove all size classes
    body.classList.remove('xs-screen', 'sm-screen', 'md-screen', 'lg-screen', 'xl-screen');
    
    // Add appropriate size class
    if (width <= 575) {
      body.classList.add('xs-screen');
    } else if (width <= 767) {
      body.classList.add('sm-screen');
    } else if (width <= 991) {
      body.classList.add('md-screen');
    } else if (width <= 1199) {
      body.classList.add('lg-screen');
    } else {
      body.classList.add('xl-screen');
    }
    
    // Adjust promo grid layout
    const promoGrid = document.querySelector('.promo-grid');
    if (promoGrid) {
      if (width <= 767) {
        promoGrid.style.gridTemplateColumns = '1fr';
      } else if (width <= 991) {
        promoGrid.style.gridTemplateColumns = 'repeat(auto-fit, minmax(300px, 1fr))';
      } else {
        promoGrid.style.gridTemplateColumns = 'repeat(auto-fit, minmax(350px, 1fr))';
      }
    }
  }

  // Initialize on page load
  document.addEventListener('DOMContentLoaded', detectScreenSize);
  
  // Listen for window resize
  window.addEventListener('resize', detectScreenSize);

  // Countdown Timer
  document.addEventListener('DOMContentLoaded', function() {
    // Set the date we're counting down to (5 days from now)
    const countDownDate = new Date();
    countDownDate.setDate(countDownDate.getDate() + 5);
    countDownDate.setHours(23, 59, 59, 0);
    
    function updateCountdown() {
      // Get current date and time
      const now = new Date().getTime();
      
      // Find the distance between now and the countdown date
      const distance = countDownDate - now;
      
      // Time calculations for days, hours, minutes and seconds
      const days = Math.floor(distance / (1000 * 60 * 60 * 24));
      const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
      const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
      const seconds = Math.floor((distance % (1000 * 60)) / 1000);
      
      // Display the result
      document.getElementById("days").innerHTML = days < 10 ? "0" + days : days;
      document.getElementById("hours").innerHTML = hours < 10 ? "0" + hours : hours;
      document.getElementById("minutes").innerHTML = minutes < 10 ? "0" + minutes : minutes;
      document.getElementById("seconds").innerHTML = seconds < 10 ? "0" + seconds : seconds;
      
      // If the countdown is finished, display expired message
      if (distance < 0) {
        clearInterval(x);
        document.getElementById("promotion-countdown").innerHTML = "EXPIRED";
      }
    }
    
    // Update countdown every 1 second
    updateCountdown();
    const x = setInterval(updateCountdown, 1000);
    
    // Interactive cards animation
    const promoCards = document.querySelectorAll('.promo-card');
    
    promoCards.forEach(card => {
      card.addEventListener('mouseenter', function() {
        promoCards.forEach(c => {
          if (c !== this) {
            c.style.transform = 'scale(0.98)';
            c.style.opacity = '0.7';
          }
        });
      });
      
      card.addEventListener('mouseleave', function() {
        promoCards.forEach(c => {
          c.style.transform = '';
          c.style.opacity = '1';
        });
      });
    });
    
    // Form submission animation
    const testDriveForm = document.querySelector('.test-drive-form');
    
    if (testDriveForm) {
      testDriveForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const submitBtn = document.querySelector('.test-drive-btn');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        submitBtn.disabled = true;
        
        // Simulate form submission
        setTimeout(() => {
          testDriveForm.innerHTML = `
            <div style="text-align: center; grid-column: 1 / -1; padding: 20px;">
              <i class="fas fa-check-circle" style="font-size: 3rem; color: #4ceb95; margin-bottom: 15px;"></i>
              <h3 style="color: #ffd700; margin-bottom: 10px;">Test Drive Scheduled!</h3>
              <p style="color: #ddd;">Thank you for your interest. Our sales team will contact you shortly to confirm your test drive appointment.</p>
            </div>
          `;
        }, 1500);
      });
    }
  });
</script>

<?php include 'footer.php'; ?>
