<?php
require_once('./config/database.php');
session_start();

$pdo = Database::dbConnect();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login = $_POST['login'];
    $current_password = $_POST['password'];
    $new_password = $_POST['new-password'];

    $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);

    try {
        $query = "SELECT * FROM users WHERE username = :username";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':username', $login);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if (password_verify($current_password, $user['password'])) {
                $update_query = "UPDATE users SET password = :new_password WHERE username = :username";
                $update_stmt = $pdo->prepare($update_query);
                $update_stmt->bindParam(':new_password', $hashed_new_password);
                $update_stmt->bindParam(':username', $login);

                if ($update_stmt->execute()) {
                    header('Location: ./admin/admin_login.html');
                    exit();
                } else {
                    echo "Error updating password.";
                }
            } else {
                echo "Current password is incorrect.";
            }
        } else {
            echo "User not found.";
        }
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}

Database::dbDisconnect();
?>
