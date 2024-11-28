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

    public function sendNewTicketNotification($userEmail, $ticketDetails) {
        try {
            $this->resetMailer();
            
            $this->mailer->setFrom($this->config['from_email'], $this->config['from_name']);
            $this->mailer->addAddress($userEmail);
            $this->mailer->Subject = "[Ticket #{$ticketDetails['id']}] " . $ticketDetails['subject'];
            
            // Create the initial thread Message-ID
            $domain = parse_url($this->config['smtp_host'], PHP_URL_HOST);
            $threadId = "<ticket-{$ticketDetails['id']}@$domain>";
            $this->mailer->MessageID = $threadId;
            
            // Set thread headers for the initial message
            $this->mailer->addCustomHeader('Thread-Topic', "Ticket #{$ticketDetails['id']}");
            $this->mailer->addCustomHeader('Thread-Index', base64_encode(pack('H*', md5($ticketDetails['id']))));
            $this->mailer->addCustomHeader('X-Ticket-ID', $ticketDetails['id']);
            
            $content = "
                <h2>Your ticket has been created successfully!</h2>
                <p><strong>Ticket ID:</strong> {$ticketDetails['id']}</p>
                <p><strong>Subject:</strong> {$ticketDetails['subject']}</p>
                <p><strong>Description:</strong><br>{$ticketDetails['description']}</p>
                <p><strong>Status:</strong> {$ticketDetails['status']}</p>";
            
            $this->mailer->Body = $this->getEmailTemplate($content);
            $this->mailer->AltBody = strip_tags(str_replace(['<br>', '</p>'], "\n", $content));
            
            return $this->sendEmail();
        } catch (Exception $e) {
            error_log("New ticket notification error: " . $e->getMessage());
            return false;
        }
    }

    public function sendStatusChangeNotification($userEmail, $ticketId, $newStatus) {
        try {
            $this->resetMailer();
            $this->mailer->setFrom($this->config['from_email'], $this->config['from_name']);
            $this->mailer->addAddress($userEmail);
            $this->mailer->Subject = "[Ticket #$ticketId] Status Updated";
            
            // Add threading headers
            $this->getThreadHeaders($ticketId);
            
            $content = "
                <h2>Ticket Status Update</h2>
                <p>Your ticket status has been updated.</p>
                <p><strong>Ticket ID:</strong> $ticketId</p>
                <p><strong>New Status:</strong> $newStatus</p>";
            
            $this->mailer->Body = $this->getEmailTemplate($content);
            $this->mailer->AltBody = strip_tags(str_replace(['<br>', '</p>'], "\n", $content));
            
            return $this->sendEmail();
        } catch (Exception $e) {
            error_log("Status change notification error: " . $e->getMessage());
            return false;
        }
    }

    public function sendResponseNotification($userEmail, $ticketId, $response) {
        try {
            $this->resetMailer();
            $this->mailer->setFrom($this->config['from_email'], $this->config['from_name']);
            $this->mailer->addAddress($userEmail);
            $this->mailer->Subject = "[Ticket #$ticketId] New Response";
            
            // Add threading headers
            $this->getThreadHeaders($ticketId);
            
            $content = "
                <h2>New Response to Your Ticket</h2>
                <p><strong>Ticket ID:</strong> $ticketId</p>
                <p><strong>Response:</strong><br>" . nl2br(htmlspecialchars($response)) . "</p>";
            
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
            $this->resetMailer();
            $this->mailer->setFrom($this->config['from_email'], $this->config['from_name']);
            $this->mailer->addAddress($userEmail);
            $this->mailer->Subject = "[Ticket #$ticketId] Ticket Closed";
            
            $this->getThreadHeaders($ticketId);
            
            $content = "
                <h2>Ticket Has Been Closed</h2>
                <p>Your ticket has been marked as closed. Here's a summary of the ticket:</p>
                <div style='background-color: #f9f9f9; padding: 15px; border-left: 4px solid #003366; margin: 10px 0;'>
                    <p><strong>Ticket ID:</strong> $ticketId</p>
                    <p><strong>Title:</strong> {$ticketData['title']}</p>
                    <p><strong>Category:</strong> {$ticketData['category']}</p>
                    <p><strong>Created:</strong> {$ticketData['created_at']}</p>
                    <p><strong>Closed:</strong> {$ticketData['closed_date']}</p>
                    <p><strong>Description:</strong><br>{$ticketData['description']}</p>
                </div>
                <p>If you need to discuss this ticket further, you can reply to reopen it automatically.</p>";
            
            $this->mailer->Body = $this->getEmailTemplate($content);
            $this->mailer->AltBody = strip_tags(str_replace(['<br>', '</p>'], "\n", $content));
            
            return $this->sendEmail();
        } catch (Exception $e) {
            error_log("Ticket closure notification error: " . $e->getMessage());
            return false;
        }
    }

    public function sendTicketReopenedNotification($userEmail, $ticketId, $response) {
        try {
            $this->resetMailer();
            $this->mailer->setFrom($this->config['from_email'], $this->config['from_name']);
            $this->mailer->addAddress($userEmail);
            $this->mailer->Subject = "[Ticket #$ticketId] Ticket Reopened";
            
            $this->getThreadHeaders($ticketId);
            
            $content = "
                <h2>Ticket Has Been Reopened</h2>
                <p>This ticket has been automatically reopened due to a new response:</p>
                <div style='background-color: #f9f9f9; padding: 15px; border-left: 4px solid #003366; margin: 10px 0;'>
                    " . nl2br(htmlspecialchars($response)) . "
                </div>
                <p>The ticket status has been changed to 'open' and will be reviewed by our support team.</p>";
            
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