<?php
session_start();
include_once(dirname(__DIR__) . '/includes/database/db_conn.php');

// Allow public access to view car details - no login required

// Check if vehicle ID is set
if (!isset($_GET['id']) || empty($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: car_menu.php");
    exit;
}

$vehicle_id = (int)$_GET['id'];

// Fetch vehicle data from database
try {
    $stmt_vehicle = $connect->prepare("SELECT * FROM vehicles WHERE id = ? AND availability_status = 'available'");
    $stmt_vehicle->execute([$vehicle_id]);
    $vehicle = $stmt_vehicle->fetch(PDO::FETCH_ASSOC);

    if (!$vehicle) {
        header("Location: car_menu.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    header("Location: car_menu.php");
    exit;
}

// Fetch user details for header (if logged in)
$user = null;
$displayName = 'Guest';
$profile_image_html = '';
if (isset($_SESSION['user_id'])) {
    $stmt = $connect->prepare("SELECT * FROM accounts WHERE Id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $displayName = !empty($user['FirstName']) ? $user['FirstName'] : $user['Username'];

    // Prepare profile image HTML
    if (!empty($user['ProfileImage'])) {
        $imageData = base64_encode($user['ProfileImage']);
        $imageMimeType = 'image/jpeg';
        $profile_image_html = '<img src="data:' . $imageMimeType . ';base64,' . $imageData . '" alt="User Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">';
    } else {
        // Show initial if no profile image
        $profile_image_html = strtoupper(substr($displayName, 0, 1));
    }
} else {
    // Guest user - show initial
    $profile_image_html = strtoupper(substr($displayName, 0, 1));
}

// Process color options
$color_options = !empty($vehicle['color_options']) ? explode(',', $vehicle['color_options']) : [];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($vehicle['model_name']); ?> Details - Mitsubishi Motors</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        /* CSS Custom Properties for Consistent Theming */
        :root {
            --primary-color: #e60012;
            --primary-dark: #c5000f;
            --primary-light: #ffccd1;
            --text-primary: #1a1a1a;
            --text-secondary: #6c757d;
            --text-light: #8a8a8a;
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --bg-tertiary: #f1f3f5;
            --border-color: #e9ecef;
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.12);
            --shadow-xl: 0 12px 32px rgba(0,0,0,0.15);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 24px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-secondary);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
        }

        /* Modern Header Design */
        .header {
            background: #000000;
            padding: 1.5rem 2rem;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid var(--border-color);
            width: 100%;
            left: 0;
            right: 0;
        }

        .header-container {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .logo {
            width: 48px;
            height: auto;
            transition: var(--transition);
        }

        .logo:hover {
            transform: scale(1.05);
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
            gap: 1.5rem;
        }

        .user-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: white;
            font-size: 1.1rem;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
        }

        .user-avatar:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .welcome-label {
            font-size: 0.875rem;
            color: #ffffff;
            font-weight: 500;
        }

        .user-name {
            font-size: 1rem;
            font-weight: 600;
            color: #ffffff;
        }

        .welcome-text {
            font-size: 1rem;
            font-weight: 500;
            color: var(--text-secondary);
        }

        .logout-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 600;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: var(--shadow-sm);
        }

        .logout-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Main Container */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Navigation Section */
        .nav-section {
            margin-bottom: 2rem;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--bg-primary);
            color: var(--text-primary);
            padding: 0.75rem 1.25rem;
            border-radius: var(--radius-md);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }

        .back-btn:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            transform: translateX(-4px);
            box-shadow: var(--shadow-md);
        }

        /* Modern Card Design */
        .vehicle-card {
            background: var(--bg-primary);
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
        }

        .card-header {
            background: linear-gradient(135deg, var(--bg-tertiary), var(--bg-secondary));
            padding: 2rem;
            border-bottom: 1px solid var(--border-color);
        }

        .vehicle-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            letter-spacing: -0.02em;
            line-height: 1.2;
        }

        .vehicle-subtitle {
            color: var(--text-secondary);
            font-size: 1rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
        }

        .image-section {
            padding: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-secondary);
        }

        .vehicle-image {
            max-width: 100%;
            height: 200px;
            object-fit: contain;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
        }

        .info-section {
            padding: 2rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .price-badge {
            background: var(--primary-color);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-lg);
            font-weight: 700;
            font-size: 1.25rem;
            display: inline-block;
            width: fit-content;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
        }

        .price-badge:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .description-text {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .specs-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
            margin: 1rem 0;
        }

        .spec-item {
            background: var(--bg-secondary);
            padding: 0.75rem 1rem;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            border-left: 3px solid var(--primary-color);
            transition: var(--transition);
        }

        .spec-item:hover {
            background: var(--bg-tertiary);
            transform: translateX(2px);
        }

        .spec-label {
            color: var(--primary-color);
            font-weight: 600;
            display: block;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .spec-value {
            color: var(--text-primary);
            margin-top: 0.25rem;
            font-weight: 500;
        }

        .features-section {
            grid-column: 1 / -1;
            padding: 2rem;
            border-top: 1px solid var(--border-color);
            background: var(--bg-secondary);
        }

        .section-title {
            color: var(--primary-color);
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            font-size: 1rem;
        }

        .color-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .color-tag {
            background: var(--bg-primary);
            color: var(--text-primary);
            padding: 0.5rem 1rem;
            border-radius: var(--radius-lg);
            font-size: 0.875rem;
            border: 1px solid var(--border-color);
            font-weight: 500;
            transition: var(--transition);
        }

        .color-tag:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }

        .stock-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-lg);
            font-size: 0.875rem;
            font-weight: 600;
            transition: var(--transition);
        }

        .stock-available {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
            border: 1px solid rgba(76, 175, 80, 0.2);
        }

        .stock-low {
            background: rgba(255, 152, 0, 0.1);
            color: #FF9800;
            border: 1px solid rgba(255, 152, 0, 0.2);
        }

        .stock-out {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
            border: 1px solid rgba(244, 67, 54, 0.2);
        }

        .actions-section {
            grid-column: 1 / -1;
            padding: 2rem;
            border-top: 1px solid var(--border-color);
            background: var(--bg-tertiary);
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            grid-template-rows: repeat(2, 1fr);
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .action-btn {
            background: var(--bg-primary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            padding: 1.25rem 1rem;
            border-radius: var(--radius-lg);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            text-align: center;
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            min-height: 90px;
            cursor: pointer;
            box-shadow: var(--shadow-sm);
        }

        .action-btn i {
            font-size: 1.5rem;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }

        .action-btn:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .action-btn:hover i {
            color: white;
        }

        /* Remove the primary class special styling - all buttons are now equal */
        .action-btn.primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 215, 0, 0.4);
        }

        /* Remove the loan class special styling - make it match others */
        .action-btn.loan:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 215, 0, 0.4);
        }

        /* Responsive Design */
        @media (max-width: 575px) {
            .header {
                padding: 1rem;
            }

            .header-container {
                flex-direction: column;
                gap: 1rem;
            }

            .user-section {
                width: 100%;
                justify-content: center;
            }

            .back-btn{
                padding: 0.5rem 1rem;
                font-size: 0.875rem;

            }

            .main-container {
                padding: 1rem;
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

        @media (min-width: 576px) and (max-width: 767px) {
            .card-body {
                grid-template-columns: 1fr;
            }

            .vehicle-title {
                font-size: 1.75rem;
            }

            .specs-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .action-grid {
                grid-template-columns: repeat(2, 1fr);
                grid-template-rows: repeat(3, 1fr);
                gap: 0.875rem;
            }

            .action-btn {
                padding: 1.125rem;
                font-size: 0.9rem;
                min-height: 75px;
            }

            .action-btn i {
                font-size: 1.375rem;
            }
        }

        @media (min-width: 768px) and (max-width: 991px) {
            .vehicle-title {
                font-size: 1.875rem;
            }

            .action-grid {
                grid-template-columns: repeat(3, 1fr);
                grid-template-rows: repeat(2, 1fr);
                gap: 0.9375rem;
            }

            .action-btn {
                padding: 1.1875rem;
                font-size: 0.9375rem;
                min-height: 80px;
            }

            .action-btn i {
                font-size: 1.4375rem;
            }
        }

        @media (min-width: 992px) {
            .vehicle-title {
                font-size: 2rem;
            }

            .action-grid {
                grid-template-columns: repeat(3, 1fr);
                grid-template-rows: repeat(2, 1fr);
                gap: 1rem;
            }

            .action-btn {
                padding: 1.25rem 1rem;
                font-size: 0.9rem;
                min-height: 90px;
            }

            .action-btn i {
                font-size: 1.5rem;
            }
        }

        /* Focus States for Accessibility */
        button:focus,
        a:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-secondary);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }

        /* Smooth Transitions */
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>

<body>
    <header class="header">
        <div class="header-container">
            <div class="logo-section">
                <img src="../includes/images/mitsubishi_logo.png" alt="Mitsubishi Logo" class="logo">
                <div class="brand-text">MITSUBISHI MOTORS</div>
            </div>
            <div class="user-section">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="user-avatar"><?php echo $profile_image_html; ?></div>
                    <div class="user-info">
                        <span class="welcome-label">Welcome</span>
                        <span class="user-name"><?php echo htmlspecialchars($displayName); ?></span>
                    </div>
                    <button class="logout-btn" onclick="window.location.href='logout.php'">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </button>
                <?php else: ?>
                    <div class="user-info">
                        <span class="welcome-label">Browse as Guest</span>
                    </div>
                    <button class="logout-btn" onclick="window.location.href='login.php'">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Login</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="main-container">
        <nav class="nav-section">
            <a href="car_menu.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                <span>Back to Car Menu</span>
            </a>
        </nav>

        <div class="container">

        <div class="vehicle-card">
            <!-- Card Header -->
            <div class="card-header">
                <h1 class="vehicle-title"><?php echo htmlspecialchars($vehicle['model_name']); ?></h1>
                <div class="vehicle-subtitle">
                    <?php if (!empty($vehicle['variant'])): ?>
                        <span><?php echo htmlspecialchars($vehicle['variant']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($vehicle['year_model'])): ?>
                        <span>•</span>
                        <span><?php echo htmlspecialchars($vehicle['year_model']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($vehicle['category'])): ?>
                        <span>•</span>
                        <span><?php echo htmlspecialchars(ucfirst($vehicle['category'])); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Card Body -->
            <div class="card-body">
                <!-- Image Section -->
                <div class="image-section">
                    <?php if (!empty($vehicle['main_image'])): ?>
                        <?php 
                        // Check if it's a file path or base64 data
                        if (strpos($vehicle['main_image'], 'uploads') !== false || 
                            strpos(strtolower($vehicle['main_image']), '.png') !== false || 
                            strpos(strtolower($vehicle['main_image']), '.jpg') !== false || 
                            strpos(strtolower($vehicle['main_image']), '.jpeg') !== false) {
                            
                            // It's a file path - convert to web path
                            $webPath = $vehicle['main_image'];
                            
                            // Convert backslashes to forward slashes
                            $webPath = str_replace('\\', '/', $webPath);
                            
                            // Remove any Windows drive letter (e.g., C:)
                            $webPath = preg_replace('/^[A-Za-z]:/i', '', $webPath);
                            
                            // Extract the path starting from 'uploads'
                             if (preg_match('/uploads.*$/i', $webPath, $matches)) {
                                 $webPath = $matches[0];
                             }
                             
                             // Build relative path from /pages to project root so it works under subdirectories (e.g., /Mitsubishi)
                             $webPath = '../' . ltrim($webPath, '/');
                             
                             // Do NOT force lowercase on Linux to avoid case-mismatch on real files
                             
                             echo '<img src="' . htmlspecialchars($webPath) . '" alt="' . htmlspecialchars($vehicle['model_name']) . '" class="vehicle-image">';
                        } else if (preg_match('/^[A-Za-z0-9+\/=]+$/', $vehicle['main_image']) && strlen($vehicle['main_image']) > 100) {
                            // It's base64 data
                            echo '<img src="data:image/jpeg;base64,' . $vehicle['main_image'] . '" alt="' . htmlspecialchars($vehicle['model_name']) . '" class="vehicle-image">';
                        } else {
                            // Attempt to render legacy BLOB by base64-encoding binary; fallback to default image if empty
                            $encoded = base64_encode($vehicle['main_image']);
                            if (!empty($encoded)) {
                                echo '<img src="data:image/jpeg;base64,' . $encoded . '" alt="' . htmlspecialchars($vehicle['model_name']) . '" class="vehicle-image">';
                            } else {
                                echo '<img src="../includes/images/default-car.svg" alt="' . htmlspecialchars($vehicle['model_name']) . '" class="vehicle-image">';
                            }
                        }
                        ?>
                    <?php else: ?>
                        <img src="../includes/images/default-car.svg"
                            alt="<?php echo htmlspecialchars($vehicle['model_name']); ?>"
                            class="vehicle-image">
                    <?php endif; ?>
                </div>

                <!-- Info Section -->
                <div class="info-section">
                    <!-- Price -->
                    <?php if ($vehicle['base_price']): ?>
                        <div class="price-badge">
                            <?php
                                $base  = (float)$vehicle['base_price'];
                                $promo = isset($vehicle['promotional_price']) ? (float)$vehicle['promotional_price'] : 0;
                                $hasPromo = $promo > 0 && $promo < $base;
                            ?>
                            ₱<?php echo number_format($hasPromo ? $promo : $base, 2); ?>
                            <?php if ($hasPromo): ?>
                                <span style="text-decoration: line-through; opacity: 0.7; margin-left: 8px;">
                                    ₱<?php echo number_format($base, 2); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Description -->
                    <?php if (!empty($vehicle['key_features'])): ?>
                        <p class="description-text"><?php echo htmlspecialchars($vehicle['key_features']); ?></p>
                    <?php endif; ?>

                    <!-- Specs Grid -->
                    <div class="specs-grid">
                        <?php if ($vehicle['engine_type']): ?>
                            <div class="spec-item">
                                <span class="spec-label">Engine</span>
                                <span class="spec-value"><?php echo htmlspecialchars($vehicle['engine_type']); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($vehicle['transmission']): ?>
                            <div class="spec-item">
                                <span class="spec-label">Transmission</span>
                                <span class="spec-value"><?php echo htmlspecialchars($vehicle['transmission']); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($vehicle['fuel_type']): ?>
                            <div class="spec-item">
                                <span class="spec-label">Fuel Type</span>
                                <span class="spec-value"><?php echo htmlspecialchars($vehicle['fuel_type']); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($vehicle['seating_capacity']): ?>
                            <div class="spec-item">
                                <span class="spec-label">Seating</span>
                                <span class="spec-value"><?php echo htmlspecialchars($vehicle['seating_capacity']); ?> passengers</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Stock Status -->
                    <div>
                        <?php if ($vehicle['stock_quantity'] > 10): ?>
                            <span class="stock-badge stock-available">
                                <i class="fas fa-check-circle"></i>
                                In Stock (<?php echo $vehicle['stock_quantity']; ?>)
                            </span>
                        <?php elseif ($vehicle['stock_quantity'] > 0): ?>
                            <span class="stock-badge stock-low">
                                <i class="fas fa-exclamation-triangle"></i>
                                Limited Stock (<?php echo $vehicle['stock_quantity']; ?>)
                            </span>
                        <?php else: ?>
                            <span class="stock-badge stock-out">
                                <i class="fas fa-times-circle"></i>
                                Out of Stock
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Features Section -->
                <div class="features-section">
                    <!-- Colors -->
                    <?php if (!empty($color_options)): ?>
                        <div style="margin-bottom: 15px;">
                            <div class="section-title">
                                <i class="fas fa-palette"></i>
                                Available Colors
                            </div>
                            <div class="color-tags">
                                <?php foreach ($color_options as $color): ?>
                                    <span class="color-tag"><?php echo htmlspecialchars(trim($color)); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Financing Info -->
                    <?php if ($vehicle['min_downpayment_percentage'] || $vehicle['financing_terms']): ?>
                        <div>
                            <div class="section-title">
                                <i class="fas fa-calculator"></i>
                                Financing Options
                            </div>
                            <p class="description-text">
                                <?php if ($vehicle['min_downpayment_percentage']): ?>
                                    Down payment from <?php echo $vehicle['min_downpayment_percentage']; ?>%
                                <?php endif; ?>
                                <?php if ($vehicle['financing_terms']): ?>
                                    <?php echo $vehicle['min_downpayment_percentage'] ? ' • ' : ''; ?>
                                    <?php echo htmlspecialchars($vehicle['financing_terms']); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Actions Section -->
                <div class="actions-section">
                    <div class="action-grid">
                        <a href="#" class="action-btn" onclick="getQuote(<?php echo $vehicle['id']; ?>)">
                            <i class="fas fa-calculator"></i>
                            <span>Get Quote</span>
                        </a>
                        <?php
                        $isInStock = isset($vehicle['stock_quantity']) && $vehicle['stock_quantity'] > 0;
                        if ($isInStock):
                        ?>
                            <a href="#" class="action-btn" onclick="bookTestDrive(<?php echo $vehicle['id']; ?>)">
                                <i class="fas fa-car"></i>
                                <span>Test Drive</span>
                            </a>
                        <?php else: ?>
                            <a href="#" class="action-btn" style="opacity: 0.5; cursor: not-allowed;" onclick="event.preventDefault(); alert('Sorry, this vehicle is currently out of stock and not available for test drives.');">
                                <i class="fas fa-car"></i>
                                <span>Test Drive (Out of Stock)</span>
                            </a>
                        <?php endif; ?>
                        <a href="#" class="action-btn" onclick="inquireVehicle(<?php echo $vehicle['id']; ?>)">
                            <i class="fas fa-question-circle"></i>
                            <span>Inquiry</span>
                        </a>
                        <a href="#" class="action-btn" onclick="view3D(<?php echo $vehicle['id']; ?>)">
                            <i class="fas fa-cube"></i>
                            <span>3D View</span>
                        </a>
                        <a href="#" class="action-btn" onclick="viewPMSRecord(<?php echo $vehicle['id']; ?>)">
                            <i class="fas fa-clipboard-list"></i>
                            <span>PMS Record</span>
                        </a>
                        <a href="#" class="action-btn" onclick="applyLoan(<?php echo $vehicle['id']; ?>)">
                            <i class="fas fa-file-contract"></i>
                            <span>Loan</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </main>

    <script>
        // Remove all modal-related JavaScript since we're not using modal anymore
        function applyLoan(vehicleId) {
            window.location.href = `loan_requirements.php?vehicle_id=${vehicleId}`;
        }

        // Get Quote function
        function getQuote(vehicleId) {
            window.location.href = `quote_request.php?vehicle_id=${vehicleId}`;
        }

        // Book Test Drive function
        function bookTestDrive(vehicleId) {
            window.location.href = `test_drive.php?vehicle_id=${vehicleId}`;
        }

        // Inquire Vehicle function
        function inquireVehicle(vehicleId) {
            window.location.href = `inquiry.php?vehicle_id=${vehicleId}`;
        }

        // 3D View function
        function view3D(vehicleId) {
            window.location.href = `car_3d_view.php?vehicle_id=${vehicleId}`;
        }

        // View PMS Record function
        function viewPMSRecord(vehicleId) {
            window.location.href = `pms_record.php?vehicle_id=${vehicleId}`;
        }
    </script>

</body>

</html>