<?php
require_once('../config/database.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $pdo = Database::dbConnect();

    try {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username AND role = "admin"');
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Start the session and set session variables on successful login only
            session_start();
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            // Output success alert with redirect to admin dashboard
            echo "<script>
                alert('Login successful!');
                window.location.href = '../html/admin_dashboard.html';
            </script>";
        } else {
            // Output error alert with redirect back to login page
            echo "<script>
                alert('Invalid username or password.');
                window.location.href = '../html/admin_login.html';
            </script>";
        }
    } catch (Exception $e) {
        // Output error alert with redirect back to login page in case of exception
        echo "<script>
            alert('Error logging in: " . addslashes($e->getMessage()) . "');
            window.location.href = '../html/admin_login.html';
        </script>";
    }

    Database::dbDisconnect();
}
?>
