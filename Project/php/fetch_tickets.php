<?php
/**
 * Ticket Retrieval Handler
 * Fetches and filters support tickets based on status and sort criteria
 * Includes responses history and manages both active and closed tickets
 * Security measures: Admin session verification
 * Jonah Carpenter - Turing Tickets
 */
// Configure error handling and session management
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once('../database/database.php');
session_start();

// Verify admin authorization
// Enhanced session check with redirect information
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Unauthorized access',
        'redirect' => true,
        'redirectUrl' => '../html/admin_login.html',
        'message' => 'Please login first!'
    ]);
    exit();
}

header('Content-Type: application/json');

// Helper function for error logging
// Log errors to the console
function logError($message) {
    error_log("PHP SQL Error: $message", 0);
}

// Function to fetch admin responses for a ticket
// Function to fetch responses for tickets
function fetchNotes($pdo, $ticketId, $isClosed = false) {
    $tableName = $isClosed ? 'closed_responses' : 'responses';
    try {
        $stmt = $pdo->prepare("SELECT r.response AS content, r.created_at, 
                                     u.id as admin_id, u.username as admin_username 
                              FROM $tableName r 
                              LEFT JOIN users u ON r.admin_id = u.id 
                              WHERE r.ticket_id = :ticket_id 
                              AND u.role = 'admin'
                              ORDER BY r.created_at ASC");
        $stmt->execute([':ticket_id' => $ticketId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError("Error fetching responses for ticket $ticketId from $tableName: " . $e->getMessage());
        return [];
    }
}

try {
    // Initialize database connection
    $pdo = Database::dbConnect();
    
    // Define base queries with consistent column names for both active and closed tickets
    // Base queries with consistent column names
    $baseQueryOpen = "SELECT id, category AS request_type, title AS request_title, 
                             status, created_at, updated_at, name, email, description 
                      FROM tickets";
    $baseQueryClosed = "SELECT id, category AS request_type, title AS request_title, 
                               status, created_at, updated_at, name, email, description 
                        FROM closed_tickets";

    // Process filter and sort parameters from request
    // Get parameters
    $filterOption = isset($_GET['filterOption']) ? $_GET['filterOption'] : 'all';
    $sortOption = isset($_GET['sort']) ? $_GET['sort'] : 'status';
    $ticketId = isset($_GET['ticket_id']) ? $_GET['ticket_id'] : null;

    if ($ticketId) {
        // When searching by specific ticket ID, check both active and closed tables
        // When searching by ID, check both tables
        $query = "($baseQueryOpen WHERE id = :ticket_id) 
                 UNION 
                 ($baseQueryClosed WHERE id = :ticket_id)";
        $params = [':ticket_id' => $ticketId];
    } else {
        // Handle filtering based on ticket status
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

        // Apply sorting based on user preference
        // Handle sorting
        switch ($sortOption) {
            case 'created-asc':
                $query .= " ORDER BY created_at ASC, id ASC";
                break;
            case 'created-desc':
                $query .= " ORDER BY created_at DESC, id DESC";
                break;
            case 'updated-asc':
                $query .= " ORDER BY updated_at ASC, id ASC";
                break;
            case 'updated-desc':
                $query .= " ORDER BY updated_at DESC, id DESC";
                break;
            default: // 'status'
                $query .= " ORDER BY FIELD(status, 'open', 'in-progress', 'awaiting-response', 'closed'), updated_at DESC";
        }
    }

    // Execute query and fetch tickets
    // Add debug logging for sorting
    error_log("Sort option: " . $sortOption);
    error_log("Filter option: " . $filterOption);
    error_log("Final query: " . $query);

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add notes and server time to each ticket
    // Add server's current time to the response
    $result = array_map(function($ticket) use ($pdo) {
        $ticket['notes'] = fetchNotes($pdo, $ticket['id'], $ticket['status'] === 'closed');
        $ticket['server_time'] = date('Y-m-d H:i:s');
        return $ticket;
    }, $tickets);

    echo json_encode($result);

} catch (PDOException $e) {
    // Handle database errors
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['error' => 'Database connection error']);
}

?>