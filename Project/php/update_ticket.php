<?php
require_once('../config/database.php');
require_once('emails.php');
session_start();

// Debug logging for session verification
error_log('Current session data: ' . print_r($_SESSION, true));

// Match login.php session check pattern
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || !isset($_SESSION['admin_id'])) {
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
$response = ['success' => true, 'message' => ''];

// Get admin info from session (matching login.php pattern)
$admin_id = $_SESSION['admin_id'];
$admin_username = $_SESSION['admin_username'];

error_log("Admin ID from session: $admin_id"); // Debug log
error_log("Admin Username from session: $admin_username"); // Debug log

try {
    $pdo = Database::dbConnect();
    $mailer = new MailHandler($pdo);

    // Verify admin exists in database
    $stmt = $pdo->prepare('SELECT id FROM users WHERE id = :admin_id AND role = "admin"');
    $stmt->execute([':admin_id' => $admin_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Invalid admin session');
    }

    $data = json_decode(file_get_contents('php://input'), true);

    // Determine operation type based on input
    $isStatusUpdate = isset($data['status']) && !empty($data['status']);
    $isResponseUpdate = isset($data['response']) && !empty($data['response']);

    if (!isset($data['ticket_id']) || (!$isStatusUpdate && !$isResponseUpdate)) {
        $response['success'] = false;
        $response['message'] = 'Invalid input. Ticket ID and at least one change required.';
        echo json_encode($response);
        exit;
    }

    $ticket_id = intval($data['ticket_id']);
    error_log("Using admin_id from session: " . $admin_id); // Debug log

    try {
        $pdo->beginTransaction();
        
        // Simple location check
        $stmt = $pdo->prepare("SELECT 'active' as location FROM tickets WHERE id = ? UNION SELECT 'closed' FROM closed_tickets WHERE id = ?");
        $stmt->execute([$ticket_id, $ticket_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            throw new Exception('Ticket not found');
        }
        
        $currentLocation = $result['location'];
        error_log("Current location: " . $currentLocation);
        error_log("Status update: " . ($isStatusUpdate ? $data['status'] : 'none'));
        error_log("Response update: " . ($isResponseUpdate ? 'yes' : 'no'));

        // STEP 1: Handle any new responses first
        if ($isResponseUpdate) {
            if ($currentLocation === 'active') {
                // Add response to active ticket
                $stmt = $pdo->prepare("
                    INSERT INTO responses (ticket_id, admin_id, response, created_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$ticket_id, $admin_id, $data['response']]);
                error_log("Added response to active ticket");
            } else {
                // Add response to closed ticket
                $stmt = $pdo->prepare("
                    INSERT INTO closed_responses (ticket_id, admin_id, response, created_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$ticket_id, $admin_id, $data['response']]);
                error_log("Added response to closed ticket");
            }
        }

        // STEP 2: Handle status changes
        if ($isStatusUpdate) {
            $newStatus = $data['status'];
            
            // Case 1: Moving to closed
            if ($newStatus === 'closed' && $currentLocation === 'active') {
                // First move the ticket
                $stmt = $pdo->prepare("
                    INSERT INTO closed_tickets 
                    (id, title, name, email, category, description, status, closed_date, created_at, updated_at)
                    SELECT id, title, name, email, category, description, 
                           'closed', NOW(), created_at, NOW()
                    FROM tickets WHERE id = ?
                ");
                $stmt->execute([$ticket_id]);

                // Then move all responses
                $stmt = $pdo->prepare("
                    INSERT INTO closed_responses (id, ticket_id, admin_id, response, created_at)
                    SELECT id, ticket_id, admin_id, response, created_at
                    FROM responses WHERE ticket_id = ?
                ");
                $stmt->execute([$ticket_id]);

                // Clean up active tables
                $stmt = $pdo->prepare("DELETE FROM responses WHERE ticket_id = ?");
                $stmt->execute([$ticket_id]);
                $stmt = $pdo->prepare("DELETE FROM tickets WHERE id = ?");
                $stmt->execute([$ticket_id]);
                
                $response['message'] = 'Ticket closed successfully';
            }
            // Case 2: Reopening ticket
            else if ($newStatus !== 'closed' && $currentLocation === 'closed') {
                // Move ticket to active
                $stmt = $pdo->prepare("
                    INSERT INTO tickets 
                    (id, title, name, email, category, description, status, created_at, updated_at)
                    SELECT id, title, name, email, category, description, 
                           ?, created_at, NOW()
                    FROM closed_tickets WHERE id = ?
                ");
                $stmt->execute([$newStatus, $ticket_id]);

                // Move all responses
                $stmt = $pdo->prepare("
                    INSERT INTO responses (id, ticket_id, admin_id, response, created_at)
                    SELECT id, ticket_id, admin_id, response, created_at
                    FROM closed_responses WHERE ticket_id = ?
                ");
                $stmt->execute([$ticket_id]);

                // Clean up closed tables
                $stmt = $pdo->prepare("DELETE FROM closed_responses WHERE ticket_id = ?");
                $stmt->execute([$ticket_id]);
                $stmt = $pdo->prepare("DELETE FROM closed_tickets WHERE id = ?");
                $stmt->execute([$ticket_id]);

                $response['message'] = 'Ticket reopened with status: ' . $newStatus;
            }
            // Case 3: Regular status update
            else if ($currentLocation === 'active') {
                $stmt = $pdo->prepare("
                    UPDATE tickets 
                    SET status = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$newStatus, $ticket_id]);
                $response['message'] = 'Status updated successfully';
            }
        }

        // Set final message if both operations occurred
        if ($isStatusUpdate && $isResponseUpdate) {
            $response['message'] .= ' and response added';
        } else if ($isResponseUpdate && !$isStatusUpdate) {
            $response['message'] = 'Response added successfully';
        }

        $pdo->commit();
        $response['success'] = true;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $response['success'] = false;
        $response['message'] = 'Database operation failed';
        $response['error'] = $e->getMessage();
        $response['sqlError'] = [
            'code' => $e->getCode(),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ];
    }

    echo json_encode($response);

} catch (PDOException $e) {
    $response['success'] = false;
    $response['message'] = 'Database connection failed';
    $response['error'] = $e->getMessage();
    $response['sqlError'] = [
        'code' => $e->getCode(),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
    echo json_encode($response);
    exit;
}