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
    // Start transaction
    $pdo->beginTransaction();

    // First check if ticket exists in closed_tickets
    $stmt = $pdo->prepare("SELECT * FROM closed_tickets WHERE id = ?");
    $stmt->execute([$ticket_id]);
    $closedTicket = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($closedTicket) {
        // Move ticket back to active tickets
        $stmt = $pdo->prepare("INSERT INTO tickets (id, title, name, email, category, description, status, created_at, updated_at) 
                              SELECT id, title, name, email, category, description, 'open', created_at, NOW() 
                              FROM closed_tickets WHERE id = ?");
        $stmt->execute([$ticket_id]);

        // Move responses back
        $stmt = $pdo->prepare("INSERT INTO responses (id, ticket_id, admin_id, response, created_at)
                              SELECT id, ticket_id, admin_id, response, created_at
                              FROM closed_responses WHERE ticket_id = ?");
        $stmt->execute([$ticket_id]);

        // Delete from closed tables
        $stmt = $pdo->prepare("DELETE FROM closed_responses WHERE ticket_id = ?");
        $stmt->execute([$ticket_id]);
        
        $stmt = $pdo->prepare("DELETE FROM closed_tickets WHERE id = ?");
        $stmt->execute([$ticket_id]);
    }

    // Insert the new response
    $stmt = $pdo->prepare("INSERT INTO responses (ticket_id, response, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$ticket_id, $response]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Response added successfully.']);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Failed to add response: ' . $e->getMessage()]);
}
