<?php
session_start();
// Destroy all session data
session_unset();
session_destroy();

// Output JavaScript to show alert and redirect
echo "<script>
        alert('Logout Successful!');
        window.location.href = '../html/admin_login.html';
      </script>";
exit();
?>
