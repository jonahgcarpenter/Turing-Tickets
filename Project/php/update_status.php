<?php
require_once('../config/database.php');

header('Content-Type: application/json');

try {
    $pdo = Database::dbConnect();
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Capture the incoming JSON payload
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($data['ticket_id']) || !isset($data['status'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input. Ticket ID and status are required.']);
    exit;
}

$ticket_id = intval($data['ticket_id']);
$status = trim($data['status']);

// Verify that the status is valid
$valid_statuses = ['open', 'in-progress', 'awaiting-response', 'closed'];
if (!in_array($status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status.']);
    exit;
}

try {
  if ($status === 'closed') {
      // Move the ticket and responses to the closed tables
      $pdo->beginTransaction();

      // Move the ticket to closed_tickets with specific columns first
      $stmt = $pdo->prepare("INSERT INTO closed_tickets (id, title, name, email, category, description, status, closed_date, created_at, updated_at)
                             SELECT id, title, name, email, category, description, 'closed', NOW(), created_at, updated_at FROM tickets WHERE id = ?");
      $stmt->execute([$ticket_id]);

      // Move associated responses to closed_responses
      $stmt = $pdo->prepare("INSERT INTO closed_responses (id, ticket_id, admin_id, response, created_at)
                             SELECT id, ticket_id, admin_id, response, created_at FROM responses WHERE ticket_id = ?");
      $stmt->execute([$ticket_id]);

      // Now delete responses from the responses table
      $stmt = $pdo->prepare("DELETE FROM responses WHERE ticket_id = ?");
      $stmt->execute([$ticket_id]);

      // Finally, delete the ticket from the tickets table
      $stmt = $pdo->prepare("DELETE FROM tickets WHERE id = ?");
      $stmt->execute([$ticket_id]);

      $pdo->commit();
      echo json_encode(['success' => true, 'message' => 'Ticket closed and moved to closed_tickets with associated responses.']);
  } else {
      // Check if the ticket is currently in the closed_tickets table
      $stmt = $pdo->prepare("SELECT COUNT(*) FROM closed_tickets WHERE id = ?");
      $stmt->execute([$ticket_id]);
      $is_closed_ticket = $stmt->fetchColumn() > 0;

      if ($is_closed_ticket) {
          // Move the ticket and responses back to open tables
          $pdo->beginTransaction();

          // Insert the ticket back into tickets with the new status first
          $stmt = $pdo->prepare("INSERT INTO tickets (id, title, name, email, category, description, status, created_at, updated_at)
                                 SELECT id, title, name, email, category, description, ?, created_at, updated_at FROM closed_tickets WHERE id = ?");
          $stmt->execute([$status, $ticket_id]);

          // Move associated responses back to responses
          $stmt = $pdo->prepare("INSERT INTO responses (id, ticket_id, admin_id, response, created_at)
                                 SELECT id, ticket_id, admin_id, response, created_at FROM closed_responses WHERE ticket_id = ?");
          $stmt->execute([$ticket_id]);

          // Delete responses from closed_responses
          $stmt = $pdo->prepare("DELETE FROM closed_responses WHERE ticket_id = ?");
          $stmt->execute([$ticket_id]);

          // Finally, delete the ticket from closed_tickets
          $stmt = $pdo->prepare("DELETE FROM closed_tickets WHERE id = ?");
          $stmt->execute([$ticket_id]);

          $pdo->commit();
          echo json_encode(['success' => true, 'message' => 'Ticket re-opened and moved to tickets with associated responses.']);
      } else {
          // Update the status in the tickets table for open, in-progress, and awaiting-response statuses
          $stmt = $pdo->prepare("UPDATE tickets SET status = ? WHERE id = ?");
          $stmt->execute([$status, $ticket_id]);

          echo json_encode(['success' => true, 'message' => 'Ticket status updated successfully.']);
      }
  }
} catch (PDOException $e) {
  if ($pdo->inTransaction()) {
      $pdo->rollBack(); // Roll back if there's any error
  }
  echo json_encode(['success' => false, 'message' => 'Failed to update ticket status: ' . $e->getMessage()]);
}