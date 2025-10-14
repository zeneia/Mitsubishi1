<?php
session_start();
include_once(dirname(__DIR__) . '/includes/database/db_conn.php');

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Customer') {
    header("Location: login.php");
    exit;
}

// Check if vehicle ID is set
if (!isset($_GET['vehicle_id']) || empty($_GET['vehicle_id']) || !is_numeric($_GET['vehicle_id'])) {
    header("Location: car_menu.php");
    exit;
}

$vehicle_id = (int)$_GET['vehicle_id'];

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

// Fetch user details for header
$stmt = $connect->prepare("SELECT * FROM accounts WHERE Id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$displayName = !empty($user['FirstName']) ? $user['FirstName'] : $user['Username'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>3D View - <?php echo htmlspecialchars($vehicle['model_name']); ?> - Mitsubishi Motors</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Google Model Viewer -->
    <script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/4.0.0/model-viewer.min.js"></script>
    <style>
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
            overflow-x: hidden;
            font-size: 16px;
        }

        /* Modern Header Design */
        .header {
            background: var(--bg-primary);
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
            color: var(--primary-color);
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
            color: var(--text-secondary);
            font-weight: 500;
        }

        .user-name {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
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

        /* Main Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
        }

        /* Viewer Card */
        .viewer-card {
            background: var(--bg-primary);
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
        }

        /* Card Header */
        .card-header {
            background: linear-gradient(135deg, var(--bg-tertiary), var(--bg-secondary));
            padding: 2rem;
            border-bottom: 1px solid var(--border-color);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 1.5rem;
        }

        .vehicle-info {
            flex: 1;
            min-width: 250px;
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
        }

        /* View Toggle */
        .view-toggle {
            display: flex;
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            padding: 0.25rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
        }

        .toggle-btn {
            background: transparent;
            color: var(--text-secondary);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .toggle-btn:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .toggle-btn.active {
            background: var(--primary-color);
            color: white;
            box-shadow: var(--shadow-md);
        }

        /* Viewer Container */
        .viewer-container {
            position: relative;
            height: 70vh;
            background: var(--bg-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        model-viewer {
            width: 100%;
            height: 100%;
            background-color: transparent;
            --poster-color: transparent;
        }

        /* Fallback Viewer */
        .fallback-360-viewer {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .image-carousel {
            position: relative;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .carousel-image {
            max-width: 85%;
            max-height: 85%;
            object-fit: contain;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
        }

        .carousel-controls {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: var(--bg-primary);
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
            width: 56px;
            height: 56px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.2rem;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-lg);
        }

        .carousel-controls:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-50%) scale(1.1);
        }

        .carousel-prev {
            left: 2rem;
        }

        .carousel-next {
            right: 2rem;
        }

        /* Modern Control Panel */
        .controls-panel {
            position: absolute;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%);
            background: var(--bg-primary);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            display: flex;
            gap: 2rem;
            align-items: center;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-xl);
            z-index: 20;
        }

        .control-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .control-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--primary-color);
            white-space: nowrap;
        }

        .control-btn,
        .rotation-btn {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            padding: 0.75rem 1rem;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: var(--shadow-sm);
        }

        .control-btn:hover,
        .rotation-btn:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .rotation-btn {
            padding: 0.75rem;
            border-radius: 50%;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .rotation-controls {
            display: flex;
            gap: 0.5rem;
        }

        /* Color picker */
        .color-picker {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
            max-width: 420px;
        }

        .color-swatch {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 2px solid var(--border-color);
            cursor: pointer;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f5f5f5;
        }

        .color-swatch:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .color-swatch.active { border-color: var(--primary-color); box-shadow: 0 0 0 2px rgba(230,0,18,0.15); }

        /* Info Panel */
        .info-panel {
            position: absolute;
            top: 2rem;
            right: 2rem;
            background: var(--bg-primary);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            max-width: 320px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-lg);
        }

        .info-panel h3 {
            color: var(--text-primary);
            margin-bottom: 1rem;
            font-size: 1.25rem;
            font-weight: 700;
        }

        .info-panel p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .feature-list {
            list-style: none;
            padding: 0;
        }

        .feature-list li {
            color: var(--text-secondary);
            font-size: 0.875rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: var(--transition);
        }

        .feature-list li:last-child {
            border-bottom: none;
        }

        .feature-list li:hover {
            color: var(--primary-color);
            padding-left: 0.5rem;
        }

        .feature-list li i {
            color: var(--primary-color);
            width: 16px;
        }

        /* Loading Screen */
        .loading-screen {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--bg-primary);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }

        .spinner {
            width: 60px;
            height: 60px;
            border: 4px solid var(--border-color);
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 1.5rem;
        }

        .loading-text {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-secondary);
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Fallback Message */
        .fallback-message {
            text-align: center;
            color: var(--text-secondary);
            padding: 2rem;
        }

        .fallback-message i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: var(--primary-color);
            opacity: 0.5;
        }

        .fallback-message p {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header {
                padding: 1rem;
                width: 100%;
            }

            .header-container {
                flex-direction: column;
                gap: 1rem;
                width: 100%;
            }

            .user-section {
                width: 100%;
                justify-content: space-between;
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

            .viewer-container {
                height: 50vh;
            }

            .controls-panel {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
                border-radius: var(--radius-lg);
                bottom: 1rem;
                left: 1rem;
                right: 1rem;
                transform: none;
            }

            .control-group {
                justify-content: center;
                width: 100%;
            }

            .info-panel {
                position: static;
                margin-top: 1rem;
                max-width: none;
            }

            .carousel-controls {
                width: 48px;
                height: 48px;
            }

            .carousel-prev {
                left: 1rem;
            }

            .carousel-next {
                right: 1rem;
            }
        }

        @media (min-width: 769px) and (max-width: 1024px) {
            .viewer-container {
                height: 60vh;
            }

            .info-panel {
                max-width: 280px;
            }

            .controls-panel {
                gap: 1.5rem;
                padding: 1.25rem;
            }
        }

        /* Smooth Transitions */
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
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
                <div class="user-avatar"><?php echo strtoupper(substr($displayName, 0, 1)); ?></div>
                <div class="user-info">
                    <span class="welcome-label">Welcome</span>
                    <span class="user-name"><?php echo htmlspecialchars($displayName); ?></span>
                </div>
                <button class="logout-btn" onclick="window.location.href='logout.php'">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </button>
            </div>
        </div>
    </header>

    <main class="main-container">
        <nav class="nav-section">
            <a href="car_details.php?id=<?php echo $vehicle_id; ?>" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                <span>Back to Details</span>
            </a>
        </nav>

        <div class="content-grid">
            <div class="viewer-card fade-in">
                <div class="card-header">
                    <div class="header-content">
                        <div class="vehicle-info">
                            <h1 class="vehicle-title"><?php echo htmlspecialchars($vehicle['model_name']); ?> 3D View</h1>
                            <p class="vehicle-subtitle">Interactive 360° Vehicle Viewing Experience</p>
                        </div>
                        <div class="view-toggle">
                            <button class="toggle-btn active" data-view="exterior">
                                <i class="fas fa-car"></i>
                                <span>Exterior</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="viewer-container">
                    <div class="loading-screen" id="loadingScreen">
                        <div class="spinner"></div>
                        <p class="loading-text">Loading 3D Model...</p>
                    </div>

                    <!-- Google Model Viewer for 3D models -->
                    <model-viewer id="model-viewer"
                        alt="<?php echo htmlspecialchars($vehicle['model_name']); ?> 3D Model"
                        src=""
                        camera-controls
                        touch-action="pan-y"
                        auto-rotate
                        shadow-intensity="1"
                        camera-orbit="0deg 75deg 3.75m"
                        min-camera-orbit="auto auto 0.01m"
                        max-camera-orbit="auto auto 1000m"
                        style="display: none;">
                    </model-viewer>

                    <!-- Fallback 360 Image Carousel -->
                    <div class="fallback-360-viewer" id="fallback-viewer">
                        <div class="image-carousel" id="image-carousel">
                            <img class="carousel-image" id="carousel-image" src="" alt="360° View" style="display: none;">
                            <button class="carousel-controls carousel-prev" id="prev-btn" onclick="previousImage()">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button class="carousel-controls carousel-next" id="next-btn" onclick="nextImage()">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                        <div class="fallback-message" id="fallback-message" style="display: none;">
                            <i class="fas fa-cube"></i>
                            <p>3D model not available for this vehicle.</p>
                            <p>Showing 360° images instead.</p>
                        </div>
                    </div>

                    <div class="controls-panel">
                        <div class="control-group">
                            <span class="control-label">Auto Rotate:</span>
                            <button class="control-btn" id="autoRotateBtn">
                                <i class="fas fa-sync"></i>
                                <span>Start</span>
                            </button>
                        </div>

                        <div class="control-group">
                            <span class="control-label">Manual:</span>
                            <div class="rotation-controls">
                                <button class="rotation-btn" id="rotateLeft">
                                    <i class="fas fa-undo"></i>
                                </button>
                                <button class="rotation-btn" id="rotateRight">
                                    <i class="fas fa-redo"></i>
                                </button>
                            </div>
                        </div>

                        <div class="control-group">
                            <button class="control-btn" id="zoomIn">
                                <i class="fas fa-search-plus"></i>
                                <span>Zoom In</span>
                            </button>
                            <button class="control-btn" id="zoomOut">
                                <i class="fas fa-search-minus"></i>
                                <span>Zoom Out</span>
                            </button>
                            <button class="control-btn" id="resetView">
                                <i class="fas fa-sync"></i>
                                <span>Reset</span>
                            </button>
                        </div>

                        <div class="control-group">
                            <span class="control-label">Color:</span>
                            <div id="colorPicker" class="color-picker" aria-label="Color options"></div>
                        </div>
                    </div>

                    <div class="info-panel">
                        <h3 id="viewTitle">Exterior View</h3>
                        <p id="viewDescription">Explore the exterior design and features of the <?php echo htmlspecialchars($vehicle['model_name']); ?>.</p>
                        <ul class="feature-list" id="featureList">
                            <li><i class="fas fa-car"></i> Aerodynamic Design</li>
                            <li><i class="fas fa-lightbulb"></i> LED Headlights</li>
                            <li><i class="fas fa-shield-alt"></i> Safety Features</li>
                            <li><i class="fas fa-cog"></i> Alloy Wheels</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Global variables
        let currentImageIndex = 0;
        let images360 = [];
        let autoRotateInterval = null;
        let isAutoRotating = false;
        const vehicleId = <?php echo $vehicle_id; ?>;
        // Project base (e.g., /Mitsubishi) and origin for robust, cross-env URLs
        const PROJECT_BASE = "<?php echo rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/'); ?>";
        const ORIGIN = window.location.origin;
        let modelControlsBound = false;

        // Color-related state
        let colorOptions = [];
        let colorModels = {}; // map: normalizedColor -> modelPath
        let selectedColor = null;

        // Normalize any filesystem or partial web path to a full web URL under the project base
        function toProjectWebUrl(pathInput) {
            try {
                if (!pathInput) return null;
                if (/^https?:\/\//i.test(pathInput)) return pathInput; // already absolute URL
                let p = String(pathInput).replace(/\\/g, '/');
                // If string already contains '/uploads/', extract from there
                const idx = p.toLowerCase().indexOf('/uploads/');
                let sub = idx !== -1 ? p.slice(idx) : (p.startsWith('/') ? p : '/' + p);
                // Ensure it is scoped to the project base (handles subfolder deployments)
                if (!sub.startsWith(PROJECT_BASE + '/')) {
                    sub = PROJECT_BASE + sub;
                }
                return ORIGIN + sub;
            } catch (e) {
                console.error('toProjectWebUrl error:', e, pathInput);
                return null;
            }
        }

        // Initialize the viewer on page load
        document.addEventListener('DOMContentLoaded', function() {
            initializeViewer();
        });

        async function initializeViewer() {
            try {
                // Use vehicle data from PHP instead of making API call
                const view360Data = <?php echo json_encode($vehicle['view_360_images'] ?? ''); ?>;
                const colorOptionsRaw = <?php echo json_encode($vehicle['color_options'] ?? ''); ?>;
                colorOptions = Array.isArray(colorOptionsRaw)
                    ? colorOptionsRaw
                    : String(colorOptionsRaw || '')
                        .split(',')
                        .map(s => s.trim())
                        .filter(Boolean);
                const normalizedColors = colorOptions.map(c => normalizeColorKey(c));

                if (view360Data) {
                    // Parse the view_360_images data (it might be JSON string or array)
                    let view360Files = [];
                    if (typeof view360Data === 'string') {
                        try {
                            view360Files = JSON.parse(view360Data);
                        } catch (e) {
                            // If it's not JSON, treat as single file path
                            view360Files = [view360Data];
                        }
                    } else if (Array.isArray(view360Data)) {
                        view360Files = view360Data;
                    } else {
                        view360Files = [view360Data];
                    }

                    // Build color->model map if objects provided or infer by filename
                    colorModels = buildColorModelMap(view360Files, normalizedColors);

                    // Collect plain model files for non-mapped case
                    const modelFiles = flattenToArray(view360Files).filter(filePath => 
                        filePath && (String(filePath).toLowerCase().endsWith('.glb') || String(filePath).toLowerCase().endsWith('.gltf'))
                    );

                    // Render color picker UI
                    renderColorPicker();

                    // Prefer a color with explicit model mapping
                    const firstColorWithModel = normalizedColors.find(nc => colorModels[nc]);
                    if (firstColorWithModel) {
                        selectedColor = firstColorWithModel;
                        setActiveColorUI(selectedColor);
                        await loadModelFromPath(colorModels[selectedColor]);
                        return;
                    }

                    if (modelFiles.length > 0) {
                        await loadModelFromPath(modelFiles[0]);
                    } else {
                        // Check for image files
                        const imageFiles = flattenToArray(view360Files).filter(filePath => 
                            filePath && String(filePath).toLowerCase().match(/\.(jpg|jpeg|png|gif|webp)$/)
                        );
                        
                        if (imageFiles.length > 0) {
                            await setup360ImageCarouselFromPaths(imageFiles);
                        } else {
                            // Fallback to legacy loader that reads BLOBs from server if DB stores images as LOBs/serialized
                            await setup360ImageCarousel();
                        }
                    }
                } else {
                    // No 360 data available, attempt legacy fetch then fallback message
                    await setup360ImageCarousel();
                }
            } catch (error) {
                console.error('Error initializing viewer:', error);
                showFallbackMessage();
            }
        }

        function flattenToArray(val) {
            if (Array.isArray(val)) return val;
            if (val && typeof val === 'object') return Object.values(val);
            return [val];
        }

        function normalizeColorKey(c) {
            return String(c || '').trim().toLowerCase();
        }

        function buildColorModelMap(raw, normalizedColors) {
            const map = {};
            if (!raw) return map;
            // Case 1: array of objects [{color, model}] or [{color, file}]
            if (Array.isArray(raw)) {
                raw.forEach(item => {
                    if (item && typeof item === 'object') {
                        const color = normalizeColorKey(item.color || item.colour || '');
                        const model = item.model || item.file || item.src || item.path;
                        if (color && model && /\.(glb|gltf)$/i.test(String(model))) {
                            map[color] = model;
                        }
                    }
                });
                // If still empty, try infer by filename
                if (Object.keys(map).length === 0) {
                    const paths = raw.filter(x => typeof x === 'string');
                    inferFromFilenames(paths, normalizedColors, map);
                }
                return map;
            }
            // Case 2: object keyed by color
            if (raw && typeof raw === 'object') {
                Object.keys(raw).forEach(k => {
                    const color = normalizeColorKey(k);
                    const model = raw[k];
                    if (color && model && /\.(glb|gltf)$/i.test(String(model))) {
                        map[color] = model;
                    }
                });
                return map;
            }
            // Case 3: string or unknown -> no map
            return map;
        }

        function inferFromFilenames(paths, normalizedColors, map) {
            (paths || []).forEach(p => {
                const lower = String(p).toLowerCase();
                if (!/\.(glb|gltf)$/.test(lower)) return;
                for (const c of normalizedColors) {
                    if (c && lower.includes(c)) { map[c] = p; break; }
                }
            });
        }

        function renderColorPicker() {
            const container = document.getElementById('colorPicker');
            if (!container) return;
            container.innerHTML = '';
            if (!colorOptions || colorOptions.length === 0) return;
            colorOptions.forEach(color => {
                const key = normalizeColorKey(color);
                const swatch = document.createElement('button');
                swatch.type = 'button';
                swatch.className = 'color-swatch';
                swatch.title = color;
                // Try to set CSS color safely; fall back to label if invalid
                swatch.style.background = color;
                swatch.dataset.colorKey = key;
                swatch.addEventListener('click', () => selectColor(key));
                container.appendChild(swatch);
            });
        }

        async function selectColor(colorKey) {
            selectedColor = colorKey;
            setActiveColorUI(colorKey);
            const model = colorModels[colorKey];
            if (model) {
                await loadModelFromPath(model);
            } else {
                // No model for this color, show images or fallback
                if (images360 && images360.length > 0) {
                    showImageCarouselFromPaths(images360);
                } else {
                    await setup360ImageCarousel();
                }
            }
        }

        function setActiveColorUI(colorKey) {
            const container = document.getElementById('colorPicker');
            if (!container) return;
            Array.from(container.children).forEach(el => {
                el.classList.toggle('active', el.dataset.colorKey === colorKey);
            });
        }

        async function loadModelFromPath(modelPath) {
            try {
                const fullUrl = toProjectWebUrl(modelPath);
                if (!fullUrl) throw new Error('Invalid model URL generated from path: ' + modelPath);

                console.log('Loading 3D model from path:', fullUrl);
                
                const modelViewer = document.getElementById('model-viewer');
                const fallbackViewer = document.getElementById('fallback-viewer');
                const loading = document.getElementById('loadingScreen');

                // Attach one-time listeners for robust error handling and loading state
                const onLoad = () => {
                    loading.style.display = 'none';
                    modelViewer.removeEventListener('load', onLoad);
                };
                const onError = (e) => {
                    console.error('Model viewer error:', e);
                    modelViewer.removeEventListener('error', onError);
                    showFallbackMessage();
                };
                modelViewer.addEventListener('load', onLoad, { once: true });
                modelViewer.addEventListener('error', onError, { once: true });

                // Set the model source to the full URL
                modelViewer.src = fullUrl;
                modelViewer.style.display = 'block';
                fallbackViewer.style.display = 'none';

                // Initialize controls for model-viewer
                setupModelViewerControls(modelViewer);
            } catch (error) {
                console.error('Error loading 3D model:', error);
                showFallbackMessage();
            }
        }
        
        function setupModelViewer(modelData) {
            const modelViewer = document.getElementById('model-viewer');
            const fallbackViewer = document.getElementById('fallback-viewer');
            
            // Create blob URL for the model
            let blob;
            if (modelData instanceof ArrayBuffer) {
                blob = new Blob([modelData], { type: 'model/gltf-binary' });
            } else {
                blob = new Blob([modelData], { type: 'model/gltf-binary' });
            }
            const modelUrl = URL.createObjectURL(blob);
            
            modelViewer.src = modelUrl;
            modelViewer.style.display = 'block';
            fallbackViewer.style.display = 'none';
            
            // Hide loading screen
            document.getElementById('loadingScreen').style.display = 'none';
            
            // Setup model viewer controls
            setupModelViewerControls(modelViewer);
        }

        async function setup360ImageCarouselFromPaths(imagePaths) {
            try {
                const urls = (imagePaths || []).map(p => toProjectWebUrl(p)).filter(Boolean);
                images360 = urls;
                
                if (images360.length > 0) {
                    showImageCarouselFromPaths();
                } else {
                    showFallbackMessage();
                }
            } catch (error) {
                console.error('Error setting up 360 image carousel:', error);
                showFallbackMessage();
            }
        }
        
        async function setup360ImageCarousel() {
            try {
                // Fetch 360 images from the database
                const response = await fetch(`get_360_images.php?vehicle_id=${vehicleId}`);
                const result = await response.json();
                
                if (result.success && result.images && result.images.length > 0) {
                    images360 = result.images;
                    showImageCarousel();
                } else {
                    showFallbackMessage();
                }
            } catch (error) {
                console.error('Error loading 360 images:', error);
                showFallbackMessage();
            }
        }

        function showImageCarouselFromPaths() {
            const modelViewer = document.getElementById('model-viewer');
            const fallbackViewer = document.getElementById('fallback-viewer');
            const carouselImage = document.getElementById('carousel-image');
            const fallbackMessage = document.getElementById('fallback-message');
            
            modelViewer.style.display = 'none';
            fallbackViewer.style.display = 'flex';
            fallbackMessage.style.display = 'none';
            
            if (images360.length > 0) {
                carouselImage.src = images360[0]; // Full URL with correct port
                carouselImage.style.display = 'block';
                
                // Show/hide navigation buttons
                document.getElementById('prev-btn').style.display = images360.length > 1 ? 'flex' : 'none';
                document.getElementById('next-btn').style.display = images360.length > 1 ? 'flex' : 'none';
            }
            
            // Hide loading screen
            document.getElementById('loadingScreen').style.display = 'none';
            
            // Setup carousel controls
            setupCarouselControls();
        }
        
        function showImageCarousel() {
            const modelViewer = document.getElementById('model-viewer');
            const fallbackViewer = document.getElementById('fallback-viewer');
            const carouselImage = document.getElementById('carousel-image');
            const fallbackMessage = document.getElementById('fallback-message');
            
            modelViewer.style.display = 'none';
            fallbackViewer.style.display = 'flex';
            fallbackMessage.style.display = 'none';
            
            if (images360.length > 0) {
                carouselImage.src = `data:image/jpeg;base64,${images360[0]}`;
                carouselImage.style.display = 'block';
                
                // Show/hide navigation buttons
                document.getElementById('prev-btn').style.display = images360.length > 1 ? 'flex' : 'none';
                document.getElementById('next-btn').style.display = images360.length > 1 ? 'flex' : 'none';
            }
            
            // Hide loading screen
            document.getElementById('loadingScreen').style.display = 'none';
            
            // Setup carousel controls
            setupCarouselControls();
        }

        function showFallbackMessage() {
            const modelViewer = document.getElementById('model-viewer');
            const fallbackViewer = document.getElementById('fallback-viewer');
            const carouselImage = document.getElementById('carousel-image');
            const fallbackMessage = document.getElementById('fallback-message');
            
            modelViewer.style.display = 'none';
            fallbackViewer.style.display = 'flex';
            carouselImage.style.display = 'none';
            fallbackMessage.style.display = 'block';
            
            // Hide navigation buttons
            document.getElementById('prev-btn').style.display = 'none';
            document.getElementById('next-btn').style.display = 'none';
            
            // Hide loading screen
            document.getElementById('loadingScreen').style.display = 'none';
        }

        function setupModelViewerControls(modelViewer) {
            if (modelControlsBound) return; // prevent duplicate listeners
            modelControlsBound = true;
            // Auto-rotate control
            document.getElementById('autoRotateBtn').addEventListener('click', function() {
                isAutoRotating = !isAutoRotating;
                modelViewer.autoRotate = isAutoRotating;
                this.querySelector('span').textContent = isAutoRotating ? 'Stop' : 'Start';
            });

            // Manual rotation controls
            document.getElementById('rotateLeft').addEventListener('click', function() {
                const currentOrbit = modelViewer.getCameraOrbit();
                modelViewer.cameraOrbit = `${currentOrbit.theta - 0.5}rad ${currentOrbit.phi}rad ${currentOrbit.radius}m`;
            });

            document.getElementById('rotateRight').addEventListener('click', function() {
                const currentOrbit = modelViewer.getCameraOrbit();
                modelViewer.cameraOrbit = `${currentOrbit.theta + 0.5}rad ${currentOrbit.phi}rad ${currentOrbit.radius}m`;
            });

            // Reset view
            document.getElementById('resetView').addEventListener('click', function() {
                modelViewer.resetTurntableRotation();
                modelViewer.jumpCameraToGoal();
            });

            // Zoom controls (handled by model-viewer's camera-controls)
            document.getElementById('zoomIn').addEventListener('click', function() {
                const currentOrbit = modelViewer.getCameraOrbit();
                modelViewer.cameraOrbit = `${currentOrbit.theta}rad ${currentOrbit.phi}rad ${currentOrbit.radius * 0.8}m`;
            });

            document.getElementById('zoomOut').addEventListener('click', function() {
                const currentOrbit = modelViewer.getCameraOrbit();
                modelViewer.cameraOrbit = `${currentOrbit.theta}rad ${currentOrbit.phi}rad ${currentOrbit.radius * 1.2}m`;
            });
        }

        function setupCarouselControls() {
            // Auto-rotate control for carousel
            document.getElementById('autoRotateBtn').addEventListener('click', function() {
                isAutoRotating = !isAutoRotating;
                if (isAutoRotating) {
                    startAutoRotateCarousel();
                    this.querySelector('span').textContent = 'Stop';
                } else {
                    stopAutoRotateCarousel();
                    this.querySelector('span').textContent = 'Start';
                }
            });

            // Manual rotation controls
            document.getElementById('rotateLeft').addEventListener('click', previousImage);
            document.getElementById('rotateRight').addEventListener('click', nextImage);

            // Reset view
            document.getElementById('resetView').addEventListener('click', function() {
                currentImageIndex = 0;
                updateCarouselImage();
            });

            // Zoom controls (not applicable for images, but we can show a message)
            document.getElementById('zoomIn').addEventListener('click', function() {
                // Could implement image zoom here if needed
                console.log('Zoom in - not implemented for image carousel');
            });

            document.getElementById('zoomOut').addEventListener('click', function() {
                // Could implement image zoom here if needed
                console.log('Zoom out - not implemented for image carousel');
            });
        }

        function previousImage() {
            if (images360.length > 1) {
                currentImageIndex = (currentImageIndex - 1 + images360.length) % images360.length;
                updateCarouselImage();
            }
        }

        function nextImage() {
            if (images360.length > 1) {
                currentImageIndex = (currentImageIndex + 1) % images360.length;
                updateCarouselImage();
            }
        }

        function updateCarouselImage() {
            const carouselImage = document.getElementById('carousel-image');
            if (images360.length > 0) {
                const src = images360[currentImageIndex] || '';
                const isAbsoluteUrl = /^https?:\/\//i.test(src) || src.startsWith('data:') || src.startsWith(PROJECT_BASE + '/');
                carouselImage.src = isAbsoluteUrl ? src : `data:image/jpeg;base64,${src}`;
            }
        }

        function startAutoRotateCarousel() {
            if (images360.length > 1) {
                autoRotateInterval = setInterval(nextImage, 2000); // Change image every 2 seconds
            }
        }

        function stopAutoRotateCarousel() {
            if (autoRotateInterval) {
                clearInterval(autoRotateInterval);
                autoRotateInterval = null;
            }
        }

        // View toggle functionality
        const viewBtns = document.querySelectorAll('.toggle-btn');
        viewBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const view = this.dataset.view;
                viewBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                // Update info panel
                const viewTitle = document.getElementById('viewTitle');
                const viewDescription = document.getElementById('viewDescription');
                const featureList = document.getElementById('featureList');

                if (view === 'exterior') {
                    viewTitle.textContent = 'Exterior View';
                    viewDescription.textContent = 'Explore the exterior design and features of the vehicle.';
                    featureList.innerHTML = `
                        <li><i class="fas fa-car"></i> Aerodynamic Design</li>
                        <li><i class="fas fa-lightbulb"></i> LED Headlights</li>
                        <li><i class="fas fa-shield-alt"></i> Safety Features</li>
                        <li><i class="fas fa-cog"></i> Alloy Wheels</li>
                    `;
                    
                    // Set exterior camera view for model viewer
                    const modelViewer = document.getElementById('model-viewer');
                    if (modelViewer && modelViewer.style.display !== 'none') {
                        modelViewer.cameraOrbit = '0deg 75deg 3.75m';
                    }
                }
            });
        });

    </script>
</body>
</html>