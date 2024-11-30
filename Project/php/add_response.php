<?php
require_once('../config/database.php');
require_once('emails.php');
session_start(); // Add session start

header('Content-Type: application/json');
$response = ['success' => true, 'message' => ''];

try {
    $pdo = Database::dbConnect();
    $mailer = new MailHandler($pdo);  // Create single mailer instance
} catch (PDOException $e) {
    $response['success'] = false;
    $response['message'] = 'Database connection failed: ' . $e->getMessage();
    echo json_encode($response);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['ticket_id']) || !isset($data['response'])) {
    $response['success'] = false;
    $response['message'] = 'Invalid input. Ticket ID and response are required.';
    echo json_encode($response);
    exit;
}

$ticket_id = intval($data['ticket_id']);
$response = trim($data['response']);

if (empty($responseText)) {
    $response['success'] = false;
    $response['message'] = 'Response cannot be empty.';
    echo json_encode($response);
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

echo json_encode($response);
