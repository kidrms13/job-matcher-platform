<?php
require 'db.php';
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $conn->real_escape_string($_POST['email']);
    $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);

    $check_email = $conn->query("SELECT * FROM users WHERE email = '$email'");
    
    if ($check_email->num_rows == 1) {
        $conn->query("UPDATE users SET password = '$new_password' WHERE email = '$email'");
        $message = "<div class='success'>Password updated! <a href='login.php'>Login here</a></div>";
    } else {
        $message = "<div class='error'>Email not found in our system.</div>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password - Job Matcher</title>
    <style>
        body { font-family: Arial; background-color: #f3f2ef; display: flex; justify-content: center; padding-top: 50px; }
        .box { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); width: 350px; }
        h2 { color: #0a66c2; text-align: center; }
        input, button { width: 100%; padding: 10px; margin: 10px 0; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;}
        button { background-color: #0a66c2; color: white; font-weight: bold; border: none; cursor: pointer; }
        button:hover { background-color: #004182; }
        .success { color: green; text-align: center; margin-bottom: 10px; }
        .error { color: red; text-align: center; margin-bottom: 10px; }
        .links { text-align: center; font-size: 14px; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="box">
        <h2>Reset Password</h2>
        <p style="text-align:center; font-size: 14px; color: #666;">For this local MVP, enter your email to set a new password.</p>
        <?php echo $message; ?>
        <form method="POST">
            <input type="email" name="email" placeholder="Registered Email Address" required>
            <input type="password" name="new_password" placeholder="New Password" required>
            <button type="submit">Update Password</button>
        </form>
        <div class="links">
            <a href="login.php">Back to Login</a>
        </div>
    </div>
</body>
</html>