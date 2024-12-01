<?php
/**
 * Ticket Submission Handler
 * Creates new support tickets and sends confirmation emails
 * Validates required fields and manages database insertion
 * No authentication required - public endpoint
 * Jonah Carpenter - Turing Tickets
 */
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../database/database.php');
require_once('emails.php');  // Changed from phpmailer.php to emails.php

header('Content-Type: application/json');

// Handle POST requests for new ticket submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Extract and validate required fields from POST data
    $title = $_POST['title'] ?? null;
    $name = $_POST['name'] ?? null;
    $email = $_POST['email'] ?? null;
    $category = $_POST['category'] ?? null;
    $description = $_POST['issue'] ?? null; // Match this to the form's textarea name

    // Validate input fields
    if (!$title || !$name || !$email || !$category || !$description) {
        echo json_encode(['success' => false, 'error' => 'All fields are required.']);
        exit();
    }

    $response = ['success' => false, 'message' => '', 'ticketId' => null];

    try {
        // Begin transaction for atomic ticket creation
        $pdo = Database::dbConnect();
        $pdo->beginTransaction();

        // Initialize email handler for notifications
        $mailHandler = new MailHandler($pdo);

        // Insert new ticket into database
        $stmt = $pdo->prepare('INSERT INTO tickets (title, name, email, category, description, status) VALUES (:title, :name, :email, :category, :description, "open")');
        $stmt->execute([
            ':title' => $title,
            ':name' => $name,
            ':email' => $email,
            ':category' => $category,
            ':description' => $description
        ]);

        $ticketId = $pdo->lastInsertId();
        
        // Send confirmation email to user
        $ticketDetails = [
            'id' => $ticketId,
            'subject' => $title,
            'description' => $description,
            'status' => 'open'
        ];
        
        $emailSent = $mailHandler->sendNewTicketNotification($email, $ticketDetails);
        
        // Commit transaction and prepare success response
        $pdo->commit();

        $response['success'] = true;
        $response['ticketId'] = $ticketId;
        $response['message'] = "Ticket #$ticketId created successfully.";
        if (!$emailSent) {
            $response['message'] .= " Note: Confirmation email could not be sent.";
        }

    } catch (Exception $e) {
        // Handle errors and rollback if needed
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $response['success'] = false;
        $response['message'] = "Error: " . $e->getMessage();
    }

    // Return JSON response and close database connection
    echo json_encode($response);
    Database::dbDisconnect();
}
?>
