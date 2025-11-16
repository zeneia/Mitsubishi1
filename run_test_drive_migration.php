<?php
/**
 * Migration Script: Add approved_by column to test_drive_requests table
 * 
 * This script adds the approved_by column to track who approved each test drive request.
 * It's safe to run multiple times - it will check if the column already exists.
 */

// Include database connection
include_once('includes/database/db_conn.php');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Test Drive Migration</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { color: blue; background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Test Drive Migration Script</h1>
    <p>This script will add the <code>approved_by</code> column to the <code>test_drive_requests</code> table.</p>
";

try {
    // Read the SQL file
    $sqlFile = 'includes/database/add_approved_by_to_test_drive.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    echo "<div class='info'><strong>Step 1:</strong> Reading SQL file... ✓</div>";
    
    // Execute the SQL
    echo "<div class='info'><strong>Step 2:</strong> Executing migration...</div>";
    
    // Split by semicolon and execute each statement
    $statements = explode(';', $sql);
    $executedCount = 0;
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $connect->exec($statement);
                $executedCount++;
            } catch (PDOException $e) {
                // Some statements might fail if already executed, that's okay
                if (strpos($e->getMessage(), 'Duplicate') === false) {
                    echo "<div class='error'>Warning: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
            }
        }
    }
    
    echo "<div class='success'><strong>Step 2:</strong> Executed $executedCount SQL statements ✓</div>";
    
    // Verify the column was added
    echo "<div class='info'><strong>Step 3:</strong> Verifying column exists...</div>";
    
    $stmt = $connect->query("DESCRIBE test_drive_requests");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $approvedByExists = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'approved_by') {
            $approvedByExists = true;
            break;
        }
    }
    
    if ($approvedByExists) {
        echo "<div class='success'><strong>Step 3:</strong> Column 'approved_by' exists ✓</div>";
        echo "<div class='success'><h2>✓ Migration Completed Successfully!</h2></div>";
        
        // Show table structure
        echo "<h3>Updated Table Structure:</h3>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($columns as $column) {
            $highlight = ($column['Field'] === 'approved_by') ? "style='background: #d4edda;'" : "";
            echo "<tr $highlight>";
            echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } else {
        echo "<div class='error'><strong>Step 3:</strong> Column 'approved_by' was not created ✗</div>";
        echo "<div class='error'><h2>Migration Failed</h2><p>Please check the error messages above.</p></div>";
    }
    
    echo "<div class='info'><h3>Next Steps:</h3>
    <ol>
        <li>Test the gatepass PDF by approving a test drive request</li>
        <li>View the gatepass to verify all fields are showing correctly</li>
        <li>Use <a href='debug_test_drive_data.php?request_id=57'>debug_test_drive_data.php</a> to inspect data</li>
    </ol>
    </div>";
    
} catch (Exception $e) {
    echo "<div class='error'><h2>Error</h2><p>" . htmlspecialchars($e->getMessage()) . "</p></div>";
    echo "<div class='error'><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre></div>";
}

echo "</body></html>";
?>

