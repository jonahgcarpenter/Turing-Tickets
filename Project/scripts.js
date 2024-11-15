// Handle Category Dropdown Keydown Events
const categoryDropdown = document.getElementById("category");
if (categoryDropdown) {
    categoryDropdown.addEventListener("keydown", function(event) {
        if (event.key === "Enter") {
            event.preventDefault();
            
            // Toggle dropdown open/close
            if (!categoryDropdown.hasAttribute("size")) {
                categoryDropdown.setAttribute("size", categoryDropdown.options.length); // Open dropdown
            } else {
                categoryDropdown.removeAttribute("size"); // Close dropdown
                
                // Move to the next input field
                const form = event.target.form;
                const index = Array.prototype.indexOf.call(form, categoryDropdown);
                if (form.elements[index + 1]) {
                    form.elements[index + 1].focus();
                }
            }
        } else if (event.key === "ArrowDown" && categoryDropdown.hasAttribute("size")) {
            event.preventDefault();
            categoryDropdown.selectedIndex = Math.min(categoryDropdown.selectedIndex + 1, categoryDropdown.options.length - 1);
        } else if (event.key === "ArrowUp" && categoryDropdown.hasAttribute("size")) {
            event.preventDefault();
            categoryDropdown.selectedIndex = Math.max(categoryDropdown.selectedIndex - 1, 1); // Skip the disabled placeholder
        } else if (event.key === "Escape" && categoryDropdown.hasAttribute("size")) {
            event.preventDefault();
            categoryDropdown.removeAttribute("size"); // Close dropdown
        }
    });
}

// Handle Ticket Submission
const submitButton = document.getElementById("submitTicketButton");
if (submitButton) {
    submitButton.addEventListener("click", async function(event) {
        event.preventDefault();
        const ticketForm = document.getElementById("ticketForm");
        const formData = new FormData(ticketForm);

        try {
            const response = await fetch('Project/php/submit_ticket.php', { // Corrected path
                method: 'POST',
                body: formData
            });

            const text = await response.text(); // Get the raw response as text
            console.log("Raw response:", text); // Log the raw response
            let result;

            try {
                result = JSON.parse(text); // Try parsing the text as JSON
            } catch (error) {
                console.error("Unexpected response:", text);
                alert("An error occurred: " + text); // Show the raw response in case of an error
                return;
            }

            if (result.success) {
                alert(result.message);
                location.reload(); // Reload the page after successful submission
            } else {
                alert(result.error);
            }
        } catch (error) {
            console.error("Error submitting ticket:", error);
            alert("An unexpected error occurred while submitting the ticket.");
        }
    });
}

// Define the loadAdminTable function
async function loadAdminTable() {
    try {
        const response = await fetch('../php/fetch_admins.php', {
            method: 'GET'
        });
        const result = await response.json();

        if (result.success) {
            const adminTableBody = document.getElementById('adminTableBody');
            adminTableBody.innerHTML = ''; // Clear any existing rows

            result.admins.forEach(admin => {
                console.log("Loaded admin with ID:", admin.id); // Log the ID to verify it is present
                const row = document.createElement('tr');
                row.setAttribute('data-id', admin.id);

                // Username cell
                const usernameCell = document.createElement('td');
                usernameCell.textContent = admin.username;
                row.appendChild(usernameCell);

                // Email cell
                const emailCell = document.createElement('td');
                emailCell.textContent = admin.email;
                row.appendChild(emailCell);

                // Action cell with delete button
                const actionCell = document.createElement('td');
                const deleteButton = document.createElement('button');
                deleteButton.textContent = ' ';
                deleteButton.classList.add('delete-btn');
                deleteButton.onclick = () => confirmDelete(admin.id);
                actionCell.appendChild(deleteButton);
                row.appendChild(actionCell);

                adminTableBody.appendChild(row);
            });
        } else {
            alert(result.error);
        }
    } catch (error) {
        console.error("Error loading admin data:", error);
        alert("An unexpected error occurred while loading admin data.");
    }
}

// Check if 'adminTableBody' exists and load the admin table
const adminTableBody = document.getElementById('adminTableBody');
if (adminTableBody) {
    document.addEventListener("DOMContentLoaded", loadAdminTable);
}

// Add Admin Form Submission Handling
const addAdminForm = document.getElementById("addAdminForm");
if (addAdminForm) {
    addAdminForm.addEventListener("submit", async function(event) {
        event.preventDefault();
        const formData = new FormData(addAdminForm);

        try {
            const response = await fetch('../auth/add_admin.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                alert("Admin added successfully!");
                addAdminToTable(result.admin); // Add the new admin to the table without reloading
                addAdminForm.reset(); // Clear the form after success
            } else {
                alert(result.error);
            }
        } catch (error) {
            console.error("Error adding admin:", error);
            alert("An unexpected error occurred while adding the admin.");
        }
    });
}

// Function to add a new admin row to the admin table dynamically
function addAdminToTable(admin) {
    const adminTableBody = document.getElementById("adminTableBody");

    const row = document.createElement("tr");
    row.setAttribute('data-id', admin.id); // Set data-id attribute for row

    // Username cell
    const usernameCell = document.createElement("td");
    usernameCell.textContent = admin.username;
    row.appendChild(usernameCell);

    // Email cell
    const emailCell = document.createElement("td");
    emailCell.textContent = admin.email;
    row.appendChild(emailCell);

    // Action cell with delete button
    const actionCell = document.createElement("td");
    const deleteButton = document.createElement("button");
    deleteButton.textContent = " ";
    deleteButton.classList.add("delete-btn");
    deleteButton.onclick = () => confirmDelete(admin.id);
    actionCell.appendChild(deleteButton);
    row.appendChild(actionCell);

    adminTableBody.appendChild(row);
}

// Function to confirm and delete an admin
async function confirmDelete(adminId) {
    if (confirm("Are you sure you want to delete this admin?")) {
        console.log("Deleting admin with ID:", adminId); // Log the ID to ensure it is correct
        try {
            const response = await fetch('../auth/delete_admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({ id: adminId })
            });
            const result = await response.json();

            if (result.success) {
                alert(result.message);
                removeAdminFromTable(adminId); // Remove the admin row from the table
            } else {
                alert(result.error);
            }
        } catch (error) {
            console.error("Error deleting admin:", error);
            alert("An unexpected error occurred while deleting the admin.");
        }
    }
}

// Function to remove the admin row from the table
function removeAdminFromTable(adminId) {
    const row = document.querySelector(`#adminTableBody tr[data-id='${adminId}']`);
    if (row) {
        row.remove();
    }
}

// Reset Password Form Submission Handling
const resetPasswordForm = document.getElementById("resetPasswordForm");
if (resetPasswordForm) {
    resetPasswordForm.addEventListener("submit", submitForm);
    async function submitForm(event) {
        event.preventDefault();
        const formData = new FormData(resetPasswordForm);

        try {
            const response = await fetch('../auth/reset_password.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                alert(result.message);
                window.location.href = 'admin_login.html';
            } else {
                alert(result.error);
            }
        } catch (error) {
            console.error("Error resetting password:", error);
            alert("An unexpected error occurred while resetting the password.");
        }
    }
}

// Ticket Table Fetch
document.addEventListener("DOMContentLoaded", () => {
    // Attach event listeners to filter and sort options
    document.getElementById("search-button").addEventListener("click", populateTicketTable);
    document.getElementById("sort-tickets").addEventListener("change", populateTicketTable);
    document.getElementById("filter-status").addEventListener("change", populateTicketTable);

    // Initial population of the ticket table
    populateTicketTable();
});

async function populateTicketTable() {
    try {
        // Get filter, sort, and search input values
        const searchInput = document.getElementById("search-ticket").value.trim();
        const sortOption = document.getElementById("sort-tickets").value;
        const filterOption = document.getElementById("filter-status").value;

        // Construct URL with query parameters for filtering and sorting if supported by the backend
        let url = `../php/fetch_tickets.php?`;
        if (filterOption) url += `status=${filterOption}&`;
        if (sortOption) url += `sort=${sortOption}&`;
        if (searchInput) url += `ticket_id=${searchInput}`;

        // Fetch ticket data from the backend
        const response = await fetch(url);
        const data = await response.json();

        if (!data.error) {
            const ticketTableBody = document.getElementById('ticketTableBody');
            ticketTableBody.innerHTML = ''; // Clear existing rows

            // Apply filter and sorting on the client side if not handled on the backend
            const filteredTickets = data
                .filter(ticket => filterOption === '' || ticket.status === filterOption)
                .filter(ticket => searchInput === '' || ticket.id.toString().includes(searchInput))
                .sort((a, b) => {
                    if (sortOption === 'status') return a.status.localeCompare(b.status);
                    if (sortOption === 'updated-asc') return new Date(a.updated) - new Date(b.updated);
                    if (sortOption === 'updated-desc') return new Date(b.updated) - new Date(a.updated);
                    return 0;
                });

            // Populate the table with filtered and sorted tickets
            filteredTickets.forEach(addRow);
        } else {
            console.error('Failed to fetch tickets:', data.error);
        }
    } catch (error) {
        console.error('Error fetching tickets:', error);
    }
}

// Function to add rows dynamically with expand-on-click functionality
function addRow(ticketData) {
    const ticketTableBody = document.getElementById("ticketTableBody");

    // Create main row for ticket data
    const mainRow = document.createElement("tr");
    mainRow.classList.add("main-row");
    mainRow.innerHTML = `
        <td>${ticketData.id}</td>
        <td>${formatDateTime(ticketData.updated)}</td>
        <td>${ticketData.request_type || 'N/A'}</td>
        <td>${ticketData.request_title || 'N/A'}</td>
        <td>${ticketData.latest_notes || 'New'}</td>
        <td>${ticketData.status}</td>
    `;

    // Add click event to toggle expansion of the associated row
    mainRow.addEventListener("click", () => toggleExpand(mainRow));

    // Create expandable row for additional details from both ticket tables
    const expandableRow = document.createElement("tr");
    expandableRow.classList.add("expandable-row");
    expandableRow.innerHTML = `
        <td colspan="6">
            <strong>Ticket ID:</strong> ${ticketData.id} <br>
            <strong>Request Type:</strong> ${ticketData.request_type || 'N/A'} <br>
            <strong>Request Title:</strong> ${ticketData.request_title || 'N/A'} <br>
            <strong>Status:</strong> ${ticketData.status} <br>
            <strong>Last Updated:</strong> ${formatDateTime(ticketData.updated)} <br>
            <strong>Notes:</strong> <ul>
                ${ticketData.notes.map(note => `<li>${note.content} (Created: ${formatDateTime(note.created_at)})</li>`).join('')}
            </ul>
        </td>
    `;
    expandableRow.style.display = "none"; // Hide initially


    // Append both rows to the table body
    ticketTableBody.appendChild(mainRow);
    ticketTableBody.appendChild(expandableRow);
}

// Function to toggle row expansion
function toggleExpand(row) {
    console.log("Toggling expand for row:", row);
    const expandableRow = row.nextElementSibling;

    if (expandableRow && expandableRow.classList.contains("expandable-row")) {
        expandableRow.classList.toggle("expanded");
        expandableRow.style.display = expandableRow.style.display === "none" ? "table-row" : "none";
        console.log("Row toggled:", expandableRow);
    } else {
        console.error("Expandable row not found or incorrect structure:", row);
    }
}

// Utility function to format date and time
function formatDateTime(dateTimeStr) {
    const dateObj = new Date(dateTimeStr);
    if (isNaN(dateObj.getTime())) return 'Invalid Date';
    const hours = dateObj.getHours() % 12 || 12;
    const minutes = String(dateObj.getMinutes()).padStart(2, '0');
    const ampm = dateObj.getHours() >= 12 ? 'PM' : 'AM';
    return `${hours}:${minutes} ${ampm} ${dateObj.getMonth() + 1}/${dateObj.getDate()}/${dateObj.getFullYear()}`;
}
