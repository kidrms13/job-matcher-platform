<?php
session_start();
require 'db.php';
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    $result = $conn->query("SELECT * FROM users WHERE email = '$email'");

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // Verify the scrambled password
        if (password_verify($password, $user['password'])) {
            // Start the session and store user info
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            
            // Check role and redirect to the correct dashboard!
            if ($user['role'] == 'applicant') {
                header("Location: applicant_dashboard.php");
            } else {
                header("Location: employer_dashboard.php");
            }
            exit();
        } else {
            $error = "Incorrect password.";
        }
    } else {
        $error = "No account found with that email.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login - Job Matcher</title>
    <style>
        body { font-family: Arial; background-color: #f3f2ef; display: flex; justify-content: center; padding-top: 50px; }
        .box { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); width: 350px; }
        h2 { color: #0a66c2; text-align: center; }
        input, button { width: 100%; padding: 10px; margin: 10px 0; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;}
        button { background-color: #0a66c2; color: white; font-weight: bold; border: none; cursor: pointer; }
        button:hover { background-color: #004182; }
        .error { color: red; text-align: center; margin-bottom: 10px; font-weight: bold; }
        .links { text-align: center; font-size: 14px; margin-top: 15px; display: flex; flex-direction: column; gap: 8px;}
    </style>
</head>
<body>
    <div class="box">
        <h2>Welcome Back</h2>
        <?php if($error) echo "<div class='error'>$error</div>"; ?>
        <form method="POST">
            <input type="email" name="email" placeholder="Email Address" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
        <div class="links">
            <a href="forgot.php">Forgot Password?</a>
            <a href="register.php">Need an account? Register</a>
        </div>
    </div>
</body>
</html>