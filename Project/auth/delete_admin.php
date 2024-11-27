<?php
require_once('../config/database.php');
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode([
        'error' => 'Unauthorized access',
        'redirect' => true,
        'redirectUrl' => '../html/admin_login.html',
        'message' => 'Please login first!'
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminId = $_POST['id'] ?? null;

    // Log the received ID for debugging
    error_log("Received admin ID for deletion: " . $adminId);

    // Validate input
    if (!$adminId) {
        echo json_encode(['success' => false, 'error' => 'Invalid admin ID.']);
        exit();
    }

    try {
        $pdo = Database::dbConnect();

        // Delete the admin with the specified ID
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id AND role = "admin"');
        $stmt->execute([':id' => $adminId]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Admin deleted successfully.']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Admin not found or already deleted.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error deleting admin: ' . $e->getMessage()]);
    }

    Database::dbDisconnect();
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
}
?>
