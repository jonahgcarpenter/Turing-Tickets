<?php
require_once 'database.php';

if (isset($_GET['id'])) {
    $adminId = $_GET['id'];

    $pdo = Database::dbConnect();
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id AND role = 'admin'");
    $stmt->bindParam(':id', $adminId);

    if ($stmt->execute()) {
        header('Location: add_admin.php');
        exit;
    } else {
        echo "Error deleting admin.";
    }
}
?>
