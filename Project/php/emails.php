<?php
require_once __DIR__ . '/../src/Exception.php';
require_once __DIR__ . '/../src/PHPMailer.php';
require_once __DIR__ . '/../src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailHandler {
    private $mailer;
    private $config;

    public function __construct() {
        $this->config = require __DIR__ . '/../config/phpmailerconfig.php';
        $this->mailer = new PHPMailer(true);
        
        // Configure mailer
        $this->mailer->isSMTP();
        $this->mailer->Host = $this->config['smtp_host'];
        $this->mailer->SMTPAuth = $this->config['smtp_auth'];
        $this->mailer->Username = $this->config['smtp_username'];
        $this->mailer->Password = $this->config['smtp_password'];
        $this->mailer->SMTPSecure = $this->config['smtp_secure'];
        $this->mailer->Port = $this->config['smtp_port'];
    }

    public function sendNewTicketNotification($userEmail, $ticketDetails) {
        try {
            $this->mailer->setFrom($this->config['from_email'], $this->config['from_name']);
            $this->mailer->addAddress($userEmail);
            $this->mailer->Subject = 'Your Ticket Has Been Created';
            
            // Create email body
            $body = "Your ticket has been created successfully!\n\n";
            $body .= "Ticket ID: " . $ticketDetails['id'] . "\n";
            $body .= "Subject: " . $ticketDetails['subject'] . "\n";
            $body .= "Description: " . $ticketDetails['description'] . "\n";
            $body .= "Status: " . $ticketDetails['status'];
            
            $this->mailer->Body = $body;
            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }

    public function sendTicketUpdateNotification($userEmail, $ticketId, $updateDetails) {
        try {
            $this->mailer->setFrom($this->config['from_email'], $this->config['from_name']);
            $this->mailer->addAddress($userEmail);
            $this->mailer->Subject = 'Your Ticket Has Been Updated';
            
            // Create email body
            $body = "There has been an update to your ticket (ID: $ticketId)!\n\n";
            $body .= "Update: " . $updateDetails['message'] . "\n";
            $body .= "New Status: " . $updateDetails['status'] . "\n";
            $body .= "Updated by: " . $updateDetails['updated_by'];
            
            $this->mailer->Body = $body;
            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }

    public function sendStatusChangeNotification($userEmail, $ticketId, $newStatus) {
        try {
            $this->mailer->setFrom($this->config['from_email'], $this->config['from_name']);
            $this->mailer->addAddress($userEmail);
            $this->mailer->Subject = 'Ticket Status Updated';
            
            $body = "Your ticket (ID: $ticketId) status has been updated.\n\n";
            $body .= "New Status: " . $newStatus;
            
            $this->mailer->Body = $body;
            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }

    public function sendResponseNotification($userEmail, $ticketId, $response) {
        try {
            $this->mailer->setFrom($this->config['from_email'], $this->config['from_name']);
            $this->mailer->addAddress($userEmail);
            $this->mailer->Subject = 'New Response to Your Ticket';
            
            $body = "A new response has been added to your ticket (ID: $ticketId).\n\n";
            $body .= "Response: " . $response;
            
            $this->mailer->Body = $body;
            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }
}