<?php
session_start();
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Car Menu - Mitsubishi Motors</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css" />
    <style>
        /* Basic styles from customer.php */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }

        body {
            background: #ffffff;
            min-height: 100vh;
            color: white;
            overflow-x: hidden;
        }

        .header {
            background: #000000;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 215, 0, 0.2);
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logo {
            width: 60px;
            height: auto;
            filter: drop-shadow(0 0 10px rgba(255, 215, 0, 0.3));
        }

        .brand-text {
            font-size: 1.4rem;
            font-weight: 700;
            background: linear-gradient(45deg, #ffd700, #ffed4e);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
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
        }

        .logout-btn {
            background: linear-gradient(45deg, #d60000, #b30000);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(214, 0, 0, 0.3);
        }

        .logout-btn:active {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(214, 0, 0, 0.5);
        }

        .container {
            background: #ffffff;
            max-width: 2000px;
            margin: 30px auto;
            padding: 20px 30px;
            position: relative;
            z-index: 0;

            border: 3px solid #777373cc;
            border-radius: 50px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1) 
        }

        .page-title {
            font-weight: 400;
            background: #E60012;
            margin-bottom: 20px;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 800;
            text-shadow: 0 0 2px rgba(226, 22, 22, 0.66);
            text-align: center;
        }

        /* Car Category Menu */
        .category-tabs {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 50px;
            flex-wrap: wrap;
        }

        .tab-btn {
            background: transparent;
            border: 2px solid #837a7ae3;
            color: #E60012;
            padding: 12px 25px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .tab-btn.active {
            background: #E60012;
            color: #ffffff;
            box-shadow: 0 0 5px rgba(2, 1, 1, 1);
        }

        .tab-btn:active:not(.active) {
            background: rgba(255, 215, 0, 0.1);
            transform: scale(0.98);
        }

        .tab-btn.loading {
            opacity: 0.7;
            pointer-events: none;
            position: relative;
        }

        .tab-btn.loading::after {
            content: '';
            position: absolute;
            top: 50%;
            right: 8px;
            transform: translateY(-50%);
            width: 12px;
            height: 12px;
            border: 2px solid transparent;
            border-top: 2px solid #1a1a1a;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: translateY(-50%) rotate(0deg); }
            100% { transform: translateY(-50%) rotate(360deg); }
        }

        /* Smooth transitions for swiper */
        .swiper {
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .swiper-slide {
            transition: transform 0.3s ease, box-shadow 0.3s ease, opacity 0.3s ease;
        }



        /* Car Model Viewing Menu */
        .car-model-swiper {
            width: 100%;
            padding-top: 20px;
            padding-bottom: 50px;
        }

        .swiper-slide {
            background: rgba(255, 255, 255, 0.08);
            padding: 30px;
            backdrop-filter: blur(20px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 215, 0, 0.1);
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            min-height: 550px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            /* Added user-select: none to prevent text highlighting during swipe */
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }

        .car-image {
            max-width: 100%;
            height: 200px;
            object-fit: contain;
            margin-bottom: 20px;
            filter: drop-shadow(0 10px 15px rgba(0, 0, 0, 0.3));
            user-select: none;
            pointer-events: none;
            -webkit-user-drag: none;
        }

        .car-name {
            color: #000000;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .car-details {
            line-height: 1.7;
            color: #333333;
            font-weight: 400;
            margin-bottom: 15px;
        }

        .car-price {
            font-size: 1.3rem;
            font-weight: 700;
            color: #E60012;
            margin-bottom: 20px;
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 15px;
            width: 100%;
        }

        .action-btn {
            background: #E60012;
            color: #ffffff;
            border: none;
            padding: 15px 20px;
            border-radius: 15px;
            cursor: pointer;
            font-weight: 700;
            width: 100%;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.2);
            text-decoration: none;
            display: inline-block;
        }

        .action-btn:active {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 215, 0, 0.4);
        }

        /* Enhanced Swiper custom styles */
        :root {
            --swiper-navigation-color: #E60012;
            --swiper-pagination-color: #E60012;
            --swiper-theme-color: #E60012;
        }

        .swiper-button-next, .swiper-button-prev {
            transform: scale(0.8);
            background: rgba(0, 0, 0, 0.5);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            top: 50% !important;
            margin-top: 0 !important;
            transform: translateY(-50%) scale(0.8);
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        /* Added proper centering for arrow icons within circular buttons */
        .swiper-button-next::after, .swiper-button-prev::after {
            font-size: 18px;
            font-weight: 900;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            margin: 0;
        }

        /* Fine-tune arrow positioning for perfect centering */
        .swiper-button-next::after {
            transform: translate(-45%, -50%);
        }

        .swiper-button-prev::after {
            transform: translate(-55%, -50%);
        }

        .swiper-button-next:active, .swiper-button-prev:active {
            background: rgba(255, 215, 0, 0.2);
            transform: translateY(-50%) scale(0.9);
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.3);
        }

        .swiper-pagination {
            bottom: 10px !important;
        }

        .swiper-pagination-bullet {
            width: 12px;
            height: 12px;
            background: rgba(255, 215, 0, 0.5);
            opacity: 0.7;
            transition: all 0.3s ease;
        }

        .swiper-pagination-bullet-active {
            background: #ffd700;
            opacity: 1;
            transform: scale(1.2);
        }

        /* Enhanced touch feedback */
        .swiper-slide {
            cursor: grab;
        }

        .swiper-slide:active {
            cursor: grabbing;
        }

        /* Smooth scrolling indicator */
        .scroll-indicator {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: rgba(255, 215, 0, 0.6);
            font-size: 1.2rem;
            pointer-events: none;
            opacity: 0.6;
            transition: opacity 0.3s ease;
        }

        .back-btn {
            display: inline-block;
            margin-bottom: 30px;
            background: #E60012;
            color: #ffffff;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .back-btn:active {
            background: #ffd700;
            color: #1a1a1a;
        }

        /* Enhanced Responsive Styles */
        @media (max-width: 575px) {
            .header {
                flex-direction: column;
                gap: 12px;
                padding: 15px 20px;
            }
            .user-section {
                flex-direction: column;
                gap: 10px;
                text-align: center;
                width: 100%;
            }
            .page-title {
                font-size: 2rem;
            }
            .swiper-slide {
                min-height: auto;
                padding: 20px;
            }
            .car-name {
                font-size: 1.3rem;
            }
            .car-details {
                font-size: 0.85rem;
            }
            .action-buttons {
                gap: 8px;
            }
            .action-btn {
                padding: 10px 15px;
                font-size: 0.75rem;
            }
            .category-tabs {
                gap: 8px;
                overflow-x: auto;
                padding: 10px 0;
                scrollbar-width: thin;
                scrollbar-color: #ffd700 transparent;
            }
            .category-tabs::-webkit-scrollbar {
                height: 4px;
            }
            .category-tabs::-webkit-scrollbar-track {
                background: rgba(255, 215, 0, 0.1);
                border-radius: 2px;
            }
            .category-tabs::-webkit-scrollbar-thumb {
                background: #ffd700;
                border-radius: 2px;
            }
            .swiper-button-next, .swiper-button-prev {
                width: 40px;
                height: 40px;
                top: 50% !important;
                margin-top: 0 !important;
                transform: translateY(-50%) scale(0.7);
            }
            .swiper-button-next:active, .swiper-button-prev:active {
                transform: translateY(-50%) scale(0.8);
            }
            .car-model-swiper {
                padding-bottom: 60px;
            }
            .scroll-indicator {
                font-size: 1rem;
                bottom: 20px;
                top: auto;
            }
        }

        @media (min-width: 576px) and (max-width: 767px) {
            .header {
                padding: 18px 25px;
            }
            .page-title {
                font-size: 2.5rem;
            }
            .swiper-slide {
                padding: 25px;
            }
            .car-name {
                font-size: 1.4rem;
            }
            .tab-btn {
                padding: 10px 20px;
                font-size: 0.9rem;
            }
            .category-tabs {
                overflow-x: auto;
                scrollbar-width: thin;
                scrollbar-color: #ffd700 transparent;
            }
            .category-tabs::-webkit-scrollbar {
                height: 4px;
            }
            .category-tabs::-webkit-scrollbar-track {
                background: rgba(255, 215, 0, 0.1);
                border-radius: 2px;
            }
            .category-tabs::-webkit-scrollbar-thumb {
                background: #ffd700;
                border-radius: 2px;
            }
            .swiper-button-next, .swiper-button-prev {
                width: 45px;
                height: 45px;
                top: 50% !important;
                margin-top: 0 !important;
                transform: translateY(-50%) scale(0.75);
            }
            .car-model-swiper {
                padding-bottom: 50px;
            }
        }

        @media (min-width: 768px) and (max-width: 991px) {
            .page-title {
                font-size: 3rem;
            }
            .swiper-slide {
                background: #ffffff;
                padding: 30px;
                border-radius: 15px;
                backdrop-filter: none;
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
                border: 1px solid rgba(0, 0, 0, 0.08);
                text-align: center;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: space-between;
                min-height: 550px;
                transition: transform 0.3s ease, box-shadow 0.3s ease;
                user-select: none;
                
            }
            .car-name {
                font-size: 1.6rem;
            }
            .category-tabs {
                overflow-x: auto;
                scrollbar-width: thin;
                scrollbar-color: #888781ff transparent;
            }
            .category-tabs::-webkit-scrollbar {
                height: 4px;
            }
            .category-tabs::-webkit-scrollbar-track {
                background: rgba(255, 215, 0, 0.1);
                border-radius: 2px;
            }
            .category-tabs::-webkit-scrollbar-thumb {
                background: #ffd700;
                border-radius: 2px;
            }
        }

        @media (min-width: 992px) {
            .page-title {
                font-size: 3.5rem;
            }
            .swiper-slide {
                padding: 30px;
            }
            .car-name {
                font-size: 2rem;
            }
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
            max-width: 1500px;
        }

        .inquiry-card {
            max-width: 100%;
        }

        .form-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

        
        
    
    /* Fix Swiper width clamp on mobile to allow multiple cards */
    @media (max-width: 767px) {
        .car-model-swiper,
        .car-model-swiper .swiper-wrapper,
        .car-model-swiper .swiper-slide {
            max-width: none !important;
        }
        .car-model-swiper .swiper-wrapper {
            width: auto !important;
            overflow: visible !important;
        }
    }
    
    </style>
  
</head>
<body>
    <header class="header">
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

    <div class="container">
        <a href="customer.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        <h1 class="page-title">Car Category Menu</h1>
        <div class="category-tabs">
            <button class="tab-btn active" data-category="all">All Models</button>
            <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $category): ?>
                    <button class="tab-btn" data-category="<?php echo strtolower(htmlspecialchars($category)); ?>">
                        <?php echo htmlspecialchars(ucwords($category)); ?>
                    </button>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <h1 class="page-title" style="font-size: 2.5rem; margin-bottom: 30px;">Car Model Viewing</h1>

        <!-- Swiper -->
        <div class="swiper car-model-swiper">
            <div class="scroll-indicator">
                <i class="fas fa-hand-paper"></i> Swipe to explore
            </div>
            <div class="swiper-wrapper">
                <?php if (!empty($vehicles)): ?>
                    <?php foreach ($vehicles as $vehicle): ?>
                        <div class="swiper-slide" data-category="<?php echo strtolower(htmlspecialchars($vehicle['category'])); ?>">
                            <?php if (!empty($vehicle['main_image'])): ?>
                                <?php
                                // DEBUG: Log the raw image value
                                error_log("DEBUG Vehicle ID {$vehicle['id']}: Raw main_image length = " . strlen($vehicle['main_image']));
                                error_log("DEBUG Vehicle ID {$vehicle['id']}: First 100 chars = " . substr($vehicle['main_image'], 0, 100));
                                
                                // Check if it's a file path or base64 data
                                if (strpos($vehicle['main_image'], 'uploads') !== false ||
                                    strpos(strtolower($vehicle['main_image']), '.png') !== false ||
                                    strpos(strtolower($vehicle['main_image']), '.jpg') !== false ||
                                    strpos(strtolower($vehicle['main_image']), '.jpeg') !== false) {
                                    
                                    // It's a file path - convert to web path
                                    $webPath = $vehicle['main_image'];
                                    error_log("DEBUG Vehicle ID {$vehicle['id']}: Detected as FILE PATH");
                                    
                                    // Convert backslashes to forward slashes
                                    $webPath = str_replace('\\', '/', $webPath);
                                    error_log("DEBUG Vehicle ID {$vehicle['id']}: After backslash conversion = " . $webPath);
                                    
                                    // Remove any Windows drive letter (e.g., C:)
                                    $webPath = preg_replace('/^[A-Za-z]:/i', '', $webPath);
                                    error_log("DEBUG Vehicle ID {$vehicle['id']}: After drive letter removal = " . $webPath);
                                    
                                    // Extract the path starting from 'uploads'
                                    if (preg_match('/uploads.*$/i', $webPath, $matches)) {
                                        $webPath = $matches[0];
                                        error_log("DEBUG Vehicle ID {$vehicle['id']}: After uploads extraction = " . $webPath);
                                    }
                                    
                                    // Build relative path from /pages to project root so it works under subdirectories (e.g., /Mitsubishi)
                                    $webPath = '../' . ltrim($webPath, '/');
                                    error_log("DEBUG Vehicle ID {$vehicle['id']}: Final webPath = " . $webPath);
                                    
                                    // Check if file exists
                                    $checkPath = __DIR__ . '/' . $webPath;
                                    error_log("DEBUG Vehicle ID {$vehicle['id']}: File exists check = " . ($checkPath) . " => " . (file_exists($checkPath) ? "YES" : "NO"));
                                    
                                    // Do NOT force lowercase on Linux to avoid case-mismatch on real files
                                    
                                    echo '<img src="' . htmlspecialchars($webPath) . '" alt="' . htmlspecialchars($vehicle['model_name']) . '" class="car-image" draggable="false">';
                                } else if (preg_match('/^[A-Za-z0-9+\/=]+$/', $vehicle['main_image']) && strlen($vehicle['main_image']) > 100) {
                                    // It's base64 data
                                    error_log("DEBUG Vehicle ID {$vehicle['id']}: Detected as BASE64 data");
                                    echo '<img src="data:image/jpeg;base64,' . $vehicle['main_image'] . '" alt="' . htmlspecialchars($vehicle['model_name']) . '" class="car-image" draggable="false">';
                                } else {
                                    // Attempt to render as binary by base64-encoding (handles legacy BLOB records)
                                    error_log("DEBUG Vehicle ID {$vehicle['id']}: Detected as BINARY/OTHER - attempting base64 encode");
                                    $encoded = base64_encode($vehicle['main_image']);
                                    if (!empty($encoded)) {
                                        error_log("DEBUG Vehicle ID {$vehicle['id']}: Base64 encoding successful, length = " . strlen($encoded));
                                        echo '<img src="data:image/jpeg;base64,' . $encoded . '" alt="' . htmlspecialchars($vehicle['model_name']) . '" class="car-image" draggable="false">';
                                    } else {
                                        // Fallback to default image
                                        error_log("DEBUG Vehicle ID {$vehicle['id']}: Base64 encoding failed - using default image");
                                        echo '<img src="../includes/images/default-car.svg" alt="' . htmlspecialchars($vehicle['model_name']) . '" class="car-image" draggable="false">';
                                    }
                                }
                                ?>
                            <?php else: ?>
                                <img src="../includes/images/default-car.svg" alt="<?php echo htmlspecialchars($vehicle['model_name']); ?>" class="car-image" draggable="false">
                            <?php endif; ?>
                            <div>
                                <h2 class="car-name"><?php echo htmlspecialchars($vehicle['model_name']); ?></h2>
                                <?php if (!empty($vehicle['variant'])): ?>
                                    <p style="color: #E60012; font-size: 1.1rem; margin-bottom: 10px;"><?php echo htmlspecialchars($vehicle['variant']); ?></p>
                                <?php endif; ?>
                                <p class="car-details">
                                    <?php echo !empty($vehicle['key_features']) ? htmlspecialchars(substr($vehicle['key_features'], 0, 150)) . '...' : 'Premium vehicle with advanced features and exceptional performance.'; ?>
                                </p>
                                <?php if ($vehicle['base_price']): ?>
                                    <p style="color: #E60012; font-size: 1.2rem; font-weight: bold; margin-top: 10px;">
                                        <?php if ($vehicle['promotional_price'] && $vehicle['promotional_price'] < $vehicle['base_price']): ?>
                                            ₱<?php echo number_format($vehicle['promotional_price'], 2); ?>
                                            <span style="color: #ff6b6b; text-decoration: line-through; font-size: 0.9rem; margin-left: 10px;">₱<?php echo number_format($vehicle['base_price'], 2); ?></span>
                                        <?php else: ?>
                                            ₱<?php echo number_format($vehicle['base_price'], 2); ?>
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="action-buttons">
                                <a href="car_details.php?id=<?php echo $vehicle['id']; ?>" class="action-btn"><i class="fas fa-info-circle"></i> View More</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="swiper-slide">
                        <div style="text-align: center; padding: 50px;">
                            <i class="fas fa-car" style="font-size: 4rem; color: #ffd700; margin-bottom: 20px;"></i>
                            <h2 style="color: #ffd700; margin-bottom: 15px;">No Vehicles Available</h2>
                            <p>Please check back later for our latest vehicle offerings.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <!-- Add Pagination -->
            <div class="swiper-pagination"></div>
            <!-- Add Navigation -->
            <div class="swiper-button-next"></div>
            <div class="swiper-button-prev"></div>
        </div>
    </div>

    <script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>
    <script>
        // Store original slides
        const originalSlides = [];
        document.querySelectorAll('.car-model-swiper .swiper-wrapper .swiper-slide').forEach(slide => {
            originalSlides.push(slide.cloneNode(true));
        });

        const getResponsiveConfig = (slideCount = originalSlides.length) => {
            const width = window.innerWidth;
            let baseConfig;
            
            if (width <= 575) {
                baseConfig = {
                    spaceBetween: 20,
                    coverflowEffect: {
                        rotate: 12,
                        stretch: 0,
                        depth: 40,
                        modifier: 1,
                        slideShadows: false,
                    },
                    speed: 400,
                };
            } else if (width <= 767) {
                baseConfig = {
                    spaceBetween: 25,
                    coverflowEffect: {
                        rotate: 18,
                        stretch: 0,
                        depth: 60,
                        modifier: 1,
                        slideShadows: false,
                    },
                    speed: 500,
                };
            } else {
                baseConfig = {
                    spaceBetween: 30,
                    coverflowEffect: {
                        rotate: 40,
                        stretch: 0,
                        depth: 100,
                        modifier: 1,
                        slideShadows: true,
                    },
                    speed: 600,
                };
            }

            if (slideCount <= 4) {
                baseConfig.coverflowEffect.rotate = Math.max(10, baseConfig.coverflowEffect.rotate * 0.3);
                baseConfig.coverflowEffect.depth = Math.max(30, baseConfig.coverflowEffect.depth * 0.4);
                baseConfig.speed = Math.max(150, baseConfig.speed * 0.5);
                baseConfig.spaceBetween = Math.max(10, baseConfig.spaceBetween * 0.6);
            } else if (slideCount <= 6) {
                baseConfig.coverflowEffect.rotate = Math.max(15, baseConfig.coverflowEffect.rotate * 0.6);
                baseConfig.coverflowEffect.depth = Math.max(50, baseConfig.coverflowEffect.depth * 0.6);
                baseConfig.speed = Math.max(250, baseConfig.speed * 0.7);
            }

            return baseConfig;
        };

        function updateNavigationVisibility(slideCount = null) {
            const nextBtn = document.querySelector('.swiper-button-next');
            const prevBtn = document.querySelector('.swiper-button-prev');
            const pagination = document.querySelector('.swiper-pagination');
            
            if (slideCount === null) {
                slideCount = document.querySelectorAll('.swiper-slide:not(.swiper-slide-duplicate)').length;
            }
            
            if (slideCount <= 1) {
                if (nextBtn) nextBtn.style.display = 'none';
                if (prevBtn) prevBtn.style.display = 'none';
                if (pagination) pagination.style.display = 'none';
            } else {
                if (nextBtn) nextBtn.style.display = 'block';
                if (prevBtn) prevBtn.style.display = 'block';
                if (pagination) pagination.style.display = 'block';
                
                const opacity = slideCount <= 3 ? '0.8' : '1';
                if (nextBtn) nextBtn.style.opacity = opacity;
                if (prevBtn) prevBtn.style.opacity = opacity;
            }
        }

        function createSwiper(slideCount = originalSlides.length) {
            const config = getResponsiveConfig(slideCount);
            
            return new Swiper('.car-model-swiper', {
                effect: 'coverflow',
                grabCursor: true,
                centeredSlides: true,
                centeredSlidesBounds: true,
                slidesPerView: 1.2,
                spaceBetween: config.spaceBetween,
                speed: config.speed,
                coverflowEffect: config.coverflowEffect,
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                    dynamicBullets: slideCount > 5,
                    dynamicMainBullets: Math.min(3, Math.max(1, Math.floor(slideCount / 2))),
                },
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },
                loop: true,
                loopAdditionalSlides: 0,
                autoplay: false,
                touchRatio: slideCount <= 4 ? 2 : 1,
                touchAngle: slideCount <= 4 ? 75 : 45,
                simulateTouch: true,
                allowTouchMove: true,
                resistance: true,
                resistanceRatio: slideCount <= 4 ? 0.3 : 0.85,
                longSwipes: true,
                longSwipesRatio: slideCount <= 4 ? 0.2 : 0.5,
                longSwipesMs: slideCount <= 4 ? 150 : 300,
                followFinger: true,
                threshold: slideCount <= 4 ? 1 : 5,
                touchMoveStopPropagation: false,
                touchStartPreventDefault: false,
                touchStartForcePreventDefault: false,
                touchReleaseOnEdges: false,
                keyboard: {
                    enabled: true,
                    onlyInViewport: true,
                },
                mousewheel: {
                    enabled: true,
                    sensitivity: slideCount <= 4 ? 1 : 0.7,
                    releaseOnEdges: true,
                    thresholdDelta: slideCount <= 4 ? 30 : 70,
                },
                freeMode: slideCount <= 3 ? {
                    enabled: true,
                    momentum: true,
                    momentumRatio: 0.6,
                    momentumBounce: false,
                    momentumVelocityRatio: 0.6,
                    sticky: true,
                } : false,
                on: {
                    init: function() {
                        setTimeout(() => {
                            const indicator = document.querySelector('.scroll-indicator');
                            if (indicator) {
                                indicator.style.opacity = '0';
                            }
                        }, slideCount <= 3 ? 2000 : 3000);
                        
                        updateNavigationVisibility(slideCount);
                        
                        if (slideCount <= 4) {
                            this.slideTo(0, 0);
                        }
                    },
                    touchStart: function() {
                        const indicator = document.querySelector('.scroll-indicator');
                        if (indicator) {
                            indicator.style.opacity = '0';
                        }
                    },
                    slideChange: function() {
                        this.slides.forEach((slide, index) => {
                            if (index === this.activeIndex) {
                                slide.style.transform += ' scale(1.02)';
                                setTimeout(() => {
                                    slide.style.transform = slide.style.transform.replace(' scale(1.02)', '');
                                }, 200);
                            }
                        });
                    },
                    slidesLengthChange: function() {
                        updateNavigationVisibility();
                    },
                    progress: function(progress) {
                        if (slideCount <= 4) {
                            const progressBar = document.querySelector('.swiper-pagination');
                            if (progressBar) {
                                progressBar.style.opacity = 0.4 + (progress * 0.6);
                            }
                        }
                    },
                    reachEnd: function() {
                        // Auto-home behavior removed - no longer jumps back to first slide
                    }
                },
                breakpoints: {
                    0: {
                        effect: 'slide',
                        centeredSlides: false,
                        slidesPerView: 1.1,
                        coverflowEffect: { rotate: 0, stretch: 0, depth: 0, modifier: 0, slideShadows: false }
                    },
                    576: {
                        effect: 'slide',
                        centeredSlides: false,
                        slidesPerView: 1.25,
                        coverflowEffect: { rotate: 0, stretch: 0, depth: 0, modifier: 0, slideShadows: false }
                    },
                    768: { effect: 'coverflow', centeredSlides: true, slidesPerView: 2 },
                    992: { effect: 'coverflow', centeredSlides: true, slidesPerView: 2.5 },
                    1200: { effect: 'coverflow', centeredSlides: true, slidesPerView: 3 }
                }
            });
        }

        let swiper = createSwiper();

        updateNavigationVisibility();

        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                const currentSlideCount = document.querySelectorAll('.swiper-slide:not(.swiper-slide-duplicate)').length;
                const newConfig = getResponsiveConfig(currentSlideCount);
                
                Object.assign(swiper.params, {
                    spaceBetween: newConfig.spaceBetween,
                    speed: newConfig.speed,
                    coverflowEffect: newConfig.coverflowEffect
                });
                
                swiper.update();
                updateNavigationVisibility(currentSlideCount);
                
                if (currentSlideCount <= 4) {
                    swiper.slideTo(0, 0);
                }
            }, 250);
        });

        const categoryTabs = document.querySelector('.category-tabs');

        categoryTabs.addEventListener('click', (e) => {
            if (e.target.classList.contains('tab-btn')) {
                if (e.target.classList.contains('loading')) return;
                
                const currentActive = categoryTabs.querySelector('.active');
                currentActive.classList.remove('active');
                e.target.classList.add('active', 'loading');

                const category = e.target.dataset.category;
                
                if (originalSlides.length > 0) {
                    swiper.el.style.opacity = '0.7';
                    swiper.el.style.pointerEvents = 'none';
                    
                    setTimeout(() => {
                        swiper.destroy(true, true);
                        
                        const filteredSlides = originalSlides.filter(slide => {
                            return category === 'all' || slide.dataset.category === category;
                        });

                        const wrapper = document.querySelector('.swiper-wrapper');
                        wrapper.innerHTML = '';
                        
                        if (filteredSlides.length > 0) {
                            filteredSlides.forEach(slide => {
                                wrapper.appendChild(slide.cloneNode(true));
                            });
                            
                            swiper = createSwiper(filteredSlides.length);
                        }
                        
                        setTimeout(() => {
                            swiper.el.style.opacity = '1';
                            swiper.el.style.pointerEvents = 'auto';
                            e.target.classList.remove('loading');
                            updateNavigationVisibility(filteredSlides.length);
                            
                            if (filteredSlides.length <= 4) {
                                swiper.slideTo(0, 0);
                            }
                        }, 200);
                    }, 150);
                }
            }
        });

        let startX = 0;
        let scrollLeft = 0;
        let isScrolling = false;

        categoryTabs.addEventListener('touchstart', (e) => {
            startX = e.touches[0].pageX - categoryTabs.offsetLeft;
            scrollLeft = categoryTabs.scrollLeft;
            isScrolling = true;
        });

        categoryTabs.addEventListener('touchmove', (e) => {
            if (!isScrolling) return;
            e.preventDefault();
            const x = e.touches[0].pageX - categoryTabs.offsetLeft;
            const walk = (x - startX) * 2;
            categoryTabs.scrollLeft = scrollLeft - walk;
        });

        categoryTabs.addEventListener('touchend', () => {
            isScrolling = false;
        });

        let interactionTimer;
        const hideScrollIndicator = () => {
            clearTimeout(interactionTimer);
            interactionTimer = setTimeout(() => {
                const indicator = document.querySelector('.scroll-indicator');
                if (indicator) {
                    indicator.style.opacity = '0';
                }
            }, 2000);
        };

        ['touchstart', 'mousedown', 'wheel', 'keydown'].forEach(event => {
            document.addEventListener(event, hideScrollIndicator);
        });
    </script>
</body>
</html>
