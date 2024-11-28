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
$responseText = trim($data['response']);
$close_ticket = isset($data['close_ticket']) && $data['close_ticket'] === true;

if (empty($responseText)) {
    $response['success'] = false;
    $response['message'] = 'Response cannot be empty.';
    echo json_encode($response);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Get admin_id from session and verify it exists and is valid
    if (!isset($_SESSION['admin_id'])) {
        throw new Exception('Admin session not found');
    }
    $admin_id = $_SESSION['admin_id'];

    // Get complete ticket and admin data with responses
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
        WHERE u.role = 'admin'
    ");
    $stmt->execute([$ticket_id, $ticket_id, $admin_id]);
    $ticketData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticketData) {
        throw new Exception('Invalid ticket or admin credentials');
    }

    $userEmail = $ticketData['email'];
    $adminUsername = $ticketData['admin_username'];

    if ($close_ticket) {
        // First copy the ticket to closed_tickets
        $stmt = $pdo->prepare("INSERT INTO closed_tickets 
            (id, title, name, email, category, description, status, 
             closed_date, created_at, updated_at)
            SELECT id, title, name, email, category, description,
                'closed' as status, NOW(), created_at, NOW()
            FROM tickets WHERE id = ?");
        $stmt->execute([$ticket_id]);

        // Copy existing responses to closed_responses
        $stmt = $pdo->prepare("INSERT INTO closed_responses 
            (id, ticket_id, admin_id, response, created_at)
            SELECT id, ticket_id, admin_id, response, created_at 
            FROM responses WHERE ticket_id = ?");
        $stmt->execute([$ticket_id]);

        // Add the new response directly to closed_responses
        $stmt = $pdo->prepare("INSERT INTO closed_responses 
            (ticket_id, admin_id, response, created_at) 
            VALUES (?, ?, ?, NOW())");
        $stmt->execute([$ticket_id, $admin_id, $responseText]);

        // Clean up active tables
        $stmt = $pdo->prepare("DELETE FROM responses WHERE ticket_id = ?");
        $stmt->execute([$ticket_id]);
        
        $stmt = $pdo->prepare("DELETE FROM tickets WHERE id = ?");
        $stmt->execute([$ticket_id]);

        // Send closure notification
        $emailSent = $mailer->sendTicketClosureNotification($userEmail, $ticket_id, $ticketData);
        $response['message'] = 'Response added and ticket closed successfully.';
    } else {
        // Check if ticket exists in closed_tickets
        $stmt = $pdo->prepare("SELECT * FROM closed_tickets WHERE id = ?");
        $stmt->execute([$ticket_id]);
        $isClosedTicket = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($isClosedTicket) {
            // First move the ticket to active
            $stmt = $pdo->prepare("INSERT INTO tickets 
                (id, title, name, email, category, description, status, created_at, updated_at)
                SELECT id, title, name, email, category, description, 
                    'open' as status, created_at, NOW() 
                FROM closed_tickets WHERE id = ?");
            $stmt->execute([$ticket_id]);

            // Move existing responses
            $stmt = $pdo->prepare("INSERT INTO responses 
                (id, ticket_id, admin_id, response, created_at)
                SELECT id, ticket_id, admin_id, response, created_at
                FROM closed_responses WHERE ticket_id = ?");
            $stmt->execute([$ticket_id]);

            // Now add the new response to active responses
            $stmt = $pdo->prepare("INSERT INTO responses 
                (ticket_id, admin_id, response, created_at) 
                VALUES (?, ?, ?, NOW())");
            $stmt->execute([$ticket_id, $admin_id, $responseText]);

            // Clean up closed tables
            $stmt = $pdo->prepare("DELETE FROM closed_responses WHERE ticket_id = ?");
            $stmt->execute([$ticket_id]);
            
            $stmt = $pdo->prepare("DELETE FROM closed_tickets WHERE id = ?");
            $stmt->execute([$ticket_id]);

            $emailSent = $mailer->sendTicketReopenedNotification($userEmail, $ticket_id, $ticketData);
            $response['message'] = 'Ticket reopened with new response successfully.';
        } else {
            // Simple response to active ticket
            $stmt = $pdo->prepare("INSERT INTO responses 
                (ticket_id, admin_id, response, created_at) 
                VALUES (?, ?, ?, NOW())");
            $stmt->execute([$ticket_id, $admin_id, $responseText]);

            // Update the ticket timestamp
            $stmt = $pdo->prepare("UPDATE tickets SET updated_at = NOW() WHERE id = ?");
            $stmt->execute([$ticket_id]);
            
            $emailSent = $mailer->sendResponseNotification($userEmail, $ticket_id, $responseText, $ticketData, $adminUsername);
            $response['message'] = 'Response added successfully.';
        }
    }

    if (!$emailSent) {
        $response['message'] .= ' Note: Notification email could not be sent.';
    }

    $pdo->commit();
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['success'] = false;
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
