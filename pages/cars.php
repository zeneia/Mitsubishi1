<?php 
$pageTitle = "Our Vehicles - Mitsubishi Motors";
include 'header.php'; 
include_once(dirname(__DIR__) . '/includes/database/db_conn.php');

// Fetch car data from database
try {
    global $connect;
$stmt = $connect->prepare("SELECT * FROM vehicles WHERE availability_status = 'available' AND stock_quantity > 0");
    $stmt->execute();
    $cars = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Failed to fetch vehicles: " . $e->getMessage());
    $cars = [];
}
?>

<style>
  body {
		width: 100%;
		margin: 0;
		padding: 0;
  }

  body {
    background-image: url(../includes/images/logbg.jpg); 
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
    color: #ffffff;;
    font-size: 2.5rem;
    font-weight: bold;
    margin-bottom: 24px;
    text-align: center;
  }
  .cars-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 24px;
    margin-top: 32px;
  }
  .car-card {
    position: relative;
    overflow: hidden;
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 215, 0, 0.2);
    transition: transform 0.4s ease, box-shadow 0.4s ease, opacity 0.3s ease;
  }
  .car-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 30px rgba(255, 215, 0, 0.25);
    border-color: rgba(255, 215, 0, 0.6);
  }
  .car-image {
    position: relative;
    height: 200px;
    background-size: cover;
    background-position: center;
    transition: transform 0.5s ease;
    overflow: hidden;
  }
  
  .car-card:hover .car-image {
    transform: scale(1.05);
  }

  .car-image-slide {
    position: absolute;
    width: 100%;
    height: 100%;
    background-size: cover;
    background-position: center;
    opacity: 0;
    transition: opacity 0.5s ease;
  }

  .car-image-slide.active {
    opacity: 1;
  }

  .car-slide-nav {
    position: absolute;
    bottom: 10px;
    left: 0;
    right: 0;
    display: flex;
    justify-content: center;
    gap: 8px;
    z-index: 2;
  }

  .car-slide-dot {
    width: 8px;
    height: 8px;
    background: rgba(255, 255, 255, 0.4);
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.3s ease;
  }

  .car-slide-dot.active {
    background: #ffd700;
    transform: scale(1.3);
  }

  .car-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: #ffd700;
    color: #b80000;
    font-size: 0.7rem;
    font-weight: bold;
    padding: 4px 10px;
    border-radius: 12px;
    z-index: 2;
  }

  .car-info {
    padding: 20px;
  }

  .car-name {
    font-size: 1.5rem;
    margin-bottom: 10px;
    font-weight: 700;
    background: linear-gradient(to right, #ffd700, #ffec8b);
    background-clip: text;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
  }

  .car-specs {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin: 15px 0;
    padding-bottom: 15px;
    border-bottom: 1px solid rgba(255, 215, 0, 0.2);
  }

  .car-spec {
    display: flex;
    align-items: center;
    gap: 5px;
    color: #ccc;
    font-size: 0.85rem;
  }

  .car-spec i {
    color: #ffd700;
    font-size: 1rem;
  }

  .learn-more-btn {
    width: 100%;
    position: relative;
    overflow: hidden;
    background: linear-gradient(45deg, #ffd700, #ffec8b, #ffd700);
    background-size: 200% 200%;
    color: #b80000;
    border: none;
    padding: 12px 24px;
    border-radius: 6px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.5s ease;
    z-index: 1;
  }

  .learn-more-btn:hover {
    background-position: right center;
    color: #000;
    box-shadow: 0 5px 15px rgba(255, 215, 0, 0.4);
  }

  .learn-more-btn::after {
    content: '';
    position: absolute;
    bottom: -50%;
    left: -10%;
    width: 120%;
    height: 200%;
    background: #b80000;
    border-radius: 50%;
    transition: all 0.5s ease;
    z-index: -1;
    opacity: 0;
  }

  .learn-more-btn:hover::after {
    bottom: -80%;
    opacity: 0.2;
  }

  .page-navigation {
    margin-top: 40px;
    display: flex;
    justify-content: center;
    gap: 10px;
  }

  .page-btn {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(255, 255, 255, 1);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.2);
    color: #ffffffff;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
  }

  .page-btn:hover, .page-btn.active {
    background: #ffd700;
    color: #b80000;
  }

  /* Modern UI Enhancements */
  .filter-section {
    color: #ffffffff;
    margin-bottom: 30px;
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: center;
    background: rgba(255, 255, 255, 0.05);
    padding: 20px;
    border-radius: 12px;
    backdrop-filter: blur(10px);
  }

  .filter-label {
    font-size: 1.1rem;
    font-weight: 600;
    color: #fffafaff;
    margin-right: 10px;
  }

  .filter-options {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
  }

  .filter-btn {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid #313131ff;
    color: #ffffffff;
    padding: 8px 16px;
    border-radius: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
  }

  .filter-btn:hover, .filter-btn.active {
    background: #ffd700;
    color: #b80000;
  }

  @media (min-width: 992px) and (max-width: 1199px) {
    .container {
      padding: 28px;
    }
    .cars-grid {
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 20px;
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
    .cars-grid {
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 18px;
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
    .cars-grid {
      grid-template-columns: 1fr;
      gap: 16px;
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
    .cars-grid {
      grid-template-columns: 1fr;
      gap: 14px;
    }
    .car-card {
      padding: 16px;
    }
  }
</style>

<!-- Font Awesome for Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="container">
  <h1 class="page-title">Our Vehicle Lineup</h1>
  
  <div class="filter-section">
    <div class="filter-label">Filter By:</div>
    <div class="filter-options">
      <button class="filter-btn active">All</button>
      <button class="filter-btn">SUV</button>
      <button class="filter-btn">MPV</button>
      <button class="filter-btn">Sedan</button>
      <button class="filter-btn">Pickup</button>
      <button class="filter-btn">Under ₱1M</button>
    </div>
  </div>
  
  <div class="cars-grid">
    <?php if (empty($cars)): ?>
      <div style="grid-column: 1/-1; text-align: center; color: #ffd700; font-size: 1.2rem; padding: 40px 0;">No cars available</div>
    <?php else: ?>
      <?php foreach ($cars as $car): ?>
        <div class="car-card" data-category="<?php echo strtolower(htmlspecialchars($car['category'])); ?>" data-price="<?php echo $car['base_price']; ?>">
          <?php if (!empty($car['promotional_price']) && $car['promotional_price'] < $car['base_price']): ?>
            <div class="car-badge">Promo</div>
          <?php elseif ($car['stock_quantity'] > 5): ?>
            <div class="car-badge">Popular</div>
          <?php endif; ?>
          <div class="car-image">
            <div class="car-image-slide active" style="background-image: url('<?php 
            if (!empty($car['main_image'])) {
                // Check if it's a file path or base64 data
                if (strpos($car['main_image'], 'uploads') !== false || strpos($car['main_image'], '.png') !== false || strpos($car['main_image'], '.jpg') !== false || strpos($car['main_image'], '.jpeg') !== false) {
                    // It's a file path - convert to web path
                    $webPath = str_replace('\\', '/', $car['main_image']);
                    
                    // If it starts with 'uploads/', it's already a relative path from project root
                    if (strpos($webPath, 'uploads/') === 0) {
                        // Just add ../ to go up from pages/ directory to project root
                        $webPath = '../' . $webPath;
                    } else if (strpos($webPath, 'htdocs/') !== false) {
                        // Handle full system paths - extract everything after htdocs/
                        $webPath = preg_replace('/^.*\/htdocs\/[^\/]+\//', '../', $webPath);
                    } else {
                        // If it's just a filename, assume it's in uploads/vehicle_images/main/
                        $webPath = '../uploads/vehicle_images/main/' . basename($webPath);
                    }
                    echo htmlspecialchars($webPath);
                } else if (preg_match('/^[A-Za-z0-9+\/=]+$/', $car['main_image']) && strlen($car['main_image']) > 100) {
                    // It's base64 data
                    echo 'data:image/jpeg;base64,' . $car['main_image'];
                } else {
                    // Try base64_encode for backward compatibility
                    echo 'data:image/jpeg;base64,' . base64_encode($car['main_image']);
                }
            } else {
                echo '../includes/images/default-car.svg';
            }
            ?>')"></div>
            <?php 
            // Optionally render additional images if available
            ?>
            <div class="car-slide-nav">
              <span class="car-slide-dot active" data-index="0"></span>
            </div>
          </div>
          <div class="car-info">
            <div class="car-name"><?php echo htmlspecialchars($car['model_name']); ?></div>
            <div class="car-price">
              <?php if (!empty($car['promotional_price']) && $car['promotional_price'] < $car['base_price']): ?>
                <span style="text-decoration:line-through; color:#ccc;">₱<?php echo number_format($car['base_price'],0); ?></span>
                <span style="color:#ffd700; font-weight:bold;"> ₱<?php echo number_format($car['promotional_price'],0); ?></span>
              <?php else: ?>
                Starting at ₱<?php echo number_format($car['base_price'],0); ?>
              <?php endif; ?>
            </div>
            <div class="car-specs">
              <div class="car-spec"><i class="fas fa-car"></i> <?php echo htmlspecialchars($car['variant']); ?></div>
              <div class="car-spec"><i class="fas fa-palette"></i> <?php echo htmlspecialchars($car['popular_color']); ?></div>
              <div class="car-spec"><i class="fas fa-calendar"></i> <?php echo htmlspecialchars($car['year_model']); ?></div>
              <div class="car-spec"><i class="fas fa-cogs"></i> <?php echo htmlspecialchars($car['engine_type']); ?></div>
              <div class="car-spec"><i class="fas fa-money-bill"></i> <?php echo ($car['stock_quantity'] > 0 ? 'Available' : 'Out of Stock'); ?></div>
            </div>
            <button class="learn-more-btn" onclick="window.location.href='car_details.php?id=<?php echo $car['id']; ?>'">View Details</button>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  
  <div class="page-navigation">
    <button class="page-btn active">1</button>
    <button class="page-btn">2</button>
    <button class="page-btn">3</button>
    <button class="page-btn"><i class="fas fa-chevron-right"></i></button>
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
    
    // Auto-close mobile menu on larger screens
    if (width > 767) {
      const nav = document.getElementById('navMenu');
      const toggle = document.querySelector('.menu-toggle');
      if (nav && toggle) {
        nav.classList.remove('active');
        toggle.classList.remove('active');
      }
    }
    
    // Adjust cars grid layout
    const carsGrid = document.querySelector('.cars-grid');
    if (carsGrid) {
      if (width <= 767) {
        carsGrid.style.gridTemplateColumns = '1fr';
      } else if (width <= 991) {
        carsGrid.style.gridTemplateColumns = 'repeat(auto-fit, minmax(260px, 1fr))';
      } else if (width <= 1199) {
        carsGrid.style.gridTemplateColumns = 'repeat(auto-fit, minmax(280px, 1fr))';
      } else {
        carsGrid.style.gridTemplateColumns = 'repeat(auto-fit, minmax(300px, 1fr))';
      }
    }
  }

  // Initialize on page load
  document.addEventListener('DOMContentLoaded', detectScreenSize);
  
  // Listen for window resize
  window.addEventListener('resize', detectScreenSize);

  // Initialize car image sliders
  document.addEventListener('DOMContentLoaded', function() {
    // Initialize sliders for each car card
    document.querySelectorAll('.car-card').forEach(card => {
      const slides = card.querySelectorAll('.car-image-slide');
      const dots = card.querySelectorAll('.car-slide-dot');
      let currentSlide = 0;
      let interval;

      // Function to change slide
      function goToSlide(index) {
        slides.forEach(slide => slide.classList.remove('active'));
        dots.forEach(dot => dot.classList.remove('active'));
        slides[index].classList.add('active');
        dots[index].classList.add('active');
        currentSlide = index;
      }

      // Auto rotate slides
      function startSlideshow() {
        interval = setInterval(() => {
          currentSlide = (currentSlide + 1) % slides.length;
          goToSlide(currentSlide);
        }, 4000);
      }

      // Click events for dots
      dots.forEach((dot, index) => {
        dot.addEventListener('click', () => {
          clearInterval(interval);
          goToSlide(index);
          startSlideshow();
        });
      });

      // Start the slideshow
      startSlideshow();

      // Pause slideshow on hover
      card.addEventListener('mouseenter', () => clearInterval(interval));
      card.addEventListener('mouseleave', startSlideshow);
    });

    // Filter functionality
    const filterBtns = document.querySelectorAll('.filter-btn');
    
    filterBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        filterBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        
        const filterValue = btn.textContent.trim().toLowerCase();
        const carCards = document.querySelectorAll('.car-card');
        
        carCards.forEach(card => {
          const category = card.getAttribute('data-category');
          const price = parseInt(card.getAttribute('data-price'));
          let shouldShow = false;
          
          if (filterValue === 'all') {
            shouldShow = true;
          } else if (filterValue === 'under ₱1m') {
            shouldShow = price < 1000000;
          } else {
            shouldShow = category === filterValue;
          }
          
          if (shouldShow) {
            card.style.display = 'block';
            card.style.opacity = '0';
            card.style.transform = 'scale(0.95)';
            setTimeout(() => {
              card.style.opacity = '1';
              card.style.transform = 'scale(1)';
            }, 100);
          } else {
            card.style.opacity = '0';
            card.style.transform = 'scale(0.95)';
            setTimeout(() => {
              card.style.display = 'none';
            }, 300);
          }
        });
      });
    });

    // Page navigation functionality
    const pageBtns = document.querySelectorAll('.page-btn');
    
    pageBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        pageBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        
        // Scroll to top smoothly
        window.scrollTo({
          top: 0,
          behavior: 'smooth'
        });
        
        // Add pagination logic here
      });
    });
  });
</script>

<?php include 'footer.php'; ?>
