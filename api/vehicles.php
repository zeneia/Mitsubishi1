<?php
// Start output buffering to prevent any accidental output
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Enable error reporting for debugging but suppress display in API
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Detect oversized POST (likely exceeded post_max_size or web server body limit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES)) {
    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
    if ($contentLength > 0) {
        // Compute readable ini values
        $postMax = ini_get('post_max_size');
        $uploadMax = ini_get('upload_max_filesize');
        http_response_code(413);
        echo json_encode([
            'success' => false,
            'message' => 'Upload failed: request size exceeds server limits. Please reduce file size or increase server limits.',
            'debug' => [
                'content_length' => $contentLength,
                'post_max_size' => $postMax,
                'upload_max_filesize' => $uploadMax
            ]
        ]);
        ob_end_flush();
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

try {
    include_once(dirname(__DIR__) . '/includes/init.php');
} catch (Exception $e) {
    ob_clean();
    error_log("Init include error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'System initialization failed']);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_role'])) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not logged in', 'debug' => 'No user_role in session']);
    exit();
}

// Check permissions based on request method
$method = $_SERVER['REQUEST_METHOD'];

// Handle method override for FormData PUT requests
if ($method === 'POST' && isset($_POST['_method']) && $_POST['_method'] === 'PUT') {
    $method = 'PUT';
}

if ($method !== 'GET' && $_SESSION['user_role'] !== 'Admin') {
    ob_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied - Admin role required for modifications', 'debug' => 'User role: ' . $_SESSION['user_role'] . ', Method: ' . $method]);
    exit();
}

$pdo = $GLOBALS['pdo'] ?? null;

if (!$pdo) {
    ob_clean();
    error_log("Vehicles API: No PDO connection available");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection not available', 'debug' => 'PDO not found in globals']);
    exit();
}

$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            if ($action === 'stock') {
                getVehicleStock($pdo);
            } elseif ($action === 'low-stock') {
                getLowStockVehicles($pdo);
            } elseif (isset($_GET['categories'])) {
                getVehicleCategories($pdo);
            } elseif (isset($_GET['stats'])) {
                getVehicleStats($pdo);
            } elseif (isset($_GET['id'])) {
                getVehicleById($pdo, $_GET['id']);
            } else {
                getVehicles($pdo);
            }
            break;
        case 'POST':
            createVehicle($pdo);
            break;
        case 'PUT':
            if ($action === 'stock') {
                updateVehicleStock($pdo);
            } elseif ($action === 'price') {
                updateVehiclePrice($pdo);
            } else {
                updateVehicle($pdo);
            }
            break;
        case 'DELETE':
            deleteVehicle($pdo);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    ob_clean(); // Clear any output buffer
    error_log("Vehicles API Error - Method: {$method}, Action: {$action}, Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Internal server error',
        'debug' => $_SERVER['SERVER_NAME'] === 'localhost' ? $e->getMessage() : 'Check server logs'
    ]);
} catch (Error $e) {
    ob_clean(); // Clear any output buffer
    error_log("Vehicles API Fatal Error - Method: {$method}, Action: {$action}, Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Internal server error',
        'debug' => $_SERVER['SERVER_NAME'] === 'localhost' ? $e->getMessage() : 'Check server logs'
    ]);
}

// Flush output buffer
ob_end_flush();

function getVehicles($pdo) {
    try {
        error_log("getVehicles function called");
        
        $search = $_GET['search'] ?? '';
        $category = $_GET['category'] ?? '';
        $availability = $_GET['availability'] ?? '';
        
        error_log("Search params - search: '$search', category: '$category', availability: '$availability'");
        
        $whereConditions = [];
        $params = [];

        if (!empty($search)) {
            $whereConditions[] = "(model_name LIKE ? OR variant LIKE ? OR category LIKE ?)";
            $searchParam = "%$search%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }

        if (!empty($category) && $category !== 'all') {
            $whereConditions[] = "category = ?";
            $params[] = $category;
        }

        if (!empty($availability) && $availability !== 'all') {
            $whereConditions[] = "availability_status = ?";
            $params[] = $availability;
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // Exclude LONGBLOB fields (image fields) that cause JSON encoding issues
        $sql = "SELECT id, model_name, variant, year_model, category, engine_type, transmission, 
                       fuel_type, seating_capacity, key_features, base_price, promotional_price,
                       min_downpayment_percentage, financing_terms, color_options, popular_color,
                       stock_quantity, min_stock_alert, availability_status, expected_delivery_time,
                       created_at, updated_at
                FROM vehicles $whereClause ORDER BY model_name, variant";
        error_log("Executing SQL: $sql");
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Found " . count($vehicles) . " vehicles");
        
        // Clean and ensure data integrity for each vehicle
        $cleanVehicles = [];
        foreach ($vehicles as $vehicle) {
            $cleanVehicle = [];
            foreach ($vehicle as $key => $value) {
                // Ensure proper UTF-8 encoding for string fields
                if (is_string($value) && $value !== null) {
                    $cleanVehicle[$key] = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                } else {
                    $cleanVehicle[$key] = $value;
                }
            }
            $cleanVehicles[] = $cleanVehicle;
        }
        
        $response = ['success' => true, 'data' => $cleanVehicles];
        $json = json_encode($response, JSON_HEX_APOS | JSON_HEX_QUOT);
        
        if ($json === false) {
            error_log("JSON encoding failed: " . json_last_error_msg());
            echo json_encode(['success' => false, 'message' => 'JSON encoding failed', 'debug' => json_last_error_msg()]);
        } else {
            error_log("Returning JSON response of length: " . strlen($json));
            echo $json;
        }
    } catch (Exception $e) {
        error_log("getVehicles error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to load vehicles', 'debug' => $e->getMessage()]);
    }
}

function getVehicleStock($pdo) {
    $sql = "SELECT id, model_name, variant, stock_quantity, min_stock_alert, 
                   (stock_quantity - min_stock_alert) as stock_difference,
                   CASE 
                       WHEN stock_quantity <= 0 THEN 'out_of_stock'
                       WHEN stock_quantity <= min_stock_alert THEN 'low_stock'
                       ELSE 'in_stock'
                   END as stock_status
            FROM vehicles 
            ORDER BY stock_quantity ASC, model_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $stockData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $stockData]);
}

function updateVehicleStock($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['vehicle_id']) || !isset($input['quantity'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Vehicle ID and quantity are required']);
        return;
    }

    $vehicleId = (int)$input['vehicle_id'];
    $quantity = (int)$input['quantity'];
    $operation = $input['operation'] ?? 'set'; // set, add, subtract

    try {
        $pdo->beginTransaction();

        if ($operation === 'set') {
            $sql = "UPDATE vehicles SET stock_quantity = ? WHERE id = ?";
            $params = [$quantity, $vehicleId];
        } elseif ($operation === 'add') {
            $sql = "UPDATE vehicles SET stock_quantity = stock_quantity + ? WHERE id = ?";
            $params = [$quantity, $vehicleId];
        } elseif ($operation === 'subtract') {
            $sql = "UPDATE vehicles SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?";
            $params = [$quantity, $vehicleId];
        } else {
            throw new Exception('Invalid operation');
        }
        
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute($params);

        if (!$success) {
            throw new Exception('Failed to update vehicle stock');
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Stock updated successfully']);

    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateVehiclePrice($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);

    $requiredFields = ['vehicle_id', 'base_price'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Field '$field' is required"]);
            return;
        }
    }

    try {
        $sql = "UPDATE vehicles SET base_price = ?, promotional_price = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([
            $input['base_price'],
            $input['promotional_price'] ?? null,
            $input['vehicle_id']
        ]);

        if (!$success) {
            throw new Exception('Failed to update vehicle prices');
        }

        echo json_encode(['success' => true, 'message' => 'Prices updated successfully']);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getLowStockVehicles($pdo) {
    $sql = "SELECT id, model_name, variant, stock_quantity, min_stock_alert, base_price
            FROM vehicles 
            WHERE stock_quantity <= min_stock_alert 
            AND availability_status != 'discontinued'
            ORDER BY (stock_quantity / NULLIF(min_stock_alert, 0)) ASC
            LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $lowStockVehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $lowStockVehicles]);
}

function getVehicleCategories($pdo) {
    try {
        $sql = "SELECT DISTINCT category FROM vehicles WHERE category IS NOT NULL AND category != '' ORDER BY category";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode(['success' => true, 'data' => $categories]);
    } catch (Exception $e) {
        error_log("getVehicleCategories error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to load categories', 'debug' => $e->getMessage()]);
    }
}

function getVehicleStats($pdo) {
    try {
        $sql = "SELECT 
                    COUNT(*) as total_units,
                    COUNT(DISTINCT CONCAT(model_name, variant)) as models_in_stock,
                    COUNT(CASE WHEN stock_quantity <= min_stock_alert THEN 1 END) as low_stock_alerts,
                    COALESCE(SUM(base_price * stock_quantity), 0) as total_value
                FROM vehicles
                WHERE availability_status != 'discontinued'";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Ensure all values are properly formatted
        $stats['total_units'] = (int)$stats['total_units'];
        $stats['models_in_stock'] = (int)$stats['models_in_stock'];
        $stats['low_stock_alerts'] = (int)$stats['low_stock_alerts'];
        $stats['total_value'] = (float)$stats['total_value'];
        
        echo json_encode(['success' => true, 'data' => $stats]);
    } catch (Exception $e) {
        error_log("getVehicleStats error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to load stats', 'debug' => $e->getMessage()]);
    }
}

// Helper function to handle file uploads
function handleFileUpload($file, $uploadDir, $filePrefix) {
    // Ensure upload directory exists
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Get file extension
    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
    
    // Generate unique filename
    $fileName = $filePrefix . '_' . time() . '.' . $fileExtension;
    $filePath = $uploadDir . $fileName;
    
    // Move uploaded file to destination
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        // Return relative web path (not absolute filesystem path) for database storage
        // Convert to relative path from project root
        $relativePath = str_replace('../', '', $uploadDir) . $fileName;
        return $relativePath;
    }
    
    return false;
}

function getVehicleById($pdo, $id) {
    try {
        // Check if images should be included
        $includeImages = isset($_GET['include_images']) && $_GET['include_images'] === '1';
        
        if ($includeImages) {
            // Include image fields when requested
            $sql = "SELECT id, model_name, variant, year_model, category, engine_type, transmission, 
                           fuel_type, seating_capacity, key_features, base_price, promotional_price,
                           min_downpayment_percentage, financing_terms, color_options, popular_color,
                           stock_quantity, min_stock_alert, availability_status, expected_delivery_time,
                           main_image, additional_images, view_360_images, created_at, updated_at
                    FROM vehicles WHERE id = ?";
        } else {
            // Exclude LONGBLOB fields to prevent JSON encoding issues (default behavior)
            $sql = "SELECT id, model_name, variant, year_model, category, engine_type, transmission, 
                           fuel_type, seating_capacity, key_features, base_price, promotional_price,
                           min_downpayment_percentage, financing_terms, color_options, popular_color,
                           stock_quantity, min_stock_alert, availability_status, expected_delivery_time,
                           created_at, updated_at
                    FROM vehicles WHERE id = ?";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($vehicle) {
            // Clean string data for UTF-8 encoding
            $cleanVehicle = [];
            foreach ($vehicle as $key => $value) {
                if (is_string($value) && $value !== null) {
                    $cleanVehicle[$key] = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                } else {
                    $cleanVehicle[$key] = $value;
                }
            }
            echo json_encode(['success' => true, 'data' => $cleanVehicle], JSON_HEX_APOS | JSON_HEX_QUOT);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Vehicle not found']);
        }
    } catch (Exception $e) {
        error_log("getVehicleById error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to load vehicle', 'debug' => $e->getMessage()]);
    }
}

function createVehicle($pdo) {
    $requiredFields = ['model_name', 'variant', 'year_model', 'category', 'base_price', 'stock_quantity'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Field '$field' is required"]);
            return;
        }
    }
    try {
        // Handle file uploads - store as BLOBs
        $mainImage = null;
        $additionalImages = null;
        if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
            $mainImage = file_get_contents($_FILES['main_image']['tmp_name']);
        }
        if (isset($_FILES['additional_images']) && is_array($_FILES['additional_images']['tmp_name'])) {
            $images = [];
            foreach ($_FILES['additional_images']['tmp_name'] as $key => $tmpName) {
                if ($_FILES['additional_images']['error'][$key] === UPLOAD_ERR_OK) {
                    $images[] = file_get_contents($tmpName);
                }
            }
            if (!empty($images)) {
                $additionalImages = json_encode(array_map('base64_encode', $images));
            }
        }
        // Handle 360/3D images upload (direct upload without color mapping)
        $view360ImagesPaths = [];
        if (isset($_FILES['view_360_images']) && is_array($_FILES['view_360_images']['tmp_name'])) {
            foreach ($_FILES['view_360_images']['tmp_name'] as $key => $tmpName) {
                if ($_FILES['view_360_images']['error'][$key] === UPLOAD_ERR_OK) {
                    $file = [
                        'tmp_name' => $tmpName,
                        'name' => $_FILES['view_360_images']['name'][$key],
                        'type' => $_FILES['view_360_images']['type'][$key],
                        'size' => $_FILES['view_360_images']['size'][$key]
                    ];
                    // Determine upload directory based on file type
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $uploadDir = ($ext === 'glb' || $ext === 'gltf') 
                        ? '../uploads/3d_models/' 
                        : '../uploads/vehicle_images/360/';
                    $prefix = 'vehicle_new_360_' . $key;
                    $path = handleFileUpload($file, $uploadDir, $prefix);
                    if ($path) {
                        $view360ImagesPaths[] = $path;
                    }
                }
            }
        }
        
        // Handle color-specific 3D models (optional - takes precedence over generic uploads)
        $colorModelMap = [];
        if (isset($_FILES['color_model_files']) && is_array($_FILES['color_model_files']['tmp_name'])) {
            $colors = isset($_POST['color_model_colors']) && is_array($_POST['color_model_colors']) ? $_POST['color_model_colors'] : [];
            foreach ($_FILES['color_model_files']['tmp_name'] as $key => $tmpName) {
                if ($_FILES['color_model_files']['error'][$key] === UPLOAD_ERR_OK) {
                    $file = [
                        'tmp_name' => $tmpName,
                        'name' => $_FILES['color_model_files']['name'][$key],
                        'type' => $_FILES['color_model_files']['type'][$key],
                        'size' => $_FILES['color_model_files']['size'][$key]
                    ];
                    $color = isset($colors[$key]) ? trim($colors[$key]) : '';
                    $prefix = 'vehicle_new_color_' . preg_replace('/\s+/', '_', strtolower($color ?: 'unknown')) . '_' . $key;
                    $path = handleFileUpload($file, '../uploads/3d_models/', $prefix);
                    if ($path) {
                        $colorModelMap[] = ['color' => $color, 'model' => $path];
                    }
                }
            }
        }

        // Color-specific models take precedence over generic uploads
        $view360Json = null;
        if (!empty($colorModelMap)) {
            $view360Json = json_encode($colorModelMap);
        } elseif (!empty($view360ImagesPaths)) {
            $view360Json = json_encode($view360ImagesPaths);
        }

        $sql = "INSERT INTO vehicles (
            model_name, variant, year_model, category, engine_type, transmission, 
            fuel_type, seating_capacity, key_features, base_price, promotional_price,
            min_downpayment_percentage, financing_terms, color_options, popular_color,
            stock_quantity, min_stock_alert, availability_status, main_image, additional_images, view_360_images
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available', ?, ?, ?
        )";
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([
            $_POST['model_name'],
            $_POST['variant'],
            $_POST['year_model'],
            $_POST['category'],
            $_POST['engine_type'] ?? null,
            $_POST['transmission'] ?? null,
            $_POST['fuel_type'] ?? null,
            $_POST['seating_capacity'] ?? null,
            $_POST['key_features'] ?? null,
            $_POST['base_price'],
            $_POST['promotional_price'] ?? null,
            $_POST['min_downpayment_percentage'] ?? null,
            $_POST['financing_terms'] ?? null,
            $_POST['color_options'] ?? null,
            $_POST['popular_color'] ?? null,
            $_POST['stock_quantity'],
            $_POST['min_stock_alert'] ?? 5,
            $mainImage,
            $additionalImages,
            $view360Json
        ]);
        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Vehicle created successfully']);
        } else {
            throw new Exception('Failed to create vehicle');
        }
    } catch (PDOException $e) {
        ob_clean();
        error_log('PDO Error in createVehicle: ' . $e->getMessage());
        if (strpos($e->getMessage(), 'server has gone away') !== false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database connection lost during upload. Please try again with smaller files.']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    } catch (Exception $e) {
        ob_clean();
        error_log('Error in createVehicle: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateVehicle($pdo) {
    // Check if this is a FormData request (method override from POST)
    if (isset($_POST['_method']) && $_POST['_method'] === 'PUT') {
        $input = $_POST;
    } else {
        parse_str(file_get_contents('php://input'), $input);
    }
    
    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Vehicle ID is required']);
        return;
    }
    
    try {
        // Check if this is just a stock update
        if (isset($input['stock_quantity']) && count($input) == 2) {
            $sql = "UPDATE vehicles SET stock_quantity = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $success = $stmt->execute([$input['stock_quantity'], $input['id']]);
        } else {
            // Handle file uploads for FormData requests
            $mainImagePath = null;
            $additionalImagesPaths = [];
            $view360ImagesPaths = [];
            $updateImages = false;

            // Variable to store existing 3D models from hidden input
            $existingView360Images = null;

            // Create unique identifier for this vehicle's files
            $vehicleId = 'vehicle_' . $input['id'];

            if (isset($_POST['_method']) && $_POST['_method'] === 'PUT') {
                // Check if we should preserve existing 3D models
                if (isset($_POST['existing_view_360_images']) && !empty($_POST['existing_view_360_images'])) {
                    $existingView360Images = $_POST['existing_view_360_images'];
                }

                // Handle main image upload
                if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
                    $mainImagePath = handleFileUpload($_FILES['main_image'], '../uploads/vehicle_images/main/', $vehicleId . '_main_update');
                    $updateImages = true;
                }

                // Handle additional images upload
                if (isset($_FILES['additional_images']) && is_array($_FILES['additional_images']['tmp_name']) && !empty(array_filter($_FILES['additional_images']['tmp_name']))) {
                    foreach ($_FILES['additional_images']['tmp_name'] as $key => $tmpName) {
                        if ($_FILES['additional_images']['error'][$key] === UPLOAD_ERR_OK) {
                            $file = [
                                'tmp_name' => $tmpName,
                                'name' => $_FILES['additional_images']['name'][$key],
                                'type' => $_FILES['additional_images']['type'][$key],
                                'size' => $_FILES['additional_images']['size'][$key]
                            ];
                            $path = handleFileUpload($file, '../uploads/vehicle_images/additional/', $vehicleId . '_additional_update_' . $key);
                            if ($path) {
                                $additionalImagesPaths[] = $path;
                            }
                        }
                    }
                    if (!empty($additionalImagesPaths)) {
                        $updateImages = true;
                    }
                }

                // Handle 360/3D images upload
                if (isset($_FILES['view_360_images']) && is_array($_FILES['view_360_images']['tmp_name']) && !empty(array_filter($_FILES['view_360_images']['tmp_name']))) {
                    foreach ($_FILES['view_360_images']['tmp_name'] as $key => $tmpName) {
                        if ($_FILES['view_360_images']['error'][$key] === UPLOAD_ERR_OK) {
                            $file = [
                                'tmp_name' => $tmpName,
                                'name' => $_FILES['view_360_images']['name'][$key],
                                'type' => $_FILES['view_360_images']['type'][$key],
                                'size' => $_FILES['view_360_images']['size'][$key]
                            ];
                            // Determine upload directory based on file type (case-insensitive)
                            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                            $uploadDir = ($ext === 'glb' || $ext === 'gltf') 
                                ? '../uploads/3d_models/' 
                                : '../uploads/vehicle_images/360/';
                            $path = handleFileUpload($file, $uploadDir, $vehicleId . '_360_update_' . $key);
                            if ($path) {
                                $view360ImagesPaths[] = $path;
                            }
                        }
                    }
                    if (!empty($view360ImagesPaths)) {
                        $updateImages = true;
                    }
                }

                // Handle color-specific 3D models mapping
                if (isset($_FILES['color_model_files']) && is_array($_FILES['color_model_files']['tmp_name']) && !empty(array_filter($_FILES['color_model_files']['tmp_name']))) {
                    $colors = isset($_POST['color_model_colors']) && is_array($_POST['color_model_colors']) ? $_POST['color_model_colors'] : [];
                    $colorModelMap = [];
                    foreach ($_FILES['color_model_files']['tmp_name'] as $key => $tmpName) {
                        if ($_FILES['color_model_files']['error'][$key] === UPLOAD_ERR_OK) {
                            $file = [
                                'tmp_name' => $tmpName,
                                'name' => $_FILES['color_model_files']['name'][$key],
                                'type' => $_FILES['color_model_files']['type'][$key],
                                'size' => $_FILES['color_model_files']['size'][$key]
                            ];
                            $color = isset($colors[$key]) ? trim($colors[$key]) : '';
                            $prefix = $vehicleId . '_color_update_' . preg_replace('/\s+/', '_', strtolower($color ?: 'unknown')) . '_' . $key;
                            $path = handleFileUpload($file, '../uploads/3d_models/', $prefix);
                            if ($path) {
                                $colorModelMap[] = ['color' => $color, 'model' => $path];
                            }
                        }
                    }
                    if (!empty($colorModelMap)) {
                        // Replace generic list with explicit color mapping
                        $view360ImagesPaths = $colorModelMap;
                        $updateImages = true;
                    }
                }

                // If no new files uploaded but we have existing models, preserve them
                if (empty($view360ImagesPaths) && $existingView360Images !== null && !empty($existingView360Images)) {
                    // Decode existing models and use them
                    $decodedExisting = json_decode($existingView360Images, true);
                    if (json_last_error() === JSON_ERROR_NONE && !empty($decodedExisting)) {
                        $view360ImagesPaths = $decodedExisting;
                        // Set updateImages to true so we include this in the UPDATE query
                        $updateImages = true;
                    }
                }
            }
            
            // Full vehicle update
            if ($updateImages) {
                $sql = "UPDATE vehicles SET 
                            model_name = ?, variant = ?, year_model = ?, category = ?,
                            engine_type = ?, transmission = ?, fuel_type = ?, seating_capacity = ?,
                            key_features = ?, base_price = ?, promotional_price = ?,
                            min_downpayment_percentage = ?, financing_terms = ?, color_options = ?,
                            popular_color = ?, stock_quantity = ?, min_stock_alert = ?,
                            main_image = COALESCE(?, main_image), additional_images = COALESCE(?, additional_images), view_360_images = COALESCE(?, view_360_images)
                        WHERE id = ?";
                
                $stmt = $pdo->prepare($sql);
                $success = $stmt->execute([
                    $input['model_name'],
                    $input['variant'],
                    $input['year_model'],
                    $input['category'],
                    $input['engine_type'] ?? null,
                    $input['transmission'] ?? null,
                    $input['fuel_type'] ?? null,
                    $input['seating_capacity'] ?? null,
                    $input['key_features'] ?? null,
                    $input['base_price'],
                    $input['promotional_price'] ?? null,
                    $input['min_downpayment_percentage'] ?? null,
                    $input['financing_terms'] ?? null,
                    $input['color_options'] ?? null,
                    $input['popular_color'] ?? null,
                    $input['stock_quantity'],
                    $input['min_stock_alert'] ?? null,
                    $mainImagePath,
                    !empty($additionalImagesPaths) ? json_encode($additionalImagesPaths) : null,
                    !empty($view360ImagesPaths) ? json_encode($view360ImagesPaths) : null,
                    $input['id']
                ]);
            } else {
                $sql = "UPDATE vehicles SET 
                            model_name = ?, variant = ?, year_model = ?, category = ?,
                            engine_type = ?, transmission = ?, fuel_type = ?, seating_capacity = ?,
                            key_features = ?, base_price = ?, promotional_price = ?,
                            min_downpayment_percentage = ?, financing_terms = ?, color_options = ?,
                            popular_color = ?, stock_quantity = ?, min_stock_alert = ?
                        WHERE id = ?";
                
                $stmt = $pdo->prepare($sql);
                $success = $stmt->execute([
                    $input['modelName'] ?? $input['model_name'],
                    $input['variant'],
                    $input['yearModel'] ?? $input['year_model'],
                    $input['category'],
                    $input['engineType'] ?? $input['engine_type'],
                    $input['transmission'],
                    $input['fuelType'] ?? $input['fuel_type'],
                    $input['seatingCapacity'] ?? $input['seating_capacity'],
                    $input['keyFeatures'] ?? $input['key_features'],
                    $input['basePrice'] ?? $input['base_price'],
                    $input['promotionalPrice'] ?? $input['promotional_price'],
                    $input['minDownpayment'] ?? $input['min_downpayment_percentage'],
                    $input['financingTerms'] ?? $input['financing_terms'],
                    $input['colorOptions'] ?? $input['color_options'],
                    $input['popularColor'] ?? $input['popular_color'],
                    $input['stockQuantity'] ?? $input['stock_quantity'],
                    $input['minStockAlert'] ?? $input['min_stock_alert'],
                    $input['id']
                ]);
            }
        }
        
        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Vehicle updated successfully']);
        } else {
            throw new Exception('Failed to update vehicle');
        }
    } catch (PDOException $e) {
        ob_clean();
        error_log('PDO Error in updateVehicle: ' . $e->getMessage());
        if (strpos($e->getMessage(), 'server has gone away') !== false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database connection lost during upload. Please try again with smaller files.']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    } catch (Exception $e) {
        ob_clean();
        error_log('Error in updateVehicle: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteVehicle($pdo) {
    parse_str(file_get_contents('php://input'), $input);
    
    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Vehicle ID is required']);
        return;
    }
    
    try {
        $sql = "DELETE FROM vehicles WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([$input['id']]);
        
        if ($success) {
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Vehicle deleted successfully']);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Vehicle not found']);
            }
        } else {
            throw new Exception('Failed to delete vehicle');
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>