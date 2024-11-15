<?php
require_once('../config/database.php');

header('Content-Type: application/json');

try {
    $pdo = Database::dbConnect();
    $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE role = 'admin'");
    $stmt->execute();
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'admins' => $admins]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error fetching admins: ' . $e->getMessage()]);
}

Database::dbDisconnect();
?>
