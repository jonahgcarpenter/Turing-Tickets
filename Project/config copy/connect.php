<?php
/**
 * Database Connection Configuration
 * 
 * This file establishes the connection to the MariaDB/MySQL database.
 * Setup Instructions:
 * 1. Create a new database for the project
 * 2. Create a database user with appropriate privileges
 * 3. Replace the placeholder values below with your actual database credentials
 * 
 * Required Configuration:
 * - servername: Your database host (usually 'localhost' for local development)
 * - username: Database user with necessary privileges
 * - password: Database user's password
 * - db: Name of the database
 * 
 * Security Note: Never commit actual credentials to version control.
 * Consider using environment variables for production deployment.
 */

$servername = "mariadb_server";    // Database host
$username = "mariadb_username";    // Database username
$password = "mariadb_password";    // Database password
$db = "yourdatabase";             // Database name

try {
    $conn = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection Failed: " . $e->getMessage());
}
?>