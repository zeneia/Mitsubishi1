<style>
  /* Footer Styles */
  footer {
    width: 100%;
    background: #1a1a1a;
    color: #ccc;
    padding: 50px 30px 20px;
    border-top: 1px solid #333;
  }
  

  .footer-content {
    max-width: 1200px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
    gap: 30px;
    align-items: start;
  }

  .footer-section h3 {
    color: #ffd700;
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 15px;
    text-transform: uppercase;
    letter-spacing: 1px;
  }

  .footer-section p {
    color: #ccc;
    line-height: 1.5;
    font-size: 0.9rem;
  }

  .contact-info {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    font-size: 0.85rem;
  }

  .social-links {
    display: flex;
    gap: 10px;
    margin-top: 15px;
  }

  .social-links a {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 35px;
    height: 35px;
    background: #333;
    border-radius: 50%;
    color: #ccc;
    text-decoration: none;
    transition: all 0.3s ease;
    font-size: 0.9rem;
  }

  .social-links a:hover {
    background: #ffd700;
    color: #111;
    transform: translateY(-2px);
  }

  .footer-links {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .footer-links a {
    color: #ccc;
    text-decoration: none;
    font-size: 0.85rem;
    transition: color 0.3s ease;
  }

  .footer-links a:hover {
    color: #ffd700;
  }

  .footer-bottom {
    text-align: center;
    padding-top: 15px;
    margin-top: 20px;
    border-top: 1px solid #333;
    color: #999;
    font-size: 0.8rem;
  }

  /* Mobile Responsive */
  @media (max-width: 768px) {
    footer {
      padding: 25px 20px 15px;
    }
    
    .footer-content {
      grid-template-columns: 1fr;
      gap: 20px;
      text-align: center;
    }
    
    .social-links {
      justify-content: center;
    }
  }

  @media (max-width: 575px) {
    footer {
      padding: 20px 10px 15px;
    }
    
    .footer-content {
      gap: 15px;
    }
  }
</style>

<?php
// Load company settings if not already loaded
if (!function_exists('getSetting')) {
    require_once __DIR__ . '/../includes/database/db_conn.php';

    $companySettings = [];
    try {
        $stmt = $connect->prepare("SELECT setting_key, setting_value FROM company_settings");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as $row) {
            $companySettings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (PDOException $e) {
        error_log("Error loading company settings in footer: " . $e->getMessage());
    }

    function getSetting($key, $default = '') {
        global $companySettings;
        return $companySettings[$key] ?? $default;
    }
}
?>

<!-- Footer -->
<footer>
  <div class="footer-content">
    <div class="footer-section">
      <h3><?php echo htmlspecialchars(getSetting('company_name', 'Mitsubishi Motors San Pablo')); ?></h3>
      <p>Your trusted partner for innovative and reliable vehicles in <?php echo htmlspecialchars(getSetting('company_city', 'San Pablo City')); ?>, <?php echo htmlspecialchars(getSetting('company_province', 'Laguna')); ?>.</p>
      <div class="social-links">
        <?php
        $facebook = getSetting('company_facebook', '#');
        if ($facebook && $facebook !== '#') {
            echo '<a href="' . htmlspecialchars($facebook) . '" title="Facebook" target="_blank" rel="noopener">📘</a>';
        }
        ?>
        <a href="#" title="Instagram">📷</a>
        <a href="#" title="Twitter">🐦</a>
        <a href="#" title="YouTube">📺</a>
      </div>
    </div>

    <div class="footer-section">
      <h3>Quick Links</h3>
      <div class="footer-links">
        <a href="../main/cars.php">Our Vehicles</a>
        <a href="../main/sales.php">Sales & Financing</a>
        <a href="../main/service.php">Service & Parts</a>
        <a href="../main/about.php">About Us</a>
      </div>
    </div>

    <div class="footer-section">
      <h3>Contact</h3>
      <div class="contact-info">
        <span>📍</span>
        <span><?php echo htmlspecialchars(getSetting('company_address', 'Km 85.5 Maharlika Highway, Brgy.San Ignacio')); ?>, <?php echo htmlspecialchars(getSetting('company_city', 'San Pablo City')); ?> <?php echo htmlspecialchars(getSetting('company_province', 'Laguna')); ?></span>
      </div>
      <div class="contact-info">
        <span>📞</span>
        <span><?php echo htmlspecialchars(getSetting('company_phone', '(049) 503-9693')); ?></span>
      </div>
      <div class="contact-info">
        <span>✉️</span>
        <span><?php echo htmlspecialchars(getSetting('company_email', 'smf.hr@yahoo.com')); ?></span>
      </div>
    </div>
  </div>

  <div class="footer-bottom">
    <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(getSetting('company_name', 'Mitsubishi Motors San Pablo City')); ?>. All rights reserved.</p>
  </div>
</footer>
</body>
</html>
