<?php
// Initialize the session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
$db_path = __DIR__ . '/database/db_conn.php';
if (file_exists($db_path)) {
    include_once($db_path);
    // Use the existing $connect variable from db_conn.php
    $pdo = $connect ?? null;
} else {
    $pdo = null;
    error_log("Database connection file not found: " . $db_path);
}

// Make database connection available globally
$GLOBALS['pdo'] = $pdo;


// Define user role variable for use in all pages
$user_role = $_SESSION['user_role'] ?? null;

// Set application-wide constants and configurations
define('APP_NAME', 'Mitsubishi Dealership System');
define('APP_VERSION', '1.0.0');

// Default timezone setting
date_default_timezone_set('Asia/Manila'); // Set appropriate timezone for Philippines

// Error reporting settings (turn off in production)
if ($_SERVER['SERVER_NAME'] == 'localhost') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to check if user has specified role
function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

// Function to redirect with a message
function redirectWithMessage($location, $message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header("Location: $location");
    exit();
}

// Global function to include phone validation script
function includePhoneValidation() {
    ?>
    <script>
    document.addEventListener("DOMContentLoaded", () => {
        
        const restrictToNumbers = (input) => {
            // Skip if input already has phone formatting (like test_drive.php)
            const form = input.closest('form');
            if (form && (form.id?.includes('testdrive') || form.action?.includes('test_drive'))) {
                return;
            }
            
            // Skip if placeholder suggests formatting
            if (input.placeholder && input.placeholder.includes('(') && input.placeholder.includes(')')) {
                return;
            }
            
            // Remove non-digits
            const original = input.value;
            input.value = input.value.replace(/\D/g, '');
            
            // Restore cursor if changed
            if (original !== input.value) {
                const pos = input.selectionStart;
                const removed = original.length - input.value.length;
                input.setSelectionRange(Math.max(0, pos - removed), Math.max(0, pos - removed));
            }
        };

        const applyPhoneValidation = () => {
            const inputs = document.querySelectorAll('input[type="tel"], input[name*="phone"], input[name*="mobile"]');
            inputs.forEach(input => {
                if (!input.hasAttribute('data-phone-validated')) {
                    input.setAttribute('data-phone-validated', 'true');
                    restrictToNumbers(input);
                    input.addEventListener('input', () => restrictToNumbers(input));
                    input.addEventListener('paste', () => setTimeout(() => restrictToNumbers(input), 0));
                    if (input.value) restrictToNumbers(input);
                }
            });
        };

        // Apply on load
        applyPhoneValidation();
        
        // Watch for new inputs
        const observer = new MutationObserver((mutations) => {
            let needsUpdate = false;
            mutations.forEach(mutation => {
                mutation.addedNodes.forEach(node => {
                    if (node.nodeType === 1) { // Element node
                        if (node.matches?.('input[type="tel"], input[name*="phone"], input[name*="mobile"]') ||
                            node.querySelector?.('input[type="tel"], input[name*="phone"], input[name*="mobile"]')) {
                            needsUpdate = true;
                        }
                    }
                });
            });
            if (needsUpdate) setTimeout(applyPhoneValidation, 0);
        });

        observer.observe(document.body, { childList: true, subtree: true });
    });
    </script>
    <?php
}