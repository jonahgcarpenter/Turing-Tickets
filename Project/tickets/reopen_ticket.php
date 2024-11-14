<?php
require_once './config/database.php';
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$pdo = Database::dbConnect();
$data = json_decode(file_get_contents('php://input'), true);
$ticketId = $data['ticket_id'] ?? null;

if ($ticketId) {
    try {
        // Check if the ticket is in `closed_tickets` or `tickets`
        $stmt = $pdo->prepare("SELECT 'closed' AS table_name FROM closed_tickets WHERE id = :ticket_id
                               UNION
                               SELECT 'active' AS table_name FROM tickets WHERE id = :ticket_id");
        $stmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            echo json_encode(['success' => false, 'message' => 'Ticket not found']);
            exit;
        }

        $tableName = $result['table_name'];

        if ($tableName === 'closed') {
            $pdo->beginTransaction();

            // Step 1: Move the ticket back to `tickets`
            $stmt = $pdo->prepare("INSERT INTO tickets (id, title, name, email, category, description, status, created_at, updated_at)
                                   SELECT id, title, name, email, category, description, 'open', created_at, NOW()
                                   FROM closed_tickets WHERE id = :ticket_id");
            $stmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
            $stmt->execute();

            // Step 2: Move associated responses back to `responses`
            $stmt = $pdo->prepare("INSERT INTO responses (id, ticket_id, admin_id, response, created_at)
                                   SELECT id, ticket_id, admin_id, response, created_at
                                   FROM closed_responses WHERE ticket_id = :ticket_id");
            $stmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
            $stmt->execute();

            // Step 3: Delete associated responses from `closed_responses`
            $stmt = $pdo->prepare("DELETE FROM closed_responses WHERE ticket_id = :ticket_id");
            $stmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
            $stmt->execute();

            // Step 4: Delete the ticket from `closed_tickets`
            $stmt = $pdo->prepare("DELETE FROM closed_tickets WHERE id = :ticket_id");
            $stmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
            $stmt->execute();

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Ticket and responses moved back to active tables successfully']);
        } else {
            // The ticket is already in `tickets`, so just update the status
            $stmt = $pdo->prepare("UPDATE tickets SET status = 'open', updated_at = NOW() WHERE id = :ticket_id");
            $stmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
            $stmt->execute();

            echo json_encode(['success' => true, 'message' => 'Ticket status updated successfully']);
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid ticket ID']);
}

?>
