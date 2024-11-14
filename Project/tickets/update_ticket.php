<?php
require_once 'config/Project\config\database.php';
session_start();

if (!isset($_SESSION['admin/admin_logged_in']) || $_SESSION['admin/admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$pdo = Database::dbConnect();
$data = json_decode(file_get_contents('php://input'), true);
$ticketId = $data['ticket_id'] ?? null;
$newStatus = $data['status'] ?? null;

if ($ticketId && $newStatus) {
    try {
        $stmt = $pdo->prepare("UPDATE tickets SET status = :status, updated_at = NOW() WHERE id = :ticket_id");
        $stmt->bindParam(':status', $newStatus, PDO::PARAM_STR);
        $stmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Ticket status updated successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid ticket ID or status']);
}
?>
