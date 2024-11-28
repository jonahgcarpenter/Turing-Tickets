<?php
require_once __DIR__ . '/../src/Exception.php';
require_once __DIR__ . '/../src/PHPMailer.php';
require_once __DIR__ . '/../src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailHandler {
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

    private function resetMailer() {
        $this->mailer->clearAddresses();
        $this->mailer->clearAllRecipients();
        $this->mailer->clearAttachments();
        $this->mailer->clearCustomHeaders();
        $this->mailer->Subject = '';
        $this->mailer->Body = '';
        $this->mailer->AltBody = '';
    }

    private function generateMessageId($ticketId) {
        $timestamp = time();
        $random = substr(md5(rand()), 0, 8);
        $domain = parse_url($this->config['smtp_host'], PHP_URL_HOST);
        return "<ticket-$ticketId-$timestamp-$random@$domain>";
    }

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

    private function getTicketData($ticketId) {
        // Try active tickets first
        $stmt = $this->db->prepare("
            SELECT 
                id as ticket_id,
                title,
                name,
                email,
                category,
                description,
                status,
                created_at,
                updated_at,
                NULL as closed_date
            FROM tickets 
            WHERE id = ?
        ");
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        // If not found, try closed tickets
        if (!$ticket) {
            $stmt = $this->db->prepare("
                SELECT 
                    id as ticket_id,
                    title,
                    name,
                    email,
                    category,
                    description,
                    status,
                    created_at,
                    updated_at,
                    closed_date
                FROM closed_tickets 
                WHERE id = ?
            ");
            $stmt->execute([$ticketId]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($ticket) {
            // Get responses based on ticket status
            $responses = ($ticket['status'] === 'closed') 
                ? $this->getClosedTicketResponses($ticketId)
                : $this->getActiveTicketResponses($ticketId);
            
            $ticket['responses'] = $responses;
        }

        return $ticket;
    }

    private function getActiveTicketResponses($ticketId) {
        $stmt = $this->db->prepare("
            SELECT 
                r.response as content,
                r.created_at,
                u.username as admin_username
            FROM responses r
            JOIN users u ON r.admin_id = u.id
            WHERE r.ticket_id = ?
            ORDER BY r.created_at ASC
        ");
        $stmt->execute([$ticketId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getClosedTicketResponses($ticketId) {
        $stmt = $this->db->prepare("
            SELECT 
                r.response as content,
                r.created_at,
                u.username as admin_username
            FROM closed_responses r
            JOIN users u ON r.admin_id = u.id
            WHERE r.ticket_id = ?
            ORDER BY r.created_at ASC
        ");
        $stmt->execute([$ticketId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            $this->mailer->Subject = "[Ticket #$ticketId] Status Updated: $newStatus";
            
            $this->getThreadHeaders($ticketId);
            
            $content = $this->generateTicketContent(
                $ticketData,
                "Status Update",
                "<p><strong>Updated by:</strong> $adminUsername</p>" .
                "<p>New Status: <strong>$newStatus</strong></p>"
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
            $ticketData = $this->getTicketData($ticketId); // Get fresh ticket data
            if (!$ticketData) return false;

            $this->resetMailer();
            $this->mailer->setFrom($this->config['from_email'], $this->config['from_name']);
            $this->mailer->addAddress($userEmail);
            $this->mailer->Subject = "[Ticket #{$ticketId}] New Response Added";
            
            $this->getThreadHeaders($ticketId);
            
            $content = $this->generateTicketContent(
                $ticketData,
                "New Response Added to Ticket",
                "<p><strong>Response from:</strong> $adminUsername</p>" .
                "<p><strong>New Response:</strong><br>" . nl2br(htmlspecialchars($response)) . "</p>"
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
                "<p style='color:#666;margin-top:15px'>Reply to this email if you need to reopen this ticket.</p>"
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