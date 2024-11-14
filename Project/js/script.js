document.addEventListener('DOMContentLoaded', function () {
    const submitTicketSection = document.getElementById('submit-ticket');
    const ticketForm = document.getElementById('ticket-form');
    const ticketList = document.getElementById('ticket-list');
    const logoutBtn = document.getElementById('logout-btn');
    const adminLoginBtn = document.getElementById('admin-login-btn');

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
        ticketForm.addEventListener('submit', function(event) {
            event.preventDefault();

            const formData = new FormData(ticketForm);

            fetch('../tickets/submit_ticket.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Ticket Submitted!');
                    ticketForm.reset();
                } else {
                    alert('Error submitting the ticket.');
                }
            });
        });
    }

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