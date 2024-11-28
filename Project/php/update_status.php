<?php
require_once('../config/database.php');
require_once('emails.php');
session_start(); // Add session start

header('Content-Type: application/json');
$response = ['success' => true, 'messages' => []];

try {
    $pdo = Database::dbConnect();
    $mailer = new MailHandler($pdo);  // Update mailer initialization
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
    $pdo->beginTransaction();
    
    // Get complete ticket data and admin username
    $stmt = $pdo->prepare("
        SELECT t.*, u.username as admin_username, 
               (SELECT GROUP_CONCAT(r.response ORDER BY r.created_at DESC SEPARATOR '---')
                FROM responses r 
                WHERE r.ticket_id = t.id) as notes
        FROM (
            SELECT id, title, name, email, category, description, status, created_at, updated_at
            FROM tickets 
            WHERE id = ?
            UNION ALL
            SELECT id, title, name, email, category, description, status, created_at, updated_at
            FROM closed_tickets 
            WHERE id = ?
        ) t
        LEFT JOIN users u ON u.id = ?
    ");
    $stmt->execute([$ticket_id, $ticket_id, $_SESSION['admin_id']]);
    $ticketData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticketData) {
        throw new Exception('Ticket not found');
    }

    $userEmail = $ticketData['email'];
    $currentStatus = $ticketData['status'];
    $adminUsername = $ticketData['admin_username'];

    // Only proceed with status update if the status has actually changed
    if ($status === $currentStatus) {
        $response['message'] = 'Status unchanged.';
        echo json_encode($response);
        exit;
    }

    if ($status === 'closed') {
        // Get full ticket data before moving to closed_tickets
        $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
        $stmt->execute([$ticket_id]);
        $ticketData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticketData) {
            throw new Exception('Ticket not found');
        }

        // Move the ticket to closed_tickets with specific columns
        $stmt = $pdo->prepare("INSERT INTO closed_tickets (id, title, name, email, category, description, status, closed_date, created_at, updated_at)
                               SELECT id, title, name, email, category, description, 'closed', NOW(), created_at, NOW() FROM tickets WHERE id = ?");
        $stmt->execute([$ticket_id]);

        // Move associated responses to closed_responses
        $stmt = $pdo->prepare("INSERT INTO closed_responses (id, ticket_id, admin_id, response, created_at)
                               SELECT id, ticket_id, admin_id, response, created_at FROM responses WHERE ticket_id = ?");
        $stmt->execute([$ticket_id]);

        // Get the closure date from closed_tickets
        $stmt = $pdo->prepare("SELECT closed_date FROM closed_tickets WHERE id = ?");
        $stmt->execute([$ticket_id]);
        $closedDate = $stmt->fetchColumn();
        $ticketData['closed_date'] = $closedDate;

        // Delete responses from the responses table
        $stmt = $pdo->prepare("DELETE FROM responses WHERE ticket_id = ?");
        $stmt->execute([$ticket_id]);

        // Delete the ticket from the tickets table
        $stmt = $pdo->prepare("DELETE FROM tickets WHERE id = ?");
        $stmt->execute([$ticket_id]);

        if ($currentStatus !== 'closed') {
            $emailSent = $mailer->sendTicketClosureNotification($userEmail, $ticket_id, $ticketData);
        }
        
        $pdo->commit();
        
        $response['message'] = 'Ticket closed successfully.';
        if (!$emailSent) {
            $response['message'] .= ' Note: Status change email notification failed.';
        }
    } else {
        // Check if the ticket is currently in the closed_tickets table
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM closed_tickets WHERE id = ?");
        $stmt->execute([$ticket_id]);
        $is_closed_ticket = $stmt->fetchColumn() > 0;

        if ($is_closed_ticket) {
            // Move the ticket and responses back to open tables

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
            // Only update status and updated_at timestamp
            $stmt = $pdo->prepare("UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $ticket_id]);

            $response['messages'][] = 'Ticket status updated successfully.';
        }
        
        if ($status !== $currentStatus) {
            $emailSent = $mailer->sendStatusChangeNotification(
                $userEmail, 
                $ticket_id, 
                $status,
                $ticketData,
                $adminUsername
            );
        }
        
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
        
        $response['message'] = "Ticket status updated to '$status'.";
        if (!$emailSent) {
            $response['message'] .= ' Note: Status change email notification failed.';
        }
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['success'] = false;
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
