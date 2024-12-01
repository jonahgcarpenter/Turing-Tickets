<?php
/**
 * Admin Login Handler
 * Authenticates administrators and establishes secure sessions
 * Security measures: Password verification, role checking, session management
 * Jonah Carpenter - Turing Tickets
 */

// Initial setup and session check
require_once('../database/database.php');
session_start();

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: ../html/admin_dashboard.html');
    exit();
}

// Login form processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Credential validation
    $username = $_POST['username'];
    $password = $_POST['password'];

    $pdo = Database::dbConnect();

    try {
        // Admin authentication
        // Security: Specifically check for admin role to prevent regular users
        // from accessing admin functionality
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username AND role = "admin"');
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Session initialization on successful login
        if ($user && password_verify($password, $user['password'])) {
            // Start the session and set session variables on successful login only
            session_start();
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];

            error_log('Login successful - Session data: ' . print_r($_SESSION, true)); // Debug log
            
            // Redirect behavior
            echo "<script>
                alert('Login successful!');
                window.location.href = '../html/admin_dashboard.html';
            </script>";
            exit();
        } else {
            // Error handling and redirects
            // Output error alert with redirect back to login page
            echo "<script>
                alert('Invalid username or password.');
                window.location.href = '../html/admin_login.html';
            </script>";
        }
    } catch (Exception $e) {
        // Error handling and redirects
        // Output error alert with redirect back to login page in case of exception
        echo "<script>
            alert('Error logging in: " . addslashes($e->getMessage()) . "');
            window.location.href = '../html/admin_login.html';
        </script>";
    }

    Database::dbDisconnect();
}
?>
