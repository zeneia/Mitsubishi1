<?php
/**
 * Database migration script for client management functionality
 * Run this script once to create the necessary tables
 */

require_once(dirname(__DIR__) . '/includes/init.php');

try {
    echo "Starting client management database migration...\n";
    
    // Read the SQL file
    $sqlFile = __DIR__ . '/create_client_management_tables.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Split SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $pdo->beginTransaction();
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            echo "Executing: " . substr($statement, 0, 50) . "...\n";
            $pdo->exec($statement);
        }
    }
    
    $pdo->commit();
    
    echo "Migration completed successfully!\n";
    echo "Created tables:\n";
    echo "- client_reassignments\n";
    echo "- client_escalations\n";
    echo "- client_archives\n";
    echo "- Added status column to customer_information\n";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
