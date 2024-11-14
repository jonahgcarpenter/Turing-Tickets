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
$debugInfo = [];

if ($ticketId) {
    try {
        $pdo->beginTransaction();

        // Step 1: Move the ticket to `closed_tickets`
        $stmt = $pdo->prepare("INSERT INTO closed_tickets (id, title, name, email, category, description, status, closed_date, created_at, updated_at)
                               SELECT id, title, name, email, category, description, 'closed', NOW(), created_at, updated_at
                               FROM tickets WHERE id = :ticket_id");
        $stmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
        
        // Debugging the SQL Statement
        $debugInfo[] = "Executing: " . $stmt->queryString . " with ticket_id: " . $ticketId;

        if (!$stmt->execute()) {
            throw new Exception("Failed to move ticket to closed_tickets: " . implode(", ", $stmt->errorInfo()));
        }
        $debugInfo[] = "Ticket moved to closed_tickets successfully";

        // Step 2: Move associated responses to `closed_responses`
        $stmt = $pdo->prepare("INSERT INTO closed_responses (id, ticket_id, admin_id, response, created_at)
                               SELECT id, ticket_id, admin_id, response, created_at
                               FROM responses WHERE ticket_id = :ticket_id");
        $stmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
        
        // Debugging the SQL Statement
        $debugInfo[] = "Executing: " . $stmt->queryString . " with ticket_id: " . $ticketId;

        if (!$stmt->execute()) {
            throw new Exception("Failed to move responses to closed_responses: " . implode(", ", $stmt->errorInfo()));
        }
        $debugInfo[] = "Responses moved to closed_responses successfully";

        // Step 3: Verify that responses were actually inserted into `closed_responses`, if any exist
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM responses WHERE ticket_id = :ticket_id");
        $stmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
        $stmt->execute();
        $responseCount = $stmt->fetchColumn();

        if ($responseCount > 0) {
            // If there are responses, ensure they were moved to `closed_responses`
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM closed_responses WHERE ticket_id = :ticket_id");
            $stmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
            $stmt->execute();
            $closedResponseCount = $stmt->fetchColumn();

            if ($closedResponseCount == 0) {
                throw new Exception("Verification failed: No responses found in closed_responses after insertion.");
            }
            $debugInfo[] = "$closedResponseCount responses verified in closed_responses";
        } else {
            $debugInfo[] = "No responses found for this ticket, proceeding without moving responses.";
        }


        // Step 4: Delete associated responses from `responses`
        $stmt = $pdo->prepare("DELETE FROM responses WHERE ticket_id = :ticket_id");
        $stmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
        
        // Debugging the SQL Statement
        $debugInfo[] = "Executing: " . $stmt->queryString . " with ticket_id: " . $ticketId;

        if (!$stmt->execute()) {
            throw new Exception("Failed to delete responses from responses table: " . implode(", ", $stmt->errorInfo()));
        }
        $debugInfo[] = "Responses deleted from responses table successfully";

        // Step 5: Delete the ticket from `tickets`
        $stmt = $pdo->prepare("DELETE FROM tickets WHERE id = :ticket_id");
        $stmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
        
        // Debugging the SQL Statement
        $debugInfo[] = "Executing: " . $stmt->queryString . " with ticket_id: " . $ticketId;

        if (!$stmt->execute()) {
            throw new Exception("Failed to delete ticket from tickets table: " . implode(", ", $stmt->errorInfo()));
        }
        $debugInfo[] = "Ticket deleted from tickets table successfully";

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Ticket and responses moved to closed tables successfully', 'debug' => $debugInfo]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'debug' => $debugInfo]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid ticket ID']);
}
?>
