<?php
/**
 * PHPMailer Configuration File
 * 
 * This file contains SMTP settings for sending emails using Gmail SMTP server.
 * To set up:
 * 1. Create a Gmail account or use an existing one
 * 2. Enable 2-factor authentication in your Gmail account
 * 3. Generate an App Password: Gmail Settings -> Security -> App Passwords
 * 4. Replace the placeholder values below with your credentials
 * 
 * Security Note: Never commit actual credentials to version control.
 * Consider using environment variables for production deployment.
 */

return [
    'smtp_host' => 'smtp.gmail.com',      // Gmail SMTP server
    'smtp_auth' => true,                  // Enable SMTP authentication
    'smtp_username' => 'yourgmailaddress@gmail.com',  // Your Gmail address
    'smtp_password' => 'yourapppassword', // Your Gmail App Password (16 characters)
    'smtp_secure' => 'tls',              // Enable TLS encryption
    'smtp_port' => 587,                  // TLS port
    'from_email' => 'yourgmailaddress@gmail.com',  // Sender email address
    'from_name' => 'Turing Tickets Support'        // Sender name
];