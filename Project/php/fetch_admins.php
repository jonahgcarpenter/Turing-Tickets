<?php
/**
 * Admin List Handler
 * Retrieves list of system administrators
 * Used for administrative interface displays
 * Security measures: Admin session verification
 * Jonah Carpenter - Turing Tickets
 */
require_once('../database/database.php');
session_start();
header('Content-Type: application/json');

// Verify admin authorization
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode([
        'error' => 'Unauthorized access',
        'redirect' => true,
        'redirectUrl' => '../html/admin_login.html',
        'message' => 'Please login first!'
    ]);
    exit();
}

try {
    // Fetch all admin users from database
    $pdo = Database::dbConnect();
    $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE role = 'admin'");
    $stmt->execute();
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return JSON response with admin list
    echo json_encode(['success' => true, 'admins' => $admins]);
} catch (Exception $e) {
    // Handle any errors during admin fetching
    echo json_encode(['success' => false, 'error' => 'Error fetching admins: ' . $e->getMessage()]);
}

// Close database connection
Database::dbDisconnect();
?>
