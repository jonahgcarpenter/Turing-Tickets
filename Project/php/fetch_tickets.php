<?php
/*
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

    // Query for all open, in-progress, and awaiting-response statuses
    $QueryAll = "SELECT id, category AS request_type, title AS request_title, status, created_at AS updated FROM tickets WHERE status IN ('open', 'in-progress', 'awaiting-response')";

    // Query for closed tickets
    $QueryClosed = "SELECT id, category AS request_type, title AS request_title, status, created_at AS updated FROM closed_tickets WHERE status = 'closed'";

    // Query for open tickets
    $QueryOpen = "SELECT id, category AS request_type, title AS request_title, status, created_at AS updated FROM tickets WHERE status = 'open'";

    // Query for in-progress tickets
    $QueryInProgress = "SELECT id, category AS request_type, title AS request_title, status, created_at AS updated FROM tickets WHERE status = 'in-progress'";

    // Query for awaiting-response tickets
    $QueryAwaitingResponse = "SELECT id, category AS request_type, title AS request_title, status created_at AS updated FROM tickets WHERE status = 'awaiting-response'";


    // Filters and sorting
    $params = [];
    $search = isset($_GET['ticket_id']) ? trim($_GET['ticket_id']) : '';
    $statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
    $sort = isset($_GET['sort']) ? $_GET['sort'] : '';

    // Apply search filter
    if ($search !== '') {
        $QueryAll .= " AND id = :ticket_id";
        $QueryClosed .= " AND id = :ticket_id";
        $params[':ticket_id'] = $search;
    }

    // Get the filter option from the query parameters
    $filterOption = $_GET['filterOption'] ?? '';

    // Build the query based on the filter option
    if ($filterOption === 'open') {
        $query = $QueryOpen;
    } elseif ($filterOption === 'in-progress') {
        $query = $QueryInProgress;
    } elseif ($filterOption === 'awaiting-response') {
        $query = $QueryAwaitingResponse;
    } elseif ($filterOption === 'closed') {
        $query = $QueryClosed;
    } elseif ($filterOption === 'all') {
        $query = $QueryAll;
    }

    // Apply sorting
    if ($sort === 'Status') {
        $QueryAll .= " ORDER BY status";
        $QueryClosed .= " ORDER BY status";
    } elseif ($sort === 'Updated') {
        $QueryAll .= " ORDER BY created_at DESC";
        $QueryClosed .= " ORDER BY created_at DESC";
    }

    // Execute queries for all and closed tickets
    try {
        $stmtOpen = $pdo->prepare($QueryAll);
        $stmtOpen->execute($params);
    } catch (PDOException $e) {
        logError("Error executing open tickets query: " . $e->getMessage());
        echo json_encode(['error' => 'Error fetching open tickets']);
        exit;
    }

    try {
        $stmtClosed = $pdo->prepare($QueryClosed);
        $stmtClosed->execute($params);
    } catch (PDOException $e) {
        logError("Error executing closed tickets query: " . $e->getMessage());
        echo json_encode(['error' => 'Error fetching closed tickets']);
        exit;
    }

        // Execute query for open tickets
    try {
        $stmtOpen = $pdo->prepare($QueryOpen);
        $stmtOpen->execute($params);
    } catch (PDOException $e) {
        logError("Error executing open tickets query: " . $e->getMessage());
        echo json_encode(['error' => 'Error fetching open tickets']);
        exit;
    }

    // Execute query for in-progress tickets
    try {
        $stmtInProgress = $pdo->prepare($QueryInProgress);
        $stmtInProgress->execute($params);
    } catch (PDOException $e) {
        logError("Error executing in-progress tickets query: " . $e->getMessage());
        echo json_encode(['error' => 'Error fetching in-progress tickets']);
        exit;
    }

    // Execute query for awaiting-response tickets
    try {
        $stmtAwaitingResponse = $pdo->prepare($QueryAwaitingResponse);
        $stmtAwaitingResponse->execute($params);
    } catch (PDOException $e) {
        logError("Error executing awaiting-response tickets query: " . $e->getMessage());
        echo json_encode(['error' => 'Error fetching awaiting-response tickets']);
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
*/

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

    // Define base queries
    $baseQueryOpen = "SELECT id, category AS request_type, title AS request_title, status, created_at AS updated FROM tickets";
    $baseQueryClosed = "SELECT id, category AS request_type, title AS request_title, status, created_at AS updated FROM closed_tickets WHERE status = 'closed'";

    // Set initial query and parameters
    $query = '';
    $params = [];

    // Build the query based on the filter option
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

    // Add ticket_id filter if provided
    if ($ticketId) {
        $query .= (strpos($query, 'WHERE') !== false ? " AND" : " WHERE") . " id = :ticket_id";
        $params[':ticket_id'] = $ticketId;
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