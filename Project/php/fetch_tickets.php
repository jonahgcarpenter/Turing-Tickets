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
    $tickets = [];

    // Get parameters from the request
    $filterOption = isset($_GET['filterOption']) ? $_GET['filterOption'] : 'all';
    $sortOption = isset($_GET['sort']) ? $_GET['sort'] : null;
    $ticketId = isset($_GET['ticket_id']) ? $_GET['ticket_id'] : null;

    // Convert underscores to hyphens to match the database values
    $filterOption = str_replace('_', '-', $filterOption);

    // Initialize the query and parameters
    $query = '';
    $params = [];

    // Check if ticket_id is provided to override filterOption
    if ($ticketId) {
        // Query both open and closed tickets by ticket_id
        $query = "(
            SELECT id, category AS request_type, title AS request_title, status, created_at AS updated 
            FROM tickets WHERE id = :ticket_id
        ) UNION (
            SELECT id, category AS request_type, title AS request_title, status, created_at AS updated 
            FROM closed_tickets WHERE id = :ticket_id
        )";
        $params[':ticket_id'] = $ticketId;
    } else {
        // Build query based on filterOption if ticket_id is not provided
        $baseQueryOpen = "SELECT id, category AS request_type, title AS request_title, status, created_at AS updated FROM tickets";
        $baseQueryClosed = "SELECT id, category AS request_type, title AS request_title, status, created_at AS updated FROM closed_tickets WHERE status = 'closed'";

        switch ($filterOption) {
            case 'open':
                $query = $baseQueryOpen . " WHERE status = 'open'";
                break;
            case 'in-progress':
                $query = $baseQueryOpen . " WHERE status = 'in-progress'";
                break;
            case 'awaiting-response':
                $query = $baseQueryOpen . " WHERE status = 'awaiting-response'";
                break;
            case 'closed':
                $query = $baseQueryClosed;
                break;
            case 'all':
            default:
                $query = $baseQueryOpen . " WHERE status IN ('open', 'in-progress', 'awaiting-response')";
                break;
        }
    }

    // Apply sorting
    if ($sortOption) {
        switch ($sortOption) {
            case 'status':
                $query .= " ORDER BY status";
                break;
            case 'updated-asc':
                $query .= " ORDER BY updated ASC";
                break;
            case 'updated-desc':
                $query .= " ORDER BY updated DESC";
                break;
        }
    }

    // Execute the main query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch notes for each ticket and include responses
    $result = [];
    foreach ($tickets as $row) {
        $isClosed = ($row['status'] === 'closed');
        $result[] = [
            'id' => $row['id'],
            'request_type' => $row['request_type'],
            'request_title' => $row['request_title'],
            'status' => $row['status'],
            'updated' => $row['updated'],
            'notes' => fetchNotes($pdo, $row['id'], $isClosed)
        ];
    }

    echo json_encode($result);

} catch (PDOException $e) {
    logError("Database connection error: " . $e->getMessage());
    echo json_encode(['error' => 'Database connection error']);
}

?>