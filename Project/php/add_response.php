<?php
require_once('../config/database.php');

header('Content-Type: application/json');

try {
    $pdo = Database::dbConnect();
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['ticket_id']) || !isset($data['response'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input. Ticket ID and response are required.']);
    exit;
}

$ticket_id = intval($data['ticket_id']);
$response = trim($data['response']);

if (empty($response)) {
    echo json_encode(['success' => false, 'message' => 'Response cannot be empty.']);
    exit;
}

try {
    // Insert into the responses table with the correct column name 'response'
    $stmt = $pdo->prepare("INSERT INTO responses (ticket_id, response, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$ticket_id, $response]);

    echo json_encode(['success' => true, 'message' => 'Response added successfully.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to add response: ' . $e->getMessage()]);
}
