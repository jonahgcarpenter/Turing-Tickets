<?php
require_once('../config/database.php');
require_once('emails.php');

header('Content-Type: application/json');
$response = ['success' => true, 'messages' => []];

try {
    $pdo = Database::dbConnect();
} catch (PDOException $e) {
    $response['success'] = false;
    $response['messages'][] = 'Database connection failed: ' . $e->getMessage();
    echo json_encode($response);
    exit;
}

// Capture the incoming JSON payload
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($data['ticket_id']) || !isset($data['status'])) {
    $response['success'] = false;
    $response['messages'][] = 'Invalid input. Ticket ID and status are required.';
    echo json_encode($response);
    exit;
}

$ticket_id = intval($data['ticket_id']);
$status = trim($data['status']);

// Verify that the status is valid
$valid_statuses = ['open', 'in-progress', 'awaiting-response', 'closed'];
if (!in_array($status, $valid_statuses)) {
    $response['success'] = false;
    $response['messages'][] = 'Invalid status.';
    echo json_encode($response);
    exit;
}

try {
    $mailer = new MailHandler();
    
    // Get user email before any changes
    $stmt = $pdo->prepare("SELECT email FROM tickets WHERE id = ? UNION SELECT email FROM closed_tickets WHERE id = ?");
    $stmt->execute([$ticket_id, $ticket_id]);
    $userEmail = $stmt->fetchColumn();

    if ($status === 'closed') {
        // Move the ticket and responses to the closed tables
        $pdo->beginTransaction();

        // Move the ticket to closed_tickets with specific columns
        $stmt = $pdo->prepare("INSERT INTO closed_tickets (id, title, name, email, category, description, status, closed_date, created_at, updated_at)
                               SELECT id, title, name, email, category, description, 'closed', NOW(), created_at, updated_at FROM tickets WHERE id = ?");
        $stmt->execute([$ticket_id]);

        // Move associated responses to closed_responses
        $stmt = $pdo->prepare("INSERT INTO closed_responses (id, ticket_id, admin_id, response, created_at)
                               SELECT id, ticket_id, admin_id, response, created_at FROM responses WHERE ticket_id = ?");
        $stmt->execute([$ticket_id]);

        // Delete responses from the responses table
        $stmt = $pdo->prepare("DELETE FROM responses WHERE ticket_id = ?");
        $stmt->execute([$ticket_id]);

        // Delete the ticket from the tickets table
        $stmt = $pdo->prepare("DELETE FROM tickets WHERE id = ?");
        $stmt->execute([$ticket_id]);

        $pdo->commit();
        $response['messages'][] = 'Ticket closed and moved to closed_tickets with associated responses.';
        $mailer->sendStatusChangeNotification($userEmail, $ticket_id, 'closed');
    } else {
        // Check if the ticket is currently in the closed_tickets table
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM closed_tickets WHERE id = ?");
        $stmt->execute([$ticket_id]);
        $is_closed_ticket = $stmt->fetchColumn() > 0;

        if ($is_closed_ticket) {
            // Move the ticket and responses back to open tables
            $pdo->beginTransaction();

            // Insert the ticket back into tickets with the new status
            $stmt = $pdo->prepare("INSERT INTO tickets (id, title, name, email, category, description, status, created_at, updated_at)
                                   SELECT id, title, name, email, category, description, ?, created_at, NOW() FROM closed_tickets WHERE id = ?");
            $stmt->execute([$status, $ticket_id]);

            // Move associated responses back to responses
            $stmt = $pdo->prepare("INSERT INTO responses (id, ticket_id, admin_id, response, created_at)
                                   SELECT id, ticket_id, admin_id, response, created_at FROM closed_responses WHERE ticket_id = ?");
            $stmt->execute([$ticket_id]);

            // Delete responses from closed_responses
            $stmt = $pdo->prepare("DELETE FROM closed_responses WHERE ticket_id = ?");
            $stmt->execute([$ticket_id]);

            // Delete the ticket from closed_tickets
            $stmt = $pdo->prepare("DELETE FROM closed_tickets WHERE id = ?");
            $stmt->execute([$ticket_id]);

            $pdo->commit();
            $response['messages'][] = 'Ticket re-opened and moved to tickets with associated responses.';
        } else {
            // Update the status and updated_at in the tickets table for open, in-progress, and awaiting-response statuses
            $stmt = $pdo->prepare("UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $ticket_id]);

            $response['messages'][] = 'Ticket status updated successfully.';
        }
        $mailer->sendStatusChangeNotification($userEmail, $ticket_id, $status);
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['success'] = false;
    $response['messages'][] = 'Failed to update ticket status: ' . $e->getMessage();
}

echo json_encode($response);
