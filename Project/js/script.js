document.addEventListener('DOMContentLoaded', function () {
    const submitTicketSection = document.getElementById('submit-ticket');
    const ticketForm = document.getElementById('ticket-form');
    const ticketList = document.getElementById('ticket-list');
    const logoutBtn = document.getElementById('logout-btn');
    const adminLoginBtn = document.getElementById('admin-login-btn');

    // Remove or comment out the user role check
    /*
    const user = JSON.parse(sessionStorage.getItem('loggedInUser'));

    if (user && user.role === 'admin') {
        submitTicketSection.style.display = 'none';
        fetch('get_tickets.php')
            .then(response => response.json())
            .then(data => {
                data.tickets.forEach(ticket => {
                    const ticketDiv = document.createElement('div');
                    ticketDiv.innerHTML = `
                        <h3>Ticket #${ticket.id}</h3>
                        <p><strong>Category:</strong> ${ticket.category}</p>
                        <p><strong>Description:</strong> ${ticket.description}</p>
                        <p><strong>Status:</strong> ${ticket.status}</p>
                    `;
                    ticketList.appendChild(ticketDiv);
                });
            });
    } else {
    */

    ticketForm.addEventListener('submit', function(event) {
        event.preventDefault();
    
        const formData = new FormData(ticketForm);
    
        fetch('submit_ticket.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text()) // Retrieve response as text for debugging
        .then(text => {
            console.log("Raw response:", text); // Log raw response to check its content
            try {
                const data = JSON.parse(text); // Attempt to parse JSON
                if (data.success) {
                    alert('Ticket Submitted!');
                    ticketForm.reset();
                    window.location.href = 'successfully_submitted.html?ticket_id=' + data.ticket_id;
                } else {
                    alert('Error submitting the ticket.');
                    console.error("Server response error:", data); // Log the response if success is false
                }
            } catch (e) {
                console.error("JSON parsing error:", e); // Log any JSON parsing errors
                console.error("Response was not valid JSON:", text); // Show the non-JSON response
                alert("An error occurred while processing the server response.");
            }
        })
        .catch(error => {
            console.error('Fetch error:', error); // Log fetch errors (e.g., network issues)
            alert('An error occurred while submitting the ticket.');
        });
    });       

    // }

    if (adminLoginBtn) {
        adminLoginBtn.addEventListener('click', function() {
            window.location.href = '../admin/admin_login.html';
        });
    }

    if (logoutBtn) {
        logoutBtn.addEventListener('click', function() {
            sessionStorage.removeItem('loggedInUser');
            window.location.href = '../admin/admin_login.html';
        });
    }
});

document.getElementById('home-btn').addEventListener('click', function() {
    window.location.href = '../../index.html';
});

document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('login-form');
    const loginError = document.getElementById('login-error');

    loginForm.addEventListener('submit', function(event) {
        event.preventDefault();

        const formData = new FormData(loginForm);

        fetch('../auth/login.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                sessionStorage.setItem('loggedInUser', JSON.stringify(data.user));
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
    });
});

function confirmDelete(adminId) {
    if (confirm('Are you sure you want to delete this admin?')) {
        window.location.href = '../admin/delete_user.php?id=' + adminId;
    }
}

// Define these selectors globally
const sortTicketsSelect = document.getElementById('sort-tickets');
const filterStatusSelect = document.getElementById('filter-status');

document.addEventListener('DOMContentLoaded', function() {
    console.log("DOM fully loaded and parsed");
    
    // Initial fetch
    fetchTickets(sortTicketsSelect.value, filterStatusSelect.value);

    // Event listeners for sorting and filtering
    sortTicketsSelect.addEventListener('change', () => {
        console.log("Sort by changed to:", sortTicketsSelect.value);
        fetchTickets(sortTicketsSelect.value, filterStatusSelect.value);
    });
    
    filterStatusSelect.addEventListener('change', () => {
        console.log("Filter by status changed to:", filterStatusSelect.value);
        fetchTickets(sortTicketsSelect.value, filterStatusSelect.value);
    });
});

function fetchTickets(sortOrder, statusFilter) {
    console.log("Fetching tickets with sortOrder:", sortOrder, "and statusFilter:", statusFilter);
    
    let url = `admin_dashboard.php?${statusFilter ? `status=${statusFilter}&` : ''}`;

    if (sortOrder === 'status') {
        url += 'sort=status';
    } else if (sortOrder === 'updated-asc') {
        url += 'sort=updated-asc';
    } else {
        url += 'sort=updated-desc';
    }

    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.redirect) {
                console.log("Redirecting to:", data.redirect);
                window.location.href = data.redirect;
                return;
            }
            if (data.success === false) {
                console.error('Authorization error or data fetching issue.');
                return;
            }
            console.log("Tickets fetched successfully:", data.tickets);
            displayTickets(data.tickets);
        })
        .catch(error => console.error('Error fetching tickets:', error));
}

window.searchTicket = function() {
    const ticketId = document.getElementById('search-ticket').value.trim();
    if (!ticketId) {
        console.log("No ticket ID entered, fetching all tickets.");
        fetchTickets(sortTicketsSelect.value, filterStatusSelect.value);
        return;
    }
    console.log("Searching for ticket ID:", ticketId);
    
    fetch(`admin_dashboard.php?ticket_id=${ticketId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (!data.success) {
                console.error("Not authenticated, redirecting to login page");
                window.location.href = 'admin_login.html';
                return;
            }
            console.log("Ticket search result:", data.tickets);
            displayTickets(data.tickets);
        })
        .catch(error => console.error('Error searching for ticket:', error));
};

function displayTickets(tickets) {
    console.log("Displaying tickets:", tickets);
    
    const ticketsContainer = document.getElementById('tickets-container');
    ticketsContainer.innerHTML = '';
    
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

function formatDateTime(dateTimeStr) {
    const dateObj = new Date(dateTimeStr);
    if (isNaN(dateObj.getTime())) return 'Invalid Date';
    let hours = dateObj.getHours();
    const minutes = dateObj.getMinutes().toString().padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12 || 12;
    const month = (dateObj.getMonth() + 1).toString().padStart(2, '0');
    const day = dateObj.getDate().toString().padStart(2, '0');
    const year = dateObj.getFullYear();
    return `${hours}:${minutes} ${ampm} ${month}/${day}/${year}`;
}

function updateStatus(ticketId, newStatus) {
    console.log("Updating status for ticket ID:", ticketId, "to new status:", newStatus);

    let url;
    if (newStatus === 'closed') {
        url = '../tickets/close_ticket.php';
    } else if (newStatus === 'open') {
        url = '../tickets/reopen_ticket.php';
    } else {
        url = '../tickets/update_ticket.php';
    }

    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ticket_id: ticketId, status: newStatus })
    })
    .then(response => response.json())
    .then(data => {
        console.log("Update status response:", data);
        if (data.success) {
            alert('Status updated successfully.');
            fetchTickets(sortTicketsSelect.value, filterStatusSelect.value); // Refresh tickets
        } else {
            alert('Failed to update status.');
        }
    })
    .catch(error => console.error('Error updating status:', error));
}


function addResponse(ticketId) {
    console.log("Adding response for ticket ID:", ticketId);
    
    const responseText = document.getElementById(`response-${ticketId}`).value;
    fetch('../tickets/add_response.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ticket_id: ticketId, response: responseText })
    })
    .then(response => response.json())
    .then(data => {
        console.log("Add response response:", data);
        if (data.success) {
            alert('Response added successfully.');
            fetchTickets(sortTicketsSelect.value, filterStatusSelect.value); // Refresh tickets
        } else {
            alert('Failed to add response.');
        }
    })
    .catch(error => console.error('Error adding response:', error));
}

window.toggleExpand = function(row) {
    console.log("Toggling expand for row:", row);
    
    const expandableRow = row.nextElementSibling;
    expandableRow.classList.toggle('expanded');
};

// Retrieve the ticket_id from the URL query parameters
const urlParams = new URLSearchParams(window.location.search);
const ticketId = urlParams.get('ticket_id');

// Display the ticket ID in the HTML
if (ticketId) {
    document.getElementById('ticket-id').textContent = ticketId;
} else {
    document.getElementById('ticket-id').textContent = 'N/A'; // Default message if ticket ID is missing
}