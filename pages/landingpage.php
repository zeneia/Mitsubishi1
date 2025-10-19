<?php
$pageTitle = "San Pablo City - Mitsubishi Motors";
include 'header.php';

// Load DB connection and fetch top vehicles for dynamic lineup
require_once dirname(__DIR__) . '/includes/database/db_conn.php';

// Helper to normalize stored main_image into a web-safe URL
function lp_resolve_vehicle_image_url($mainImage) {
    // Default placeholder (relative, deployment-ready)
    $default = "../includes/images/default-car.svg";

    if (empty($mainImage)) {
        return $default;
    }

    // If it looks like a file path (uploaded path or typical image extension)
    if (
        is_string($mainImage) &&
        (
            strpos($mainImage, 'uploads') !== false ||
            stripos($mainImage, '.png') !== false ||
            stripos($mainImage, '.jpg') !== false ||
            stripos($mainImage, '.jpeg') !== false ||
            stripos($mainImage, '.webp') !== false
        )
    ) {
        // Normalize slashes and strip any absolute local path up to htdocs
        $webPath = str_replace('\\', '/', $mainImage);
        $webPath = preg_replace('/^.*\/htdocs\//', '/', $webPath);
        // Ensure relative path if still not root-relative
        if (!preg_match('#^/#', $webPath)) {
            // Assume path stored from project root like "uploads/..."
            $webPath = '../' . ltrim($webPath, '/');
        }
        return htmlspecialchars($webPath, ENT_QUOTES, 'UTF-8');
    }

    // If the data looks like base64 (or raw blob), convert to data URI
    if (is_string($mainImage) && preg_match('/^[A-Za-z0-9+\/=]+$/', $mainImage) && strlen($mainImage) > 100) {
        return 'data:image/jpeg;base64,' . $mainImage;
    }

    // Fallback: base64-encode binary blobs
    return 'data:image/jpeg;base64,' . base64_encode($mainImage);
}

// Fetch 3 vehicles to feature on the landing page lineup
$lpVehicles = [];
try {
    // Prefer available and in stock; order by stock desc then latest year, then model name
    $stmt = $connect->prepare("
        SELECT id, model_name, variant, year_model, category,
               base_price, promotional_price, stock_quantity, main_image, key_features
        FROM vehicles
        WHERE LOWER(availability_status) = 'available' AND stock_quantity > 0
        ORDER BY stock_quantity DESC, year_model DESC, model_name ASC
        LIMIT 3
    ");
    $stmt->execute();
    $lpVehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Landing lineup fetch failed: ' . $e->getMessage());
    $lpVehicles = [];
}
?>

<style>
  /* Zoom out the entire page to 7% */
  body {
    background: #000000;
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
    background: #000000;
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
    background: #8584848c;
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
    position: relative;
    background-attachment: fixed;
    background-repeat: no-repeat;
    background-size: cover;
    background-position: center;
    min-height: 100px;
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

      /* Tablet */
    @media (max-width: 1024px) {
        .container {
            max-width: 95%;
        }
    }

    /* Phones */
    @media (max-width: 768px) {
        .header {
            flex-direction: column;
            gap: 15px;
            padding: 15px 20px;
        }

        .user-section {
            flex-direction: column;
            gap: 12px;
            text-align: center;
            width: 100%;
        }

        .container {
            padding: 20px 15px;
        }

        .form-container {
            padding: 20px;
        }

        .form-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Large Desktops */
    @media (min-width: 1200px) {
        .container {
            max-width: 1100px;
        }

        .inquiry-card {
            max-width: 100%;
        }

        .form-grid {
            grid-template-columns: repeat(2, 1fr);
        }
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
    <?php if (empty($lpVehicles)): ?>
      <!-- Fallback: show link to full cars catalog if no vehicles available -->
      <div class="vehicle-card" style="grid-column: 1/-1; text-align:center; padding:30px;">
        <div class="vehicle-info">
          <h3 class="vehicle-name">No vehicles to display</h3>
          <p class="vehicle-features">Please visit our complete lineup to explore available models</p>
          <a href="cars.php" class="view-details">Explore Vehicles →</a>
        </div>
      </div>
    <?php else: ?>
      <?php foreach ($lpVehicles as $v):
        $img = lp_resolve_vehicle_image_url($v['main_image'] ?? null);
        $model = htmlspecialchars($v['model_name'] ?? 'Vehicle', ENT_QUOTES, 'UTF-8');
        $variant = htmlspecialchars($v['variant'] ?? '', ENT_QUOTES, 'UTF-8');
        $features = !empty($v['key_features']) ? htmlspecialchars(substr($v['key_features'], 0, 120)) . (strlen($v['key_features']) > 120 ? '...' : '') : 'Premium vehicle with advanced features and exceptional performance.';
        $base = isset($v['base_price']) ? (float)$v['base_price'] : 0;
        $promo = isset($v['promotional_price']) ? (float)$v['promotional_price'] : 0;
        $effective = ($promo > 0 && $promo < $base) ? $promo : $base;
      ?>
      <div class="vehicle-card">
        <div class="vehicle-image" style="background-image: url('<?php echo $img; ?>'); background-size: cover; background-position: center;"></div>
        <div class="vehicle-info">
          <h3 class="vehicle-name"><?php echo $model; ?><?php echo $variant ? ' ' . $variant : ''; ?></h3>
          <p class="vehicle-price">
            <?php if ($promo > 0 && $promo < $base): ?>
              <span style="text-decoration:line-through; color:#ccc;">₱<?php echo number_format($base, 0); ?></span>
              <span style="color:#ffd700; font-weight:bold;"> ₱<?php echo number_format($promo, 0); ?></span>
            <?php else: ?>
              Starting at ₱<?php echo number_format($effective, 0); ?>
            <?php endif; ?>
          </p>
          <p class="vehicle-features"><?php echo $features; ?></p>
          <a href="car_details.php?id=<?php echo (int)$v['id']; ?>" class="view-details">View Details →</a>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
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

<?php include 'footer.php'; ?>