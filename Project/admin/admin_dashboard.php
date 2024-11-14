<?php
require_once 'database.php';
session_start();

$pdo = Database::dbConnect();

// Session check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        echo json_encode([
            'success' => false,
            'redirect' => 'admin_login.html',
            'message' => 'Not authenticated'
        ]);
    } else {
        header("Location: admin_login.html");
    }
    exit;
}

try {
    // Fetch sort and filter parameters
    $sortBy = $_GET['sort'] ?? 'status';
    $statusFilter = $_GET['status'] ?? '';
    $ticketId = $_GET['ticket_id'] ?? '';

    // Build the SQL query based on sort and filter parameters
    if ($ticketId) {
        // Fetch a specific ticket by ID (for search functionality)
        $query = "
            SELECT 'open' AS table_type, t.id, t.title, t.name, t.email, t.category, t.description, t.status, 
                   t.updated_at AS updated, t.created_at AS created,
                   (SELECT response FROM responses WHERE ticket_id = t.id ORDER BY created_at DESC LIMIT 1) AS latest_note,
                   (SELECT GROUP_CONCAT(CONCAT(response, '::', created_at) SEPARATOR ';') FROM responses WHERE ticket_id = t.id ORDER BY created_at ASC) AS notes
            FROM tickets t
            WHERE t.id = :ticket_id
            UNION ALL
            SELECT 'closed' AS table_type, ct.id, ct.title, ct.name, ct.email, ct.category, ct.description, ct.status, 
                   ct.closed_date AS updated, ct.closed_date AS created,
                   (SELECT response FROM closed_responses WHERE ticket_id = ct.id ORDER BY created_at DESC LIMIT 1) AS latest_note,
                   (SELECT GROUP_CONCAT(CONCAT(response, '::', created_at) SEPARATOR ';') FROM closed_responses WHERE ticket_id = ct.id ORDER BY created_at ASC) AS notes
            FROM closed_tickets ct
            WHERE ct.id = :ticket_id
        ";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
    } else {
        // Define sorting based on `sort` parameter
        $orderByClause = "";
        if ($sortBy === 'status') {
            // Custom sort order by status: open > in-progress > awaiting-response > closed
            $orderByClause = "
                ORDER BY 
                    CASE 
                        WHEN t.status = 'open' THEN 1
                        WHEN t.status = 'in-progress' THEN 2
                        WHEN t.status = 'awaiting-response' THEN 3
                        ELSE 4
                    END, 
                    t.updated_at DESC
            ";
        } elseif ($sortBy === 'updated-asc') {
            $orderByClause = "ORDER BY t.updated_at ASC";
        } else {
            $orderByClause = "ORDER BY t.updated_at DESC";
        }

        // Base query for tickets depending on the status filter and sort order
        if ($statusFilter === 'closed') {
            // Fetch closed tickets if the status filter is set to closed
            $query = "
                SELECT ct.id, ct.title, ct.name, ct.email, ct.category, ct.description, ct.status, 
                       ct.closed_date AS updated, ct.closed_date AS created,
                       (SELECT response FROM closed_responses WHERE ticket_id = ct.id ORDER BY created_at DESC LIMIT 1) AS latest_note,
                       (SELECT GROUP_CONCAT(CONCAT(response, '::', created_at) SEPARATOR ';') FROM closed_responses WHERE ticket_id = ct.id ORDER BY created_at ASC) AS notes
                FROM closed_tickets ct
                ORDER BY ct.closed_date DESC
            ";
        } elseif ($statusFilter) {
            // Fetch tickets with a specific status (open, in-progress, awaiting-response)
            $query = "
                SELECT t.id, t.title, t.name, t.email, t.category, t.description, t.status, 
                       t.updated_at AS updated, t.created_at AS created,
                       (SELECT response FROM responses WHERE ticket_id = t.id ORDER BY created_at DESC LIMIT 1) AS latest_note,
                       (SELECT GROUP_CONCAT(CONCAT(response, '::', created_at) SEPARATOR ';') FROM responses WHERE ticket_id = t.id ORDER BY created_at ASC) AS notes
                FROM tickets t
                WHERE t.status = :statusFilter
                $orderByClause
            ";
        } else {
            // Fetch all tickets with the specified sort order
            $query = "
                SELECT t.id, t.title, t.name, t.email, t.category, t.description, t.status, 
                       t.updated_at AS updated, t.created_at AS created,
                       (SELECT response FROM responses WHERE ticket_id = t.id ORDER BY created_at DESC LIMIT 1) AS latest_note,
                       (SELECT GROUP_CONCAT(CONCAT(response, '::', created_at) SEPARATOR ';') FROM responses WHERE ticket_id = t.id ORDER BY created_at ASC) AS notes
                FROM tickets t
                $orderByClause
            ";
        }

        $stmt = $pdo->prepare($query);

        // Bind the status filter if filtering by a specific status
        if ($statusFilter && $statusFilter !== 'closed') {
            $stmt->bindParam(':statusFilter', $statusFilter, PDO::PARAM_STR);
        }
    }

    // Execute the statement and fetch results
    $stmt->execute();
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'tickets' => $tickets]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

Database::dbDisconnect();
?>
