<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('./Project/config/database.php');

// Get database connection
$pdo = Database::dbConnect();

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Add 50 sample tickets
    echo "Adding sample tickets...<br>";
    
    $titles = [
        "Cannot access email",
        "Printer not working",
        "Password reset needed",
        "Software installation help",
        "Network connectivity issues"
    ];
    
    $categories = [
        "login-issue",
        "password-reset",
        "ip-block",
        "other"
    ];
    
    $descriptions = [
        "I'm having trouble with this. Can you help?",
        "This has been an ongoing issue. Please assist.",
        "Urgent help needed with this problem.",
        "Need technical support for this issue.",
        "Having difficulties with this situation."
    ];
    
    for($i = 1; $i <= 50; $i++) {
        $title = $titles[array_rand($titles)];
        $category = $categories[array_rand($categories)];
        $description = $descriptions[array_rand($descriptions)];
        
        $stmt = $pdo->prepare('INSERT INTO tickets (title, name, email, category, description, status) 
                              VALUES (:title, :name, :email, :category, :description, "open")');
        $stmt->execute([
            ':title' => $title,
            ':name' => 'Jonah Carpenter',
            ':email' => 'jgcarpe2@go.olemiss.edu',
            ':category' => $category,
            ':description' => $description
        ]);
    }

    for($i = 51; $i <= 100; $i++) {
        $title = $titles[array_rand($titles)];
        $category = $categories[array_rand($categories)];
        $description = $descriptions[array_rand($descriptions)];
        
        $stmt = $pdo->prepare('INSERT INTO tickets (title, name, email, category, description, status) 
                              VALUES (:title, :name, :email, :category, :description, "in-progress")');
        $stmt->execute([
            ':title' => $title,
            ':name' => 'Jonah Carpenter',
            ':email' => 'jgcarpe2@go.olemiss.edu',
            ':category' => $category,
            ':description' => $description
        ]);
    }

    for($i = 101; $i <= 150; $i++) {
        $title = $titles[array_rand($titles)];
        $category = $categories[array_rand($categories)];
        $description = $descriptions[array_rand($descriptions)];
        
        $stmt = $pdo->prepare('INSERT INTO tickets (title, name, email, category, description, status) 
                              VALUES (:title, :name, :email, :category, :description, "awaiting-response")');
        $stmt->execute([
            ':title' => $title,
            ':name' => 'Jonah Carpenter',
            ':email' => 'jgcarpe2@go.olemiss.edu',
            ':category' => $category,
            ':description' => $description
        ]);
    }
    
    // Commit all changes
    $pdo->commit();
    echo "<strong>Successfully added 150 sample tickets.</strong>";
    
} catch(PDOException $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Database Error: " . $e->getMessage());
}

// Close connection
Database::dbDisconnect();
?>
