<?php
/**
 * Admin Dashboard Authentication Check
 * Verifies admin session status for dashboard access
 * Security measures: Session validation, JSON response handling
 * Jonah Carpenter - Turing Tickets
 */

// Prevent output buffering issues and configure error handling
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
error_log('Checking session data in admin_dash_check: ' . print_r($_SESSION, true));

ob_clean();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        throw new Exception('Unauthorized');
    }
    
    $username = isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : 'Admin';
    error_log('Username being sent: ' . $username); // Debug log
    
    $response = [
        'success' => true,
        'username' => $username
    ];
    error_log('Sending response: ' . print_r($response, true)); // Debug log
    exit(json_encode($response));
} catch (Exception $e) {
    exit(json_encode([
        'error' => 'Unauthorized access',
        'redirect' => true,
        'redirectUrl' => '../html/admin_login.html',
        'message' => 'Please login first!'
    ]));
}
?>