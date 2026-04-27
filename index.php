<?php
session_start();

// 1. If the user is NOT logged in, send them to the login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. If they ARE logged in, check their role and send them to their specific Home Screen
if ($_SESSION['role'] == 'applicant') {
    header("Location: applicant_dashboard.php");
} else {
    header("Location: employer_dashboard.php");
}
exit();
?>