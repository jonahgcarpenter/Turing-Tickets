<?php
require_once('../config/database.php');
require_once('../php/emails.php');
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode([
        'error' => 'Unauthorized access',
        'redirect' => true,
        'redirectUrl' => '../html/admin_login.html',
        'message' => 'Please login first!'
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? null;
    $password = $_POST['password'] ?? null;
    $email = $_POST['email'] ?? null;

    // Validate input fields
    if (!$username || !$password || !$email) {
        echo json_encode(['success' => false, 'error' => 'All fields are required.']);
        exit();
    }

    try {
        $pdo = Database::dbConnect();
        
        // Hash the password before storing it
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Insert the new admin into the database
        $stmt = $pdo->prepare('INSERT INTO users (username, password, email, role) VALUES (:username, :password, :email, "admin")');
        $stmt->execute([
            ':username' => $username,
            ':password' => $hashedPassword,
            ':email' => $email
        ]);

        // Retrieve the ID of the newly added admin
        $newAdmin = [
            'id' => $pdo->lastInsertId(),
            'username' => $username,
            'email' => $email
        ];

        echo json_encode(['success' => true, 'admin' => $newAdmin]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error adding admin: ' . $e->getMessage()]);
    }

    Database::dbDisconnect();
}
?>
