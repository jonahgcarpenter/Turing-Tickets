<?php
require_once('../config/database.php');

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

        echo json_encode(['success' => true, 'message' => "Ticket submitted successfully. Ticket ID: $ticketId"]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error submitting ticket: ' . $e->getMessage()]);
    }

    Database::dbDisconnect();
}
?>
