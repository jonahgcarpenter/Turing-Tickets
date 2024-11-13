<?php
require_once('database.php');

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
            header("Location: successfully_submitted.html?ticket_id=" . $ticket_id);
            exit;
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }

    Database::dbDisconnect();
}
?>
