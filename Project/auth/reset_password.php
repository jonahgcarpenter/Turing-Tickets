<?php
require_once('../config/database.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? null;
    $password = $_POST['password'] ?? null;
    $new_password = $_POST['new_password'] ?? null;

    // Validate input fields
    if (!$username || !$password || !$new_password) {
        echo json_encode(['success' => false, 'error' => 'All fields are required.']);
        exit();
    }

    try {
        $pdo = Database::dbConnect();
        
        // Verify the current username and password
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Hash the new password
            $hashedNewPassword = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update the password in the database
            $updateStmt = $pdo->prepare("UPDATE users SET password = :new_password WHERE username = :username");
            $updateStmt->execute([
                ':new_password' => $hashedNewPassword,
                ':username' => $username
            ]);

            echo json_encode(['success' => true, 'message' => 'Password reset successfully.']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid username or password.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error resetting password: ' . $e->getMessage()]);
    }

    Database::dbDisconnect();
}
?>
