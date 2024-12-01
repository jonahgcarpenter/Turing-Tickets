<?php
/**
 * Email Notification System
 * Handles all email communications for the ticketing system
 * Features: HTML templates, email threading, notifications for ticket events
 * Uses PHPMailer for reliable email delivery
 * Jonah Carpenter - Turing Tickets
 */
// Import PHPMailer classes
require_once __DIR__ . '/../src/Exception.php';
require_once __DIR__ . '/../src/PHPMailer.php';
require_once __DIR__ . '/../src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailHandler {
    // Initialize PHPMailer with SMTP configuration
    private $mailer;
    private $config;
    private $db;

    public function __construct($db) {
        $this->db = $db;
        $this->config = require __DIR__ . '/../config/phpmailerconfig.php';
        $this->mailer = new PHPMailer(true);
        
        // Configure mailer with minimal debug settings
        $this->mailer->isSMTP();
        $this->mailer->Host = $this->config['smtp_host'];
        $this->mailer->SMTPAuth = true;  // Force authentication
        $this->mailer->Username = $this->config['smtp_username'];
        $this->mailer->Password = $this->config['smtp_password'];
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;  // Use explicit TLS
        $this->mailer->Port = 587;  // Standard TLS port
        $this->mailer->isHTML(true);
        $this->mailer->XMailer = 'TuringTickets';
        
        // Only show errors
        $this->mailer->SMTPDebug = 0;  // Disable debug output by default
        $this->mailer->Debugoutput = function($str, $level) {
            if ($level <= 2) {  // Only show errors and warnings
                echo "Mail Error: $str\n";
            }
        };
    }

    // Helper method to reset mailer state between sends
    private function resetMailer() {
        $this->mailer->clearAddresses();
        $this->mailer->clearAllRecipients();
        $this->mailer->clearAttachments();
        $this->mailer->clearCustomHeaders();
        $this->mailer->Subject = '';
        $this->mailer->Body = '';
        $this->mailer->AltBody = '';
    }

    // Generate unique message IDs for email threading
    private function generateMessageId($ticketId) {
        $timestamp = time();
        $random = substr(md5(rand()), 0, 8);
        $domain = parse_url($this->config['smtp_host'], PHP_URL_HOST);
        return "<ticket-$ticketId-$timestamp-$random@$domain>";
    }

    // Set email headers for proper threading and ticket tracking
    private function getThreadHeaders($ticketId) {
        // Get the base thread ID which will be consistent for all messages in the thread
        $domain = parse_url($this->config['smtp_host'], PHP_URL_HOST);
        $threadId = "<ticket-$ticketId@$domain>";
        
        // Generate a unique Message-ID for this specific email
        $this->mailer->MessageID = $this->generateMessageId($ticketId);
        
        // Set References to maintain the conversation thread
        $this->mailer->addCustomHeader('References', $threadId);
        $this->mailer->addCustomHeader('Thread-Topic', "Ticket #$ticketId");
        $this->mailer->addCustomHeader('Thread-Index', base64_encode(pack('H*', md5($ticketId))));
        $this->mailer->addCustomHeader('In-Reply-To', $threadId);
        $this->mailer->addCustomHeader('X-Ticket-ID', $ticketId);
    }

    // Generate consistent HTML email template
    private function getEmailTemplate($content) {
        return "<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'>"
             . "<div style='background:#003366;padding:15px;text-align:center'>"
             . "<h1 style='color:white;margin:0;font-size:20px'>Turing Tickets Support</h1>"
             . "</div>"
             . "<div style='padding:15px;background:#f5f5f5'>$content</div>"
             . "<div style='padding:10px;background:#eee;font-size:11px;text-align:center'>"
             . "<p style='margin:0'>Automated message - Do not reply</p>"
             . "</div></div>";
    }

    // Core email sending functionality with error handling
    private function sendEmail() {
        try {
            if (!$this->mailer->send()) {
                error_log("Email sending failed: " . $this->mailer->ErrorInfo);
                return false;
            }
            $recipients = implode(", ", array_map(function($recipient) {
                return $recipient[0];
            }, $this->mailer->getToAddresses()));
            error_log("Email sent successfully to: $recipients");
            return true;
        } catch (Exception $e) {
            error_log("Email error: " . $e->getMessage());
            return false;
        }
    }

    // Format ticket response history for emails
    private function formatNotes($notes) {
        if (empty($notes) || !is_array($notes)) {
            return "<div style='padding:8px;'>No response history available</div>";
        }

        $notesHtml = "";
        foreach ($notes as $note) {
            $timestamp = date('M d, Y H:i:s', strtotime($note['created_at']));
            $notesHtml .= "<div style='padding:8px; border-left:3px solid #003366; margin:5px 0;'>"
                        . "<p style='color:#666; margin:0 0 5px 0; font-size:0.9em;'>"
                        . "<strong>{$note['admin_username']}</strong> - $timestamp</p>"
                        . nl2br(htmlspecialchars($note['content']))
                        . "</div>";
        }
        return $notesHtml;
    }

    // Fetch complete ticket data from database
    private function generateTicketContent($ticketData, $title, $additionalContent = '') {
        $ticketId = $ticketData['ticket_id'];
        $standardContent = "
            <div style='background-color: #f9f9f9; padding: 15px; border-left: 4px solid #003366; margin: 10px 0;'>
                <h2 style='margin-top:0;color:#003366'>$title</h2>
                <p><strong>Ticket #:</strong> $ticketId</p>
                $additionalContent
                <div class='ticket-details'>
                    <table style='width:100%; border-collapse:collapse; margin-top:10px;'>
                        <tr><td style='padding:8px; border-bottom:1px solid #eee;'>";

        // Add standard ticket metadata
        $metadata = [
            "Request Type" => $ticketData['category'],
            "Request Title" => $ticketData['title'],
            "Status" => $ticketData['status'],
            "Created" => date('M d, Y H:i:s', strtotime($ticketData['created_at'])),
            "Last Updated" => date('M d, Y H:i:s', strtotime($ticketData['updated_at']))
        ];

        if (isset($ticketData['closed_date'])) {
            $metadata["Closed"] = date('M d, Y H:i:s', strtotime($ticketData['closed_date']));
        }

        foreach ($metadata as $label => $value) {
            $standardContent .= "<p><strong>$label:</strong> $value</p>";
        }

        // Add description
        $standardContent .= "<p><strong>Description:</strong><br>" . nl2br(htmlspecialchars($ticketData['description'])) . "</p>";

        // Add responses history
        if (!empty($ticketData['responses'])) {
            $standardContent .= "<div class='notes-history' style='margin-top:15px;'>"
                            . "<h3 style='color:#003366'>Response History</h3>"
                            . $this->formatNotes($ticketData['responses'])
                            . "</div>";
        }

        $standardContent .= "</td></tr></table></div></div>";
        return $standardContent;
    }

    // Various notification methods for different ticket events:
    // - New ticket confirmation
    // - Status changes
    // - New responses
    // - Ticket closure
    // - Ticket reopening
    // - Admin welcome emails
    private function getTicketData($ticketId) {
        $stmt = $this->db->prepare("
            SELECT 
                t.*,
                CASE 
                    WHEN t.status = 'closed' THEN 
                        (SELECT GROUP_CONCAT(CONCAT_WS('|', r.response, r.created_at, COALESCE(u2.username, 'System'))
                         ORDER BY r.created_at DESC SEPARATOR '---')
                         FROM closed_responses r 
                         LEFT JOIN users u2 ON r.admin_id = u2.id
                         WHERE r.ticket_id = t.ticket_id)
                    ELSE 
                        (SELECT GROUP_CONCAT(CONCAT_WS('|', r.response, r.created_at, COALESCE(u2.username, 'System'))
                         ORDER BY r.created_at DESC SEPARATOR '---')
                         FROM responses r 
                         LEFT JOIN users u2 ON r.admin_id = u2.id
                         WHERE r.ticket_id = t.ticket_id)
                END as notes
            FROM (
                SELECT id as ticket_id, title, name, email, category, description, status, 
                       created_at, updated_at
                FROM tickets WHERE id = ?
                UNION ALL
                SELECT id as ticket_id, title, name, email, category, description, status,
                       created_at, updated_at
                FROM closed_tickets WHERE id = ?
            ) t
        ");
        $stmt->execute([$ticketId, $ticketId]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($ticket) {
            // Parse notes into responses array
            $notes = explode('---', $ticket['notes'] ?? '');
            $ticket['responses'] = array_map(function($note) {
                $parts = explode('|', $note);
                return [
                    'content' => $parts[0] ?? '',
                    'created_at' => $parts[1] ?? $ticket['updated_at'],
                    'admin_username' => $parts[2] ?? 'System'
                ];
            }, array_filter($notes));
        }

        return $ticket;
    }

    public function sendNewTicketNotification($userEmail, $ticketData) {
        try {
            $ticketData = $this->getTicketData($ticketData['id']); // Get full ticket data
            if (!$ticketData) return false;
            
            $this->resetMailer();
            $this->mailer->setFrom($this->config['from_email'], $this->config['from_name']);
            $this->mailer->addAddress($userEmail);
            $this->mailer->Subject = "[Ticket #{$ticketData['ticket_id']}] " . $ticketData['title'];
            
            $this->getThreadHeaders($ticketData['ticket_id']);
            
            $content = $this->generateTicketContent(
                $ticketData,
                "New Support Ticket Created",
                "<p>Your support ticket has been created and will be reviewed by our team.</p>"
            );
            
            $this->mailer->Body = $this->getEmailTemplate($content);
            $this->mailer->AltBody = strip_tags(str_replace(['<br>', '</p>'], "\n", $content));
            
            return $this->sendEmail();
        } catch (Exception $e) {
            error_log("New ticket notification error: " . $e->getMessage());
            return false;
        }
    }

    public function sendStatusChangeNotification($userEmail, $ticketId, $newStatus, $adminUsername) {
        try {
            $ticketData = $this->getTicketData($ticketId);
            if (!$ticketData) {
                error_log("Ticket not found: $ticketId");
                return false;
            }

            $this->resetMailer();
            $this->mailer->setFrom($this->config['from_email'], $this->config['from_name']);
            $this->mailer->addAddress($userEmail);

            // Customize subject and content based on status
            $statusText = ucfirst($newStatus);
            switch ($newStatus) {
                case 'in-progress':
                    $statusMessage = "Your ticket is now being worked on by our support team.";
                    break;
                case 'awaiting-response':
                    $statusMessage = "We're waiting for your response to proceed further.";
                    break;
                case 'closed':
                    $statusMessage = "Your ticket has been resolved and closed.";
                    break;
                default:
                    $statusMessage = "Your ticket status has been updated.";
            }

            $this->mailer->Subject = "[Ticket #$ticketId] Status Updated to $statusText";
            
            $this->getThreadHeaders($ticketId);
            
            $content = $this->generateTicketContent(
                $ticketData,
                "Ticket Status Update",
                "<p><strong>Updated by:</strong> $adminUsername</p>" .
                "<p>New Status: <strong>$statusText</strong></p>" .
                "<p>$statusMessage</p>"
            );
            
            $this->mailer->Body = $this->getEmailTemplate($content);
            $this->mailer->AltBody = strip_tags(str_replace(['<br>', '</p>'], "\n", $content));
            
            return $this->sendEmail();
        } catch (Exception $e) {
            error_log("Status change notification error: " . $e->getMessage());
            return false;
        }
    }

    public function sendResponseNotification($userEmail, $ticketId, $response, $ticketData, $adminUsername) {
        try {
            $ticketData = $this->getTicketData($ticketId);
            if (!$ticketData) return false;

            $this->resetMailer();
            $this->mailer->setFrom($this->config['from_email'], $this->config['from_name']);
            $this->mailer->addAddress($userEmail);
            
            // More descriptive subject based on ticket status
            $subjectPrefix = $ticketData['status'] === 'awaiting-response' ? 
                "Action Required" : "New Update";
            $this->mailer->Subject = "[Ticket #{$ticketId}] $subjectPrefix";
            
            $this->getThreadHeaders($ticketId);
            
            $actionNeeded = $ticketData['status'] === 'awaiting-response' ?
                "<p style='color: #ff6b6b;'><strong>Action Required:</strong> Please respond to this update to help us assist you better.</p>" :
                "";
            
            $content = $this->generateTicketContent(
                $ticketData,
                "New Response Added to Your Ticket",
                "<p><strong>Response from:</strong> $adminUsername</p>" .
                "<p><strong>Message:</strong><br>" . nl2br(htmlspecialchars($response)) . "</p>" .
                $actionNeeded
            );
            
            $this->mailer->Body = $this->getEmailTemplate($content);
            $this->mailer->AltBody = strip_tags(str_replace(['<br>', '</p>'], "\n", $content));
            
            return $this->sendEmail();
        } catch (Exception $e) {
            error_log("Response notification error: " . $e->getMessage());
            return false;
        }
    }

    public function sendTicketClosureNotification($userEmail, $ticketId, $ticketData) {
        try {
            $ticketData = $this->getTicketData($ticketId); // Get fresh ticket data
            if (!$ticketData) return false;

            $this->resetMailer();
            $this->mailer->setFrom($this->config['from_email'], $this->config['from_name']);
            $this->mailer->addAddress($userEmail);
            $this->mailer->Subject = "[Ticket #{$ticketId}] Ticket Closed";
            
            $this->getThreadHeaders($ticketId);
            
            $content = $this->generateTicketContent(
                $ticketData,
                "Ticket Has Been Closed",
                "<p>This ticket has been marked as closed.</p>" .
                "<p style='color:#666;margin-top:15px'>Submit a new ticket referencing this ticket number if you need further assistance</p>"
            );
            
            $this->mailer->Body = $this->getEmailTemplate($content);
            $this->mailer->AltBody = strip_tags(str_replace(['<br>', '</p>'], "\n", $content));
            
            return $this->sendEmail();
        } catch (Exception $e) {
            error_log("Ticket closure notification error: " . $e->getMessage());
            return false;
        }
    }

    public function sendTicketReopenedNotification($userEmail, $ticketId, $ticketData) {
        try {
            $ticketData = $this->getTicketData($ticketId); // Get fresh ticket data
            if (!$ticketData) return false;

            $this->resetMailer();
            $this->mailer->setFrom($this->config['from_email'], $this->config['from_name']);
            $this->mailer->addAddress($userEmail);
            $this->mailer->Subject = "[Ticket #{$ticketId}] Ticket Reopened";
            
            $this->getThreadHeaders($ticketId);
            
            $content = $this->generateTicketContent(
                $ticketData,
                "Ticket Has Been Reopened",
                "<p>This ticket has been reopened and will be reviewed by our support team.</p>"
            );
            
            $this->mailer->Body = $this->getEmailTemplate($content);
            $this->mailer->AltBody = strip_tags(str_replace(['<br>', '</p>'], "\n", $content));
            
            return $this->sendEmail();
        } catch (Exception $e) {
            error_log("Ticket reopened notification error: " . $e->getMessage());
            return false;
        }
    }

    public function sendAdminWelcomeEmail($userEmail, $username, $password) {
        try {
            $this->resetMailer();
            $this->mailer->setFrom($this->config['from_email'], $this->config['from_name']);
            $this->mailer->addAddress($userEmail);
            $this->mailer->Subject = "Welcome to Turing Tickets Admin Panel";
            
            $resetUrl = "https://turing.cs.olemiss.edu/~jgcarpe2/CS487/Project/Project/html/reset_password.html";
            
            $content = "<h2 style='margin-top:0'>Welcome to Turing Tickets!</h2>"
                    . "<p><strong>Username:</strong> $username<br>"
                    . "<strong>Temporary Password:</strong> $password</p>"
                    . "<table width='100%' cellspacing='0' cellpadding='0'><tr>"
                    . "<td align='center' style='padding:15px'>"
                    . "<table cellspacing='0' cellpadding='0'><tr>"
                    . "<td align='center' bgcolor='#83C5E5' style='border-radius:5px'>"
                    . "<a href='$resetUrl' target='_blank' "
                    . "style='display:inline-block;padding:12px 25px;color:#ffffff;"
                    . "text-decoration:none;font-weight:bold;border:1px solid #73b5d5;"
                    . "border-radius:5px'>Reset Password</a>"
                    . "</td></tr></table></td></tr></table>"
                    . "<p style='color:#ff0000;text-align:center'>"
                    . "<strong>Important:</strong> Change password immediately</p>"
                    . "<p style='color:#666666;font-size:12px;text-align:center'>"
                    . "Can't click? Copy: $resetUrl</p>";
            
            $this->mailer->Body = $this->getEmailTemplate($content);
            $this->mailer->AltBody = "Welcome to Turing Tickets!\n\n"
                                  . "Username: $username\n"
                                  . "Temporary Password: $password\n\n"
                                  . "Reset your password at: $resetUrl";
            
            return $this->sendEmail();
        } catch (Exception $e) {
            error_log("Admin welcome email error: " . $e->getMessage());
            return false;
        }
    }
}