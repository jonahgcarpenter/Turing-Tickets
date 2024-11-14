// Declare global variables
let ticketsContainer, sortTicketsSelect, filterStatusSelect, searchTicketInput;

document.addEventListener('DOMContentLoaded', function () {
    console.log("DOM fully loaded and parsed");

    // Initialize global variables
    ticketsContainer = document.getElementById('tickets-container');
    sortTicketsSelect = document.getElementById('sort-tickets');
    filterStatusSelect = document.getElementById('filter-status');
    searchTicketInput = document.getElementById('search-ticket'); // Assign the search input element

    // Log to ensure searchTicketInput is assigned correctly
    if (searchTicketInput) {
        console.log("searchTicketInput found:", searchTicketInput);
    } else {
        console.error("searchTicketInput not found. Check the HTML element ID.");
    }

    // Element selectors
    const submitTicketSection = document.getElementById('submit-ticket');
    const ticketForm = document.getElementById('ticket-form');
    const ticketList = document.getElementById('ticket-list');
    const logoutBtn = document.getElementById('logout-btn');
    const adminLoginBtn = document.getElementById('admin-login-btn');
    const loginForm = document.getElementById('login-form');
    const loginError = document.getElementById('login-error');
    const homeBtn = document.getElementById('home-btn');
    const ticketIdElement = document.getElementById('ticket-id');

    // Initialize sorting and filtering if elements exist
    if (sortTicketsSelect && filterStatusSelect) {
        initializeTicketSortingAndFiltering(sortTicketsSelect, filterStatusSelect);
    } else {
        console.error("Sort or filter element not found.");
    }

    // Other event listeners and logic
    if (ticketForm) {
        ticketForm.addEventListener('submit', handleTicketSubmission);
    }

    if (loginForm) {
        loginForm.addEventListener('submit', handleLoginForm);
    }

    if (homeBtn) {
        homeBtn.addEventListener('click', () => {
            window.location.href = '../../index.html';
        });
    }

    if (ticketIdElement) {
        displayTicketId();
    }

    // Attach search function to the search button
    if (searchTicketInput) {
        window.searchTicket = searchTicket; // Ensure searchTicket is accessible globally
    }

    const adminForm = document.getElementById('admin-form');
    if (adminForm) {
        adminForm.addEventListener('submit', addAdmin);
    }
});

/**
 * Handle ticket submission form
 */
function handleTicketSubmission(event) {
    event.preventDefault();
    const formData = new FormData(event.target);

    fetch('submit_ticket.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        console.log("Raw response:", text);
        try {
            const data = JSON.parse(text);
            if (data.success) {
                alert('Ticket Submitted!');
                event.target.reset();
                window.location.href = 'successfully_submitted.html?ticket_id=' + data.ticket_id;
            } else {
                alert('Error submitting the ticket.');
                console.error("Server response error:", data);
            }
        } catch (e) {
            console.error("JSON parsing error:", e);
            alert("An error occurred while processing the server response.");
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        alert('An error occurred while submitting the ticket.');
    });
}

/**
 * Handle login form submission
 */
function handleLoginForm(event) {
    event.preventDefault();
    const formData = new FormData(event.target);

    fetch('../auth/login.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.json();
    })
    .then(data => {
        if (data.success) {
            window.location.href = '../admin/admin_dashboard.html';
        } else {
            loginError.textContent = data.error;
            loginError.style.display = 'block';
        }
    })
    .catch(error => {
        loginError.textContent = 'An error occurred. Please try again.';
        loginError.style.display = 'block';
        console.error('Error:', error);
    });
}

/**
 * Initialize sorting and filtering for tickets
 */
function initializeTicketSortingAndFiltering(sortTicketsSelect, filterStatusSelect) {
    fetchTickets(sortTicketsSelect.value, filterStatusSelect.value);

    sortTicketsSelect.addEventListener('change', () => {
        fetchTickets(sortTicketsSelect.value, filterStatusSelect.value);
    });

    filterStatusSelect.addEventListener('change', () => {
        fetchTickets(sortTicketsSelect.value, filterStatusSelect.value);
    });
}

/**
 * Fetch and display tickets based on sort order and filter
 */
function fetchTickets(sortOrder, statusFilter) {
    let url = `admin_dashboard.php?${statusFilter ? `status=${statusFilter}&` : ''}`;

    if (sortOrder === 'status') url += 'sort=status';
    else if (sortOrder === 'updated-asc') url += 'sort=updated-asc';
    else url += 'sort=updated-desc';

    fetch(url)
    .then(response => {
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        return response.json();
    })
    .then(data => {
        if (data.redirect) {
            window.location.href = data.redirect;
            return;
        }
        if (data.success === false) {
            console.error('Authorization error or data fetching issue.');
            return;
        }
        displayTickets(data.tickets); // No need to pass ticketsContainer
    })
    .catch(error => console.error('Error fetching tickets:', error));
}

/**
 * Search for a ticket by ID
 */
function searchTicket() {
    // Check if searchTicketInput is available
    if (!searchTicketInput) {
        console.error("searchTicketInput is not defined. Ensure it is correctly assigned.");
        return;
    }

    const ticketId = searchTicketInput.value.trim();
    if (!ticketId) {
        fetchTickets(sortTicketsSelect.value, filterStatusSelect.value);
        return;
    }

    fetch(`admin_dashboard.php?ticket_id=${ticketId}`)
    .then(response => {
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        return response.json();
    })
    .then(data => {
        displayTickets(data.tickets); // No need to pass ticketsContainer
    })
    .catch(error => console.error('Error searching for ticket:', error));
}

/**
 * Display a list of tickets
 */
function displayTickets(tickets) {
    ticketsContainer.innerHTML = ''; // Access the global ticketsContainer

    tickets.forEach(ticket => {
        const statusClass = `status-${ticket.status.replace(/_/g, '-')}`;
        ticketsContainer.insertAdjacentHTML('beforeend', `
            <tr class="ticket-row" onclick="toggleExpand(this)">
                <td>${ticket.id}</td>
                <td>${formatDateTime(ticket.updated)}</td>
                <td>${ticket.category}</td>
                <td>${ticket.title}</td>
                <td>${ticket.latest_note || 'No notes'}</td>
                <td><span class="status ${statusClass}">${ticket.status}</span></td>
            </tr>
            <tr class="expandable-content">
                <td colspan="6">
                    <div class="expanded-details">
                        <h3>Ticket Details</h3>
                        <label>Ticket Number:</label> ${ticket.id}<br>
                        <label>Created At:</label> ${formatDateTime(ticket.created)}<br>
                        <label>Updated At:</label> ${formatDateTime(ticket.updated)}<br>
                        <label>Request Type:</label> ${ticket.category}<br>
                        <label>Name:</label> ${ticket.name}<br>
                        <label>Email:</label> ${ticket.email}<br>
                        
                        <div class="description-section">
                            <h3>Description</h3>
                            <p>${ticket.description}</p>
                        </div>
                        
                        <div class="notes-section">
                            <h3>Notes</h3>
                            ${ticket.notes ? ticket.notes.split(';').map(note => {
                                const [response, created_at] = note.split('::');
                                return `<div class="note"><p>${response}</p><span class="note-time">${formatDateTime(created_at)}</span></div>`;
                            }).join('') : '<p>No notes available</p>'}
                        </div>
                        
                        <div class="status-section">
                            <label for="status-update-${ticket.id}">Update Status:</label>
                            <select id="status-update-${ticket.id}" onchange="updateStatus(${ticket.id}, this.value)">
                                <option value="open" ${ticket.status === 'open' ? 'selected' : ''}>Open</option>
                                <option value="in-progress" ${ticket.status === 'in-progress' ? 'selected' : ''}>In Progress</option>
                                <option value="awaiting-response" ${ticket.status === 'awaiting-response' ? 'selected' : ''}>Awaiting Response</option>
                                <option value="closed" ${ticket.status === 'closed' ? 'selected' : ''}>Closed</option>
                            </select>
                        </div>
                        
                        <div class="response-section">
                            <label for="response-${ticket.id}">Add Response:</label>
                            <textarea id="response-${ticket.id}" rows="3"></textarea>
                            <button onclick="addResponse(${ticket.id})" class="save-button">Submit Response</button>
                        </div>
                    </div>
                </td>
            </tr>
        `);
    });
}

/**
 * Format date and time
 */
function formatDateTime(dateTimeStr) {
    const dateObj = new Date(dateTimeStr);
    if (isNaN(dateObj.getTime())) return 'Invalid Date';
    let hours = dateObj.getHours();
    const minutes = dateObj.getMinutes().toString().padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12 || 12;
    return `${hours}:${minutes} ${ampm} ${dateObj.getMonth() + 1}/${dateObj.getDate()}/${dateObj.getFullYear()}`;
}

/**
 * Display the ticket ID on the confirmation page
 */
function displayTicketId() {
    const urlParams = new URLSearchParams(window.location.search);
    const ticketId = urlParams.get('ticket_id');
    ticketIdElement.textContent = ticketId || 'N/A';
}

/**
 * Toggle the expansion of a ticket row
 */
function toggleExpand(row) {
    const nextRow = row.nextElementSibling;
    if (nextRow && nextRow.classList.contains('expandable-content')) {
        nextRow.classList.toggle('expanded');
    }
}

/**
 * Update ticket status
 */
function updateStatus(ticketId, newStatus) {
    let url = (newStatus === 'closed') ? '../tickets/close_ticket.php' : (newStatus === 'open') ? '../tickets/reopen_ticket.php' : '../tickets/update_ticket.php';

    console.log(`Updating status for Ticket ID: ${ticketId} to: ${newStatus}`); // Debug

    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ticket_id: ticketId, status: newStatus })
    })
    .then(response => {
        console.log("Status update response received:", response); // Debug
        return response.json();
    })
    .then(data => {
        console.log("Status update data:", data); // Debug
        if (data.success) {
            alert(`Status updated successfully to '${newStatus}' for Ticket ID: ${ticketId}`);
            fetchTickets(sortTicketsSelect.value, filterStatusSelect.value); // Refresh tickets
        } else {
            alert('Failed to update status. Please check the console for more details.');
            console.error("Status Update Failure:", data);
        }
    })
    .catch(error => {
        console.error('Error updating status:', error);
        alert('An error occurred while updating the status. See console for details.');
    });
}

/**
 * Add ticket response
 */
function addResponse(ticketId) {
    const responseText = document.getElementById(`response-${ticketId}`).value;

    console.log(`Adding response for Ticket ID: ${ticketId} with content:`, responseText); // Debug

    fetch('../tickets/add_response.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ticket_id: ticketId, response: responseText })
    })
    .then(response => {
        console.log("Add response received:", response); // Debug
        return response.json();
    })
    .then(data => {
        console.log("Add response data:", data); // Debug
        if (data.success) {
            alert(`Response added successfully for Ticket ID: ${ticketId}`);
            fetchTickets(sortTicketsSelect.value, filterStatusSelect.value); // Refresh tickets
        } else {
            alert('Failed to add response. Please check the console for more details.');
            console.error("Add Response Failure:", data);
        }
    })
    .catch(error => {
        console.error('Error adding response:', error);
        alert('An error occurred while adding the response. See console for details.');
    });
}

/**
 * Add admin
 */
function addAdmin(event) {
    event.preventDefault();

    const adminForm = document.getElementById('admin-form');
    const formData = new FormData(adminForm);

    console.log("Adding new admin with data:", Array.from(formData.entries())); // Debug

    fetch('../admin/add_admin.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log("Add Admin Response:", data); // Debug
        if (data.success) {
            alert('Admin added successfully.');
            adminForm.reset();
            // Optionally, update the page or redirect
        } else {
            alert('Failed to add admin. Please check the console for more details.');
            console.error("Add Admin Failure:", data);
        }
    })
    .catch(error => {
        console.error('Error adding admin:', error);
        alert('An error occurred while adding the admin. See console for details.');
    });
}

function confirmDelete(adminId) {
    if (confirm('Are you sure you want to delete this admin?')) {
        window.location.href = '../admin/delete_user.php?id=' + adminId;
    }
}