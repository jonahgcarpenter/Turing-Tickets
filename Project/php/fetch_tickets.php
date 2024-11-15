<?php
require_once('../config/database.php');

header('Content-Type: application/json');

try {
    $pdo = Database::dbConnect();

    // Using 'category' as 'request_type' and 'title' as 'request_title'
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

    // Execute queries
    $stmtOpen = $pdo->prepare($baseQueryOpen);
    $stmtOpen->execute($params);
    $stmtClosed = $pdo->prepare($baseQueryClosed);
    $stmtClosed->execute($params);

    // Fetch results
    $tickets = [];
    foreach ($stmtOpen as $row) {
        $tickets[] = [
            'id' => $row['id'],
            'request_type' => $row['request_type'],
            'request_title' => $row['request_title'],
            'status' => $row['status'],
            'updated' => $row['updated']
        ];
    }
    foreach ($stmtClosed as $row) {
        $tickets[] = [
            'id' => $row['id'],
            'request_type' => $row['request_type'],
            'request_title' => $row['request_title'],
            'status' => $row['status'],
            'updated' => $row['updated']
        ];
    }

    echo json_encode($tickets);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
