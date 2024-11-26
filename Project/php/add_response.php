<?php
require_once('../config/database.php');
require_once('emails.php');

header('Content-Type: application/json');
$response = ['success' => true, 'message' => ''];

try {
    $pdo = Database::dbConnect();
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
$responseText = trim($data['response']);

if (empty($responseText)) {
    $response['success'] = false;
    $response['message'] = 'Response cannot be empty.';
    echo json_encode($response);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Get user email
    $stmt = $pdo->prepare("SELECT email FROM tickets WHERE id = ? UNION SELECT email FROM closed_tickets WHERE id = ?");
    $stmt->execute([$ticket_id, $ticket_id]);
    $userEmail = $stmt->fetchColumn();

    if (!$userEmail) {
        throw new Exception('Ticket not found');
    }

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

        // Send specialized reopening notification instead of regular response notification
        $mailer = new MailHandler();
        $emailSent = $mailer->sendTicketReopenedNotification($userEmail, $ticket_id, $responseText);
        
        $response['message'] = 'Ticket reopened successfully.';
        if (!$emailSent) {
            $response['message'] .= ' Note: Reopening notification email could not be sent.';
        }
    } else {
        // Insert the new response
        $stmt = $pdo->prepare("INSERT INTO responses (ticket_id, response, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$ticket_id, $responseText]);

        // Send email notification
        $mailer = new MailHandler();
        $emailSent = $mailer->sendResponseNotification($userEmail, $ticket_id, $responseText);

        $response['message'] = 'Response added successfully.';
        if (!$emailSent) {
            $response['message'] .= ' Note: Response notification email could not be sent.';
        }
    }

    if ($pdo->inTransaction()) {
        $pdo->commit();
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['success'] = false;
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
