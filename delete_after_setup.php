<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set proper content type
header('Content-Type: text/plain');

require_once './Project/config/connect.php';

try {
    // Read the SQL file
    $sql = file_get_contents('./Project/database/database.sql');
    
    if ($sql === false) {
        error_log('Failed to read database.sql file');
        throw new Exception('Error reading database.sql file');
    }

    // Split the SQL file into individual statements
    $statements = array_filter(
        array_map('trim', 
            explode(';', $sql)
        )
    );

    // Execute each statement separately
    foreach ($statements as $statement) {
        if (empty($statement)) continue;
        
        try {
            $conn->exec($statement);
            echo "Successfully executed: " . substr($statement, 0, 50) . "...\n";
        } catch (PDOException $e) {
            echo "Error executing: " . substr($statement, 0, 50) . "...\n";
            echo "Error message: " . $e->getMessage() . "\n";
            error_log('Database Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    echo "\nDatabase setup completed successfully!\n";
    
} catch (PDOException $e) {
    error_log('Database Error: ' . $e->getMessage());
    die("\nDatabase Error: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    error_log('Setup Error: ' . $e->getMessage());
    die("\nSetup failed: " . $e->getMessage() . "\n");
}
?>
