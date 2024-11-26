<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../config/database.php');
require_once('emails.php');  // Changed from phpmailer.php to emails.php

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    try {
        $pdo = Database::dbConnect();

        // Insert the ticket into the database
        $stmt = $pdo->prepare('INSERT INTO tickets (title, name, email, category, description, status) VALUES (:title, :name, :email, :category, :description, "open")');
        $stmt->execute([
            ':title' => $title,
            ':name' => $name,
            ':email' => $email,
            ':category' => $category, // category must match ENUM values exactly
            ':description' => $description
        ]);

        // Get the ID of the newly created ticket
        $ticketId = $pdo->lastInsertId();

        // Send email confirmation
        try {
            $mailHandler = new MailHandler();
            $ticketDetails = [
                'id' => $ticketId,
                'subject' => $title,
                'description' => $description,
                'status' => 'open'
            ];
            
            $emailSent = $mailHandler->sendNewTicketNotification($email, $ticketDetails);
            
            if (!$emailSent) {
                error_log("Failed to send email for ticket #$ticketId");
            }
            
            $message = "Ticket #$ticketId submitted successfully. ";
            $message .= $emailSent ? "A confirmation email has been sent." : "Email notification could not be sent.";
            
            echo json_encode(['success' => true, 'message' => $message]);
        } catch (Exception $e) {
            error_log("Email error: " . $e->getMessage());
            echo json_encode(['success' => true, 'message' => "Ticket created but email failed: " . $e->getMessage()]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error submitting ticket: ' . $e->getMessage()]);
    }

    Database::dbDisconnect();
}
?>
