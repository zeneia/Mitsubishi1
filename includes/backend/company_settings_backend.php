<?php
/**
 * Company Settings Backend Handler
 * Handles CRUD operations for company settings
 */

require_once dirname(__DIR__) . '/database/db_conn.php';

header('Content-Type: application/json');

// Check if user is logged in and is Admin
session_start();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_company_settings':
            getCompanySettings($connect);
            break;
        
        case 'update_company_settings':
            updateCompanySettings($connect);
            break;
        
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Get all company settings
 */
function getCompanySettings($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM company_settings ORDER BY setting_key");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert to key-value pairs
        $settings = [];
        foreach ($results as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        echo json_encode(['success' => true, 'data' => $settings]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Update company settings
 */
function updateCompanySettings($pdo) {
    try {
        $userId = $_SESSION['user_id'] ?? null;
        
        // Get all POST data except 'action'
        $settings = $_POST;
        unset($settings['action']);
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Update each setting
        $stmt = $pdo->prepare("
            UPDATE company_settings 
            SET setting_value = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE setting_key = ?
        ");
        
        foreach ($settings as $key => $value) {
            $stmt->execute([$value, $userId, $key]);
        }
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Company settings updated successfully']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Get a single setting value
 */
function getSetting($pdo, $key) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM company_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['setting_value'] : null;
    } catch (PDOException $e) {
        return null;
    }
}

