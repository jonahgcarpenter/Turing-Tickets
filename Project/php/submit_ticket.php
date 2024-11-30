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

    $response = ['success' => false, 'message' => '', 'ticketId' => null];

    try {
        $pdo = Database::dbConnect();

        $stmt = $pdo->prepare('INSERT INTO tickets (title, name, email, category, description, status) VALUES (:title, :name, :email, :category, :description, "open")');
        $stmt->execute([
            ':title' => $title,
            ':name' => $name,
            ':email' => $email,
            ':category' => $category,
            ':description' => $description
        ]);

        $ticketId = $pdo->lastInsertId();

        echo json_encode(['success' => true, 'message' => "Ticket submitted successfully. Ticket ID: $ticketId"]);
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $response['success'] = false;
        $response['message'] = "Error: " . $e->getMessage();
    }

    echo json_encode($response);
    Database::dbDisconnect();
}
?>
