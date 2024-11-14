<?php
require_once('../config/database.php');

header('Content-Type: application/json'); // Set header for JSON response

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $title = $_POST['title'];
    $category = $_POST['category'];
    $description = $_POST['description'];

    $pdo = Database::dbConnect();

    try {
        $stmt = $pdo->prepare("INSERT INTO tickets (name, email, title, category, description, status, created_at) VALUES (:name, :email, :title, :category, :description, 'open', NOW())");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':category', $category);
        $stmt->bindParam(':description', $description);

        if ($stmt->execute()) {
            $ticket_id = $pdo->lastInsertId();
            echo json_encode(["success" => true, "ticket_id" => $ticket_id]); // Return JSON response
        } else {
            echo json_encode(["success" => false, "message" => "Failed to submit ticket."]);
        }
    } catch (Exception $e) {
        echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]); // Return error message as JSON
    }

    Database::dbDisconnect();
} else {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
}
?>
