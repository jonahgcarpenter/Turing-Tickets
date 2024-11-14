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
