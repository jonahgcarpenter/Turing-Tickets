<?php
require_once('../config/database.php');

header('Content-Type: application/json');

// Log errors to the console
function logError($message) {
    error_log("PHP SQL Error: $message", 0);
}

try {
    $pdo = Database::dbConnect();
    $tickets = [];

    // Define main ticket queries
    $baseQueryOpen = "SELECT id, category AS request_type, title AS request_title, status, created_at AS updated FROM tickets WHERE status IN ('open', 'in-progress', 'awaiting response')";
    $baseQueryClosed = "SELECT id, category AS request_type, title AS request_title, status, created_at AS updated FROM closed_tickets WHERE status = 'closed'";

    // Filters and sorting
    $params = [];
    $search = isset($_GET['ticket_id']) ? trim($_GET['ticket_id']) : '';
    $statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
    $sort = isset($_GET['sort']) ? $_GET['sort'] : '';

    // Apply search filter
    if ($search !== '') {
        $baseQueryOpen .= " AND id = :ticket_id";
        $baseQueryClosed .= " AND id = :ticket_id";
        $params[':ticket_id'] = $search;
    }

    // Apply status filter
    if ($statusFilter !== '') {
        $baseQueryOpen .= " AND status = :status";
        $baseQueryClosed .= " AND status = :status";
        $params[':status'] = $statusFilter;
    }

    // Apply sorting
    if ($sort === 'Status') {
        $baseQueryOpen .= " ORDER BY status";
        $baseQueryClosed .= " ORDER BY status";
    } elseif ($sort === 'Updated') {
        $baseQueryOpen .= " ORDER BY created_at DESC";
        $baseQueryClosed .= " ORDER BY created_at DESC";
    }

    // Execute queries for open and closed tickets
    try {
        $stmtOpen = $pdo->prepare($baseQueryOpen);
        $stmtOpen->execute($params);
    } catch (PDOException $e) {
        logError("Error executing open tickets query: " . $e->getMessage());
        echo json_encode(['error' => 'Error fetching open tickets']);
        exit;
    }

    try {
        $stmtClosed = $pdo->prepare($baseQueryClosed);
        $stmtClosed->execute($params);
    } catch (PDOException $e) {
        logError("Error executing closed tickets query: " . $e->getMessage());
        echo json_encode(['error' => 'Error fetching closed tickets']);
        exit;
    }

    // Updated fetchNotes function to fetch responses based on ticket type
    function fetchNotes($pdo, $ticketId, $isClosed = false) {
        $tableName = $isClosed ? 'closed_responses' : 'responses';
        try {
            $stmt = $pdo->prepare("SELECT response AS content, created_at FROM $tableName WHERE ticket_id = :ticket_id ORDER BY created_at DESC");
            $stmt->execute([':ticket_id' => $ticketId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            logError("Error fetching responses for ticket $ticketId from $tableName: " . $e->getMessage());
            return [];
        }
    }

    // Fetch tickets and include responses with error logging
    foreach ($stmtOpen as $row) {
        $tickets[] = [
            'id' => $row['id'],
            'request_type' => $row['request_type'],
            'request_title' => $row['request_title'],
            'status' => $row['status'],
            'updated' => $row['updated'],
            'notes' => fetchNotes($pdo, $row['id']) // Fetch responses from 'responses' table for open tickets
        ];
    }

    foreach ($stmtClosed as $row) {
        $tickets[] = [
            'id' => $row['id'],
            'request_type' => $row['request_type'],
            'request_title' => $row['request_title'],
            'status' => $row['status'],
            'updated' => $row['updated'],
            'notes' => fetchNotes($pdo, $row['id'], true) // Fetch responses from 'closed_responses' table for closed tickets
        ];
    }

    echo json_encode($tickets);
} catch (PDOException $e) {
    logError("Database connection error: " . $e->getMessage());
    echo json_encode(['error' => 'Database connection error']);
}
?>
