<?php
session_start();

session_unset();
session_destroy();

header("Location: admin/Project\admin\admin_login.html");
exit;
?>
