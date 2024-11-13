<?php
require_once 'database.php';
session_start();

// Check if the admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$pdo = Database::dbConnect();

try {
    // Get data from POST request
    $data = json_decode(file_get_contents('php://input'), true);
    $ticketId = $data['ticket_id'];
    $responseText = $data['response'];

    // Insert a new response into the responses table
    $stmt = $pdo->prepare("INSERT INTO responses (ticket_id, response, created_at) VALUES (:ticket_id, :response, NOW())");
    $stmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
    $stmt->bindParam(':response', $responseText, PDO::PARAM_STR);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Response added successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

Database::dbDisconnect();
