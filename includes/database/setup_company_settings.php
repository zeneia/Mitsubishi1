<?php
/**
 * Setup Company Settings Table
 * Run this file once to create the company_settings table and populate it with default values
 */

require_once __DIR__ . '/db_conn.php';

try {
    // Read and execute the SQL file
    $sql = file_get_contents(__DIR__ . '/company_settings.sql');
    
    // Split by semicolons to execute multiple statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $connect->exec($statement);
        }
    }
    
    echo "✓ Company settings table created successfully!<br>";
    echo "✓ Default company information has been populated.<br>";
    echo "<br>";
    echo "You can now edit company information from the Admin Settings page.<br>";
    echo "<a href='../../pages/main/settings.php'>Go to Settings</a>";
    
} catch (PDOException $e) {
    echo "Error setting up company settings table: " . $e->getMessage();
}
?>

