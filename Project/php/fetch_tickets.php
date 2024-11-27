<?php
require_once('../config/database.php');

header('Content-Type: application/json');

// Log errors to the console
function logError($message) {
    error_log("PHP SQL Error: $message", 0);
}

// Function to fetch responses for tickets
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

try {
    $pdo = Database::dbConnect();
    
    // Base queries with consistent column names
    $baseQueryOpen = "SELECT id, category AS request_type, title AS request_title, 
                             status, created_at, updated_at, name, email, description 
                      FROM tickets";
    $baseQueryClosed = "SELECT id, category AS request_type, title AS request_title, 
                               status, created_at, updated_at, name, email, description 
                        FROM closed_tickets";

    // Get parameters
    $filterOption = isset($_GET['filterOption']) ? $_GET['filterOption'] : 'all';
    $sortOption = isset($_GET['sort']) ? $_GET['sort'] : 'status';
    $ticketId = isset($_GET['ticket_id']) ? $_GET['ticket_id'] : null;

    if ($ticketId) {
        // When searching by ID, check both tables
        $query = "($baseQueryOpen WHERE id = :ticket_id) 
                 UNION 
                 ($baseQueryClosed WHERE id = :ticket_id)";
        $params = [':ticket_id' => $ticketId];
    } else {
        // Handle filtering
        switch ($filterOption) {
            case 'open':
                $query = "$baseQueryOpen WHERE status = 'open'";
                $params = [];
                break;
            case 'in-progress':
                $query = "$baseQueryOpen WHERE status = 'in-progress'";
                $params = [];
                break;
            case 'awaiting-response':
                $query = "$baseQueryOpen WHERE status = 'awaiting-response'";
                $params = [];
                break;
            case 'closed':
                // Only query the closed_tickets table for closed tickets
                $query = $baseQueryClosed;
                $params = [];
                break;
            default: // 'all'
                // For 'all', only show non-closed tickets from tickets table
                $query = "$baseQueryOpen WHERE status != 'closed'";
                $params = [];
        }

        // Handle sorting
        switch ($sortOption) {
            case 'updated-asc':
                $query .= " ORDER BY updated_at ASC";
                break;
            case 'updated-desc':
                $query .= " ORDER BY updated_at DESC";
                break;
            default:
                $query .= " ORDER BY status";
        }
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch notes for each ticket
    $result = array_map(function($ticket) use ($pdo) {
        $ticket['notes'] = fetchNotes($pdo, $ticket['id'], $ticket['status'] === 'closed');
        return $ticket;
    }, $tickets);

    echo json_encode($result);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['error' => 'Database connection error']);
}

?>