<?php
require_once 'database.php';
session_start();

$pdo = Database::dbConnect();

// Check if the admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Detect AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'redirect' => 'admin_login.html']);
    } else {
        // Redirect for non-AJAX requests
        header("Location: admin_login.html");
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $email = $_POST['email'];

    $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role) VALUES (:username, :password, :email, 'admin')");
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':password', $password);
    $stmt->bindParam(':email', $email);

    if ($stmt->execute()) {
        header('Location: add_admin.php?success=1');
        exit;
    } else {
        echo "<p style='color:red;'>Error adding admin. Please try again.</p>";
    }
}

$stmt = $pdo->query("SELECT id, username, email FROM users WHERE role = 'admin'");
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Management</title>
    <link rel="stylesheet" href="Project\styles.css">
    <style>
        /* Base styling */
        body {
            font-family: Arial, sans-serif;
            background-color: #333;
            color: #333;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        header {
            background-color: #001e65;
            color: white;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        header h1 {
            margin: 0;
            font-size: 24px;
        }

        #back-btn {
            background-color: red; /* Red Home button */
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            position: absolute;
            top: 10px;
            right: 10px;
        }

        #back-btn:hover {
            background-color: darkred;
        }

        #add-admin {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
            background-color: #333;
        }

        .form-wrapper {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            margin-bottom: 20px;
            text-align: center;
        }

        .form-wrapper h2 {
            font-size: 22px;
            color: #001e65;
            margin-bottom: 20px;
        }

        form label {
            display: block;
            margin: 10px 0 5px;
            color: #333;
            font-weight: bold;
        }

        form input {
            width: calc(100% - 22px);
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        form button {
            width: 100%;
            padding: 12px;
            background-color: #001e65;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }

        form button:hover {
            background-color: darkblue;
        }

        .admin-table-wrapper {
            width: 100%;
            max-width: 500px;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
            color: #333;
        }

        .admin-table th, .admin-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }

        .admin-table th {
            background-color: #f4f4f4;
            font-weight: bold;
        }

        .delete-btn {
            color: red;
            cursor: pointer;
            border: none;
            background: none;
        }

        footer {
            text-align: center;
            background-color: #001e65;
            color: white;
            padding: 10px;
            width: 100%;
        }
    </style>
</head>
<body>

<header>
    <h1>Admin Management</h1>
    <button id="back-btn" onclick="window.location.href='admin_dashboard.html'">Back</button>
</header>

<section id="add-admin">
    <div class="form-wrapper">
        <h2>Create New Admin Account</h2>
        <?php if (isset($_GET['success'])): ?>
            <p style="color: green;">Admin added successfully!</p>
        <?php endif; ?>
        <form action="add_admin.php" method="POST">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required>

            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>

            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>

            <button type="submit">Add Admin</button>
        </form>
    </div>

    <div class="admin-table-wrapper">
        <h2>Admin Users</h2>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($admins as $admin): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($admin['username']); ?></td>
                        <td><?php echo htmlspecialchars($admin['email']); ?></td>
                        <td><button class="delete-btn" onclick="confirmDelete(<?php echo intval($admin['id']); ?>)">X</button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<footer>
    &copy; 2024 Turing Ticket System
</footer>

<script>
    function confirmDelete(adminId) {
        if (confirm('Are you sure you want to delete this admin?')) {
            window.location.href = 'delete_user.php?id=' + adminId;
        }
    }
</script>

</body>
</html>
