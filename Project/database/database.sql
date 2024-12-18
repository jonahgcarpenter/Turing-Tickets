/*
 * This schema creates a ticket management system with the following features:
 * - User authentication and authorization
 * - Ticket creation and management
 * - Response tracking
 * - Automated archiving system for closed tickets
 * Jonah Carpenter - Turing Tickets
 */

-- Clear existing data and tables for clean installation
DELETE FROM responses WHERE ticket_id IN (SELECT id FROM tickets);
DELETE FROM tickets;
DELETE FROM users;

DROP TABLE IF EXISTS closed_responses;
DROP TABLE IF EXISTS closed_tickets;
DROP TABLE IF EXISTS responses;
DROP TABLE IF EXISTS tickets;
DROP TABLE IF EXISTS users;

/*
 * Users Table
 * Purpose: Stores administrator account information
 * Features:
 * - Unique usernames and emails
 * - Hashed passwords for security
 * - Role-based access control (currently admin-only)
 * - Timestamp tracking for account creation
 */
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    role ENUM('admin') NOT NULL DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default admin user (root/root)
-- Username: root
-- Password: root (but hashed)
-- Email: Update for your needs
INSERT INTO users (username, password, email, role) 
VALUES ('root', '$2y$10$uo7ILP4FLQYc1TAKyf31WORKKVH99rAzBBeGCcJuq0Ykt93uQlHE6', 'yourgmailaddress@gmail.com', 'admin');

/*
 * Tickets Table
 * Purpose: Manages active support tickets
 * Features:
 * - Categorized tickets for better organization
 * - Status tracking through ticket lifecycle
 * - Timestamps for creation and updates
 * - Essential contact information storage
 */
CREATE TABLE tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    category ENUM('login-issue', 'password-reset', 'ip-block', 'other') NOT NULL,
    description TEXT NOT NULL,
    status ENUM('open', 'in-progress', 'awaiting-response', 'closed') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

/*
 * Responses Table
 * Purpose: Tracks all communications on active tickets
 * Features:
 * - Links responses to specific tickets and admins
 * - Maintains chronological order of responses
 * - Cascade deletion with tickets
 */
CREATE TABLE responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT,
    admin_id INT,
    response TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
);

/*
 * Closed Tickets Table
 * Purpose: Archives completed tickets for record keeping
 * Features:
 * - Preserves all original ticket data
 * - Adds closure timestamp
 * - Separates active and completed tickets for better performance
 */
CREATE TABLE closed_tickets (
    id INT PRIMARY KEY, -- Same ticket ID
    title VARCHAR(100) NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    category ENUM('login-issue', 'password-reset', 'ip-block', 'other') NOT NULL,
    description TEXT NOT NULL,
    status ENUM('closed') NOT NULL DEFAULT 'closed',
    closed_date DATETIME DEFAULT CURRENT_TIMESTAMP, -- Store full date and time
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

/*
 * Closed Responses Table
 * Purpose: Archives responses from completed tickets
 * Features:
 * - Maintains full conversation history
 * - Links to archived tickets
 * - Preserves admin accountability
 */
CREATE TABLE closed_responses (
    id INT PRIMARY KEY, -- Same response ID
    ticket_id INT,
    admin_id INT,
    response TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES closed_tickets(id),
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
);