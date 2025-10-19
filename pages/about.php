<?php
$pageTitle = "About Us - Mitsubishi Motors";
include 'header.php';

// Load company settings from database
require_once '../includes/database/db_conn.php';

// Fetch company settings
$companySettings = [];
try {
    $stmt = $connect->prepare("SELECT setting_key, setting_value FROM company_settings");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($results as $row) {
        $companySettings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    error_log("Error loading company settings: " . $e->getMessage());
}

// Helper function to get setting with default value
function getSetting($key, $default = '') {
    global $companySettings;
    return $companySettings[$key] ?? $default;
}
?>

<style>
  body {
    background-image: url(../includes/images/backbg.jpg); 
    background-size: cover; /* scales image to cover the whole area */
    background-position: center; /* centers the image */
    background-repeat: no-repeat;
    zoom: 80%;
  }
  .container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 32px;
  }

  .about-section {
    background: #e60013d0;
    margin: 32px 0;
  }
  .section-title {
    font-size: 1.8rem;
    color: #ffd700;
    margin-bottom: 16px;
  }
  .section-content {
    color: #ccc;
    line-height: 1.6;
    margin-bottom: 24px;
    font-size: 1.1rem;
  }
  .features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 24px;
    margin: 32px 0;
  }
  .feature-card {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 24px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 215, 0, 0.2);
    text-align: center;
  }
  .feature-icon {
    font-size: 2.5rem;
    margin-bottom: 12px;
  }
  .feature-title {
    font-size: 1.2rem;
    font-weight: bold;
    color: #ffd700;
    margin-bottom: 8px;
  }
  .feature-description {
    color: #ccc;
    font-size: 0.95rem;
  }
  .contact-grid {
    background: #8b8b8bcb;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 24px;
    margin-top: 32px;
  }
  .contact-card {
    background: #E60012;
    border-radius: 12px;
    padding: 24px;
    text-align: center;
  }
  .contact-title {
    font-size: 1.3rem;
    color: #ffd700;
    margin-bottom: 16px;
  }
  .contact-info {

    color: #ccc;
    line-height: 1.8;
  }

  /* Modern UI Enhancements */
  .hero-section {
    position: relative;
    background: linear-gradient(rgba(0, 0, 0, 0.66));
    height: 350px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    text-align: center;
    margin-bottom: 40px;
    border-radius: 12px;
    overflow: hidden;
  }
  
  .hero-title {
    font-size: 3rem;
    font-weight: 800;
    color: white;
    margin-bottom: 20px;
    text-shadow: 0 2px 10px rgba(0,0,0,0.3);
  }
  
  .hero-subtitle {
    font-size: 1.2rem;
    color: #ddd;
    max-width: 600px;
    line-height: 1.6;
    text-shadow: 0 2px 5px rgba(0,0,0,0.3);
  }

  .about-section {
    margin: 50px 0;
    position: relative;
  }
  
  .highlight-box {
    position: relative;
    background: linear-gradient(135deg, rgba(139, 0, 0, 0.2), rgba(0, 0, 0, 0.5));
    border-radius: 12px;
    padding: 30px;
    border: 1px solid rgba(255, 215, 0, 0.2);
    margin-bottom: 30px;
  }
  

  
  .section-title::after {
    content: '';
    position: absolute;
    bottom: -8px;
    left: 0;
    width: 60px;
    height: 3px;
    background: #ffd700;
    border-radius: 2px;
  }
  
  .section-content {
    color: #ddd;
    line-height: 1.7;
    margin-bottom: 24px;
    font-size: 1.1rem;
  }
  
  .value-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 30px 0;
  }
  
  .value-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 25px 20px;
    background: rgba(0, 0, 0, 0.3);
    border-radius: 12px;
    border: 1px solid rgba(255, 215, 0, 0.15);
    transition: all 0.3s ease;
  }
  
  .value-item:hover {
    transform: translateY(-5px);
    border-color: rgba(255, 215, 0, 0.5);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
  }
  
  .value-icon {
    font-size: 2.5rem;
    color: #ffd700;
    margin-bottom: 15px;
    transition: transform 0.3s ease;
  }
  
  .value-item:hover .value-icon {
    transform: scale(1.2);
  }
  
  .value-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: white;
    margin-bottom: 10px;
  }
  
  .value-description {
    color: #ccc;
    font-size: 0.95rem;
    line-height: 1.5;
  }

  /* Timeline styling */
  .timeline-section {
    position: relative;
    padding: 50px 0;
  }
  
  .timeline {
    position: relative;
    max-width: 1000px;
    margin: 40px auto 0;
  }
  
  .timeline::after {
    content: '';
    position: absolute;
    width: 4px;
    background: rgba(255, 215, 0, 0.3);
    top: 0;
    bottom: 0;
    left: 50%;
    margin-left: -2px;
  }
  
  .timeline-item {
    position: relative;
    width: 50%;
    padding: 0 40px 50px;
    box-sizing: border-box;
  }
  
  .timeline-item:nth-child(odd) {
    left: 0;
  }
  
  .timeline-item:nth-child(even) {
    left: 50%;
  }
  
  .timeline-content {
    position: relative;
    padding: 25px;
    background: rgba(0, 0, 0, 0.4);
    border-radius: 12px;
    border: 1px solid rgba(255, 215, 0, 0.15);
    transition: all 0.3s ease;
  }
  
  .timeline-content:hover {
    border-color: rgba(255, 215, 0, 0.5);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    transform: translateY(-5px);
  }
  
  .timeline-date {
    display: inline-block;
    background: #ffd700;
    color: #b80000;
    padding: 5px 15px;
    font-size: 0.9rem;
    font-weight: bold;
    border-radius: 20px;
    margin-bottom: 15px;
  }
  
  .timeline-title {
    font-size: 1.3rem;
    color: white;
    margin-bottom: 10px;
  }
  
  .timeline-description {
    color: #ccc;
    line-height: 1.6;
  }
  
  .timeline-dot {
    position: absolute;
    width: 20px;
    height: 20px;
    background: #ffd700;
    border-radius: 50%;
    top: 25px;
    right: -10px;
    z-index: 1;
    box-shadow: 0 0 0 4px rgba(255, 215, 0, 0.2);
  }
  
  .timeline-item:nth-child(even) .timeline-dot {
    left: -10px;
    right: auto;
  }
  
  .timeline-arrow {
    position: absolute;
    width: 0;
    height: 0;
    border-top: 10px solid transparent;
    border-bottom: 10px solid transparent;
    top: 25px;
  }
  
  .timeline-item:nth-child(odd) .timeline-arrow {
    border-left: 10px solid rgba(255, 215, 0, 0.15);
    right: 30px;
  }
  
  .timeline-item:nth-child(even) .timeline-arrow {
    border-right: 10px solid rgba(255, 215, 0, 0.15);
    left: 30px;
  }

  /* Team section */
  .team-section {
    margin: 50px 0;
  }
  
  .team-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 30px;
    margin-top: 40px;
  }
  
  .team-member {
    position: relative;
    background: rgba(0, 0, 0, 0.4);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
    border: 1px solid rgba(255, 215, 0, 0.1);
  }
  
  .team-member:hover {
    transform: translateY(-10px);
    border-color: rgba(255, 215, 0, 0.3);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
  }
  
  .member-image {
    width: 100%;
    height: 250px;
    object-fit: cover;
    transition: transform 0.5s ease;
  }
  
  .team-member:hover .member-image {
    transform: scale(1.05);
  }
  
  .member-info {
    padding: 20px;
    text-align: center;
  }
  
  .member-name {
    font-size: 1.2rem;
    font-weight: 600;
    color: #ffd700;
    margin-bottom: 5px;
  }
  
  .member-position {
    font-size: 0.9rem;
    color: #ccc;
    margin-bottom: 15px;
  }
  
  .member-social {
    display: flex;
    justify-content: center;
    gap: 15px;
  }
  
  .social-link {
    width: 30px;
    height: 30px;
    background: rgba(255, 215, 0, 0.1);
    color: #ffd700;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
  }
  
  .social-link:hover {
    background: #ffd700;
    color: #b80000;
    transform: translateY(-3px);
  }

  /* Enhanced contact cards */
  .contact-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
    margin-top: 40px;
  }
  
  .contact-card {
    position: relative;
    background: #9c9797b7;
    padding: 30px;
    text-align: center;
    border: 1px solid rgba(255, 215, 0, 0.2);
    transition: all 0.3s ease;
    overflow: hidden;
  }
  
  .contact-card::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255, 215, 0, 0.1), transparent 70%);
    opacity: 0;
    transition: opacity 0.5s ease;
    z-index: 0;
  }
  
  .contact-card:hover {
    border-color: rgba(148, 81, 81, 0.73);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
    transform: translateY(-5px);
  }
  
  .contact-card:hover::before {
    opacity: 1;
  }
  
  .contact-icon {
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, #ffd700, #ffec8b);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 2rem;
    color: #b80000;
    position: relative;
    z-index: 1;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
    transition: transform 0.4s ease;
  }
  
  .contact-card:hover .contact-icon {
    transform: rotateY(360deg);
  }
  
  .contact-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #ffd700;
    margin-bottom: 15px;
    position: relative;
    z-index: 1;
  }
  
  .contact-info {
    color: #ffffffff;
    line-height: 1.8;
    text: center;
    position: relative;
    z-index: 1;
  }
  
  .map-container {
    position: relative;
    height: 100px;
    margin-top: 50px;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid rgba(255, 215, 0, 0.2);
  }

  /* Large Devices: min-width = 992px and max-width = 1199px */
  @media (min-width: 992px) and (max-width: 1199px) {
    .container {
      padding: 28px;
    }
    .features-grid {
      grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
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
    .features-grid {
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
    }
    .contact-grid {
      grid-template-columns: 1fr;
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
    .section-title {
      font-size: 1.5rem;
    }
    .features-grid, .contact-grid {
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
      font-size: 1.3rem;
    }
    .section-content {
      font-size: 1rem;
    }
    .feature-card, .contact-card {
      padding: 18px;
    }
    .feature-icon {
      font-size: 2rem;
    }
  }
</style>

<!-- Font Awesome for Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="container">
  

  <div class="hero-section">
    <h1 class="hero-title">About Mitsubishi Motors San Pablo</h1>
    <p class="hero-subtitle">Driving Innovation and Excellence in Automotive Service</p>
  </div>

    

  <div class="about-section">
    <div class="highlight-box">
      <h2 class="section-title">Why Choose Us?</h2>
      <p class="section-content">
        At Mitsubishi Motors San Pablo, we understand that purchasing a vehicle is a significant decision. 
        That's why we're committed to providing transparent pricing, flexible financing options, and 
        comprehensive warranties. Our experienced sales team will guide you through every step of the 
        process, ensuring you find the perfect Mitsubishi vehicle that fits your lifestyle and budget.
      </p>
      
      <p class="section-content">
        Our service department is staffed with factory-trained technicians who use state-of-the-art equipment
        and genuine Mitsubishi parts to maintain and repair your vehicle. We pride ourselves on quick turnaround
        times without compromising quality, ensuring that your Mitsubishi performs at its best for years to come.
      </p>
    </div>
  </div>

  <div class="contact-grid">
    <div class="contact-card">
      <div class="contact-icon"><i class="fas fa-map-marker-alt"></i></div>
      <div class="contact-title">Visit Our Showroom</div>
      <div class="contact-info">
        📍 <?php echo htmlspecialchars(getSetting('company_address', 'Km 85.5 Maharlika Highway, Brgy.San Ignacio')); ?><br>
        <?php echo htmlspecialchars(getSetting('company_city', 'San Pablo City')); ?>, <?php echo htmlspecialchars(getSetting('company_province', 'Laguna')); ?><br>
        🕒 <?php echo htmlspecialchars(getSetting('business_hours_weekday', 'Mon-Sat: 8:00 AM - 6:00 PM')); ?><br>
        🕒 <?php echo htmlspecialchars(getSetting('business_hours_weekend', 'Sunday: 9:00 AM - 5:00 PM')); ?>
      </div>
    </div>


  </div>
  
  <div class="map-container">
    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15539.571755480352!2d121.32557067061354!3d14.06127869488455!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33bd4a0c2c050a75%3A0x24b9b90e03c5e935!2sSan%20Pablo%2C%20Laguna%2C%20Philippines!5e0!3m2!1sen!2sus!4v1659479673689!5m2!1sen!2sus" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
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
    
    // Adjust features grid layout
    const featuresGrid = document.querySelector('.features-grid');
    if (featuresGrid) {
      if (width <= 767) {
        featuresGrid.style.gridTemplateColumns = '1fr';
      } else if (width <= 991) {
        featuresGrid.style.gridTemplateColumns = 'repeat(auto-fit, minmax(200px, 1fr))';
      } else if (width <= 1199) {
        featuresGrid.style.gridTemplateColumns = 'repeat(auto-fit, minmax(230px, 1fr))';
      } else {
        featuresGrid.style.gridTemplateColumns = 'repeat(auto-fit, minmax(250px, 1fr))';
      }
    }
    
    // Adjust contact grid layout
    const contactGrid = document.querySelector('.contact-grid');
    if (contactGrid) {
      if (width <= 991) {
        contactGrid.style.gridTemplateColumns = '1fr';
      } else {
        contactGrid.style.gridTemplateColumns = 'repeat(auto-fit, minmax(300px, 1fr))';
      }
    }
  }

  // Initialize on page load
  document.addEventListener('DOMContentLoaded', detectScreenSize);
  
  // Listen for window resize
  window.addEventListener('resize', detectScreenSize);
  
  // Intersection Observer for animations
  document.addEventListener('DOMContentLoaded', function() {
    // Timeline animation
    const timeline = document.querySelector('.timeline');
    const timelineItems = document.querySelectorAll('.timeline-item');
    
    if (timeline && timelineItems.length > 0) {
      const timelineObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
          if (entry.isIntersecting) {
            setTimeout(() => {
              entry.target.style.opacity = '1';
              entry.target.style.transform = 'translateY(0)';
            }, index * 200);
            timelineObserver.unobserve(entry.target);
          }
        });
      }, { threshold: 0.2 });
      
      timelineItems.forEach(item => {
        item.style.opacity = '0';
        item.style.transform = 'translateY(50px)';
        item.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        timelineObserver.observe(item);
      });
    }
    
    // Team members animation
    const teamMembers = document.querySelectorAll('.team-member');
    
    if (teamMembers.length > 0) {
      const teamObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
          if (entry.isIntersecting) {
            setTimeout(() => {
              entry.target.style.opacity = '1';
              entry.target.style.transform = 'translateY(0) scale(1)';
            }, index * 150);
            teamObserver.unobserve(entry.target);
          }
        });
      }, { threshold: 0.1 });
      
      teamMembers.forEach(member => {
        member.style.opacity = '0';
        member.style.transform = 'translateY(30px) scale(0.95)';
        member.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        teamObserver.observe(member);
      });
    }
    
    // Value items animation
    const valueItems = document.querySelectorAll('.value-item');
    
    if (valueItems.length > 0) {
      const valueObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
          if (entry.isIntersecting) {
            setTimeout(() => {
              entry.target.style.opacity = '1';
              entry.target.style.transform = 'translateY(0)';
            }, index * 100);
            valueObserver.unobserve(entry.target);
          }
        });
      }, { threshold: 0.1 });
      
      valueItems.forEach(item => {
        item.style.opacity = '0';
        item.style.transform = 'translateY(20px)';
        item.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
        valueObserver.observe(item);
      });
    }
    
    // Contact cards animation
    const contactCards = document.querySelectorAll('.contact-card');
    
    if (contactCards.length > 0) {
      const contactObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
          if (entry.isIntersecting) {
            setTimeout(() => {
              entry.target.style.opacity = '1';
              entry.target.style.transform = 'translateY(0)';
            }, index * 200);
            contactObserver.unobserve(entry.target);
          }
        });
      }, { threshold: 0.1 });
      
      contactCards.forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        contactObserver.observe(card);
      });
    }
  });
</script>

<?php include 'footer.php'; ?>
