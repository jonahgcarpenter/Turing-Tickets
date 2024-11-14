import os
import re

# Define the directory of your project
project_dir = r"U:\Jonah\School\24-25\CSCI 487\Project"

# Define old and new paths with appropriate relative paths using '../'
paths_to_update = {
    # admin folder (relative to the inner Project folder)
    "add_admin.php": r"./admin/add_admin.php",
    "admin_dashboard.html": r"./admin/admin_dashboard.html",
    "admin_dashboard.php": r"./admin/admin_dashboard.php",
    "admin_login.html": r"./admin/admin_login.html",
    "delete_user.php": r"./admin/delete_user.php",
    
    # auth folder (relative to the inner Project folder)
    "logout.php": r"./auth/logout.php",
    "reset_password.html": r"./auth/reset_password.html",
    "reset_password.php": r"./auth/reset_password.php",
    
    # config folder (relative to the inner Project folder)
    "connect.php": r"./config/connect.php",
    "database.php": r"./config/database.php",
    "database.sql": r"./config/database.sql",
    
    # js folder (relative to the inner Project folder)
    "login.js": r"./js/login.js",
    "script.js": r"./js/script.js",
    
    # tickets folder (relative to the inner Project folder)
    "add_response.php": r"./tickets/add_response.php",
    "close_ticket.php": r"./tickets/close_ticket.php",
    "reopen_ticket.php": r"./tickets/reopen_ticket.php",
    "submit_ticket.html": r"./tickets/submit_ticket.html",
    "submit_ticket.php": r"./tickets/submit_ticket.php",
    "successfully_submitted.html": r"./tickets/successfully_submitted.html",
    "update_ticket.php": r"./tickets/update_ticket.php",
    
    # Root directory files in the outer Project folder (relative to inner Project)
    "styles.css": r"../styles.css",
    "default.html": r"../index.html",
}

# Specify file types to include in the search
file_types = (".php", ".html", ".js", ".css")  # Add other file types if needed

# Function to update paths in a single file
def update_file_paths(file_path, paths_dict):
    # Read the content of the file
    with open(file_path, "r") as file:
        content = file.read()

    # Replace old paths with new paths using regular expressions
    updated_content = content
    for old_path, new_path in paths_dict.items():
        # Remove all occurrences of the old path and replace with new path
        updated_content = re.sub(rf"\b{old_path}\b", new_path, updated_content)

    # Write the updated content back to the file only if changes were made
    if content != updated_content:
        with open(file_path, "w") as file:
            file.write(updated_content)
        print(f"Updated paths in: {file_path}")

# Walk through each file in the project directory
for subdir, _, files in os.walk(project_dir):
    for file in files:
        # Process only specified file types
        if file.endswith(file_types):
            file_path = os.path.join(subdir, file)
            # Update file paths within each file
            update_file_paths(file_path, paths_to_update)

print("File path updates complete!")
