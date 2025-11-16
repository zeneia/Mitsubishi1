<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once(dirname(__DIR__) . '/includes/database/db_conn.php');



// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Customer') {
    header("Location: login.php");
    exit;
}

// Check if customer information is filled out, if not, redirect to verification
$stmt_check_info = $connect->prepare("SELECT cusID FROM customer_information WHERE account_id = ?");
$stmt_check_info->execute([$_SESSION['user_id']]);
if ($stmt_check_info->rowCount() == 0) {
    header("Location: verification.php");
    exit;
}

// Fetch user details
$stmt = $connect->prepare("SELECT * FROM accounts WHERE Id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$displayName = !empty($user['FirstName']) ? $user['FirstName'] : $user['Username'];

// Prepare profile image HTML
$profile_image_html = '';
if (!empty($user['ProfileImage'])) {
    $imageData = base64_encode($user['ProfileImage']);
    $imageMimeType = 'image/jpeg';
    $profile_image_html = '<img src="data:' . $imageMimeType . ';base64,' . $imageData . '" alt="User Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">';
} else {
    // Show initial if no profile image
    $profile_image_html = strtoupper(substr($displayName, 0, 1));
}

// Fetch all vehicles from database
try {
    $stmt_vehicles = $connect->prepare("SELECT * FROM vehicles WHERE availability_status = 'available' ORDER BY model_name");
    $stmt_vehicles->execute();
    $vehicles = $stmt_vehicles->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch unique categories from database
    $stmt_categories = $connect->prepare("SELECT DISTINCT category FROM vehicles WHERE availability_status = 'available' AND category IS NOT NULL AND category != '' ORDER BY category");
    $stmt_categories->execute();
    $categories = $stmt_categories->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $vehicles = [];
    $categories = [];
    error_log("Database error: " . $e->getMessage());
}
?>

<header class="header-container">
        <div class="logo-section">
            <img src="../includes/images/mitsubishi_logo.png" alt="Mitsubishi Logo" class="logo">
            <div class="brand-text">MITSUBISHI MOTORS</div>
        </div>
        <div class="user-section">
            <div class="user-avatar">
                <?php echo $profile_image_html; ?>
            </div>
            <span class="welcome-text">Welcome, <?php echo htmlspecialchars($displayName); ?>!</span>
            <button class="logout-btn" onclick="window.location.href='logout.php'">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </div>
</header>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: var(--bg-secondary);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
        }

        .header {
            background: #000000;
            padding: 1.5rem 2rem;
            box-shadow: #000 2px 4px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid #000000;
            width: 100%;
            left: 0;
            right: 0;
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            background-color: #000000;
            border-bottom: 2px solid #e0e0e0;
        }

        .logo-section {
            display: flex;
            align-items: center;
        }

        .logo {
            height: 50px;
            margin-right: 1rem;
        }

        .brand-text {
            font-size: 1.5rem;
            font-weight: 700;
            color: #FFFFFF;
            letter-spacing: -0.02em;
        }



        .user-section {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(45deg, #ffd700, #ffed4e);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #b80000;
            font-size: 1.2rem;
        }

        .welcome-text {
            font-size: 1rem;
            font-weight: 500;
            color: #FFFFFF;
        }

        .logout-btn {
            background: #E60012;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .logout-btn:hover {
            background-color: #c9302c;
        }



        /* --- RESPONSIVENESS --- */

/* Tablet (max-width: 1024px) */
@media (max-width: 1024px) {
  .header-container {
    padding: 1rem 1.5rem;
  }

  .brand-text {
    font-size: 1.3rem;
  }

  .welcome-text {
    font-size: 0.95rem;
  }

  .logo {
    height: 45px;
  }
}

/* Mobile (max-width: 768px) */
@media (max-width: 768px) {
  .header-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-direction: row; /* keep items in one line */
    padding: 0.75rem 1rem;
    flex-wrap: wrap; /* allow wrapping if needed */
  }

  .logo-section {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .logo {
    height: 40px;
    width: auto;
  }

  .brand-text {
    font-size: 1.1rem;
  }

  .user-section {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    justify-content: flex-end;
  }

  .welcome-text {
    font-size: 0.9rem;
    text-align: right;
  }

  .logout-btn {
    font-size: 0.9rem;
    padding: 0.4rem 0.9rem;
  }
}


     @media (max-width: 575px) {
            .header {
                padding: 1rem;
            }

            .header-container {
                flex-direction: column;
                gap: 1rem;
            }
            .img .logo{
                width: 100%;
                height: 50%;
            }
            .brand-text{
                font-size: 1.2rem;
            }

            .user-section {
                width: 100%;
                justify-content: center;
            }

            .main-container {
                padding: 1rem;
            }
            
            
            .back-btn{
                padding: 0.5rem 1rem;
                font-size: 0.875rem;

            }

            .card-header {
                padding: 1.5rem;
            }

            .header-content {
                flex-direction: column;
                align-items: stretch;
            }

            .vehicle-title {
                font-size: 1.5rem;
            }

            .card-body {
                grid-template-columns: 1fr;
            }

            .image-section,
            .info-section,
            .features-section,
            .actions-section {
                padding: 1.5rem;
            }

            .action-grid {
                grid-template-columns: 1fr;
                grid-template-rows: repeat(6, 1fr);
                gap: 0.75rem;
            }

            .action-btn {
                padding: 1rem;
                font-size: 0.875rem;
                min-height: 70px;
            }

            .action-btn i {
                font-size: 1.25rem;
            }

            .specs-grid {
                grid-template-columns: 1fr;
            }
        }

/* Small Phones (max-width: 480px) */
@media (max-width: 480px) {
  .brand-text {
    font-size: 1.1rem;
  }

  .welcome-text {
    font-size: 0.85rem;
  }

  .logout-btn {
    padding: 0.35rem 0.75rem;
    font-size: 0.85rem;
  }

  .header-container {
    padding: 0.75rem;
  }

  .logo {
    height: 35px;
  }
}
        </style>
</head>

<script>
function responsiveHeader() {
  const width = window.innerWidth;
  const header = document.getElementById('headerContainer');
  const logo = document.getElementById('logo');
  const brand = document.getElementById('brandText');
  const welcome = document.getElementById('welcomeText');
  const logout = document.getElementById('logoutBtn');
  const logoSection = document.getElementById('logoSection');
  const userSection = document.getElementById('userSection');

  // Check if elements exist (some pages may have different header structure)
  if (!header || !logo || !brand || !welcome || !logout || !logoSection || !userSection) {
    return; // Exit if any required element is missing
  }

  // --- Desktop / Laptop ---
  if (width > 1024) {
    header.style.flexDirection = 'row';
    header.style.alignItems = 'center';
    logo.style.height = '50px';
    brand.style.fontSize = '1.5rem';
    welcome.style.fontSize = '1rem';
    logout.style.fontSize = '1rem';
  }

  // --- Tablet ---
  else if (width <= 1024 && width > 768) {
    header.style.padding = '1rem 1.5rem';
    logo.style.height = '45px';
    brand.style.fontSize = '1.3rem';
    welcome.style.fontSize = '0.95rem';
  }

  // --- Mobile ---
  else if (width <= 768 && width > 480) {
    header.style.flexDirection = 'column';
    header.style.alignItems = 'center';
    header.style.textAlign = 'center';
    logoSection.style.justifyContent = 'center';
    userSection.style.justifyContent = 'center';
    logo.style.height = '40px';
    brand.style.fontSize = '1.25rem';
    welcome.style.fontSize = '0.9rem';
    logout.style.fontSize = '0.9rem';
    logout.style.padding = '0.4rem 0.9rem';
  }

  // --- Small Phones ---
  else {
    header.style.flexDirection = 'column';
    header.style.alignItems = 'center';
    header.style.textAlign = 'center';
    header.style.padding = '0.75rem';
    logo.style.height = '35px';
    brand.style.fontSize = '1.1rem';
    welcome.style.fontSize = '0.85rem';
    logout.style.padding = '0.35rem 0.75rem';
    logout.style.fontSize = '0.85rem';
  }
}

// Run on load and resize
window.addEventListener('load', responsiveHeader);
window.addEventListener('resize', responsiveHeader);
</script>
