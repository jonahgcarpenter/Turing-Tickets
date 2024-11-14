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