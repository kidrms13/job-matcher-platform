<?php
require 'db.php';
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $role = $conn->real_escape_string($_POST['role']);
    
    // Security: Never save plain-text passwords! We hash them.
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); 

    // Check if email already exists
    $check_email = $conn->query("SELECT * FROM users WHERE email = '$email'");
    
    if ($check_email->num_rows > 0) {
        $message = "<div class='error'>Email is already registered!</div>";
    } else {
        $sql = "INSERT INTO users (name, email, password, role) VALUES ('$name', '$email', '$password', '$role')";
        if ($conn->query($sql) === TRUE) {
            $message = "<div class='success'>Registration successful! <a href='login.php'>Login here</a></div>";
        } else {
            $message = "<div class='error'>Error: " . $conn->error . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register - Job Matcher</title>
    <style>
        body { font-family: Arial; background-color: #f3f2ef; display: flex; justify-content: center; padding-top: 50px; }
        .box { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); width: 350px; }
        h2 { color: #0a66c2; text-align: center; }
        input, select, button { width: 100%; padding: 10px; margin: 10px 0; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;}
        button { background-color: #0a66c2; color: white; font-weight: bold; border: none; cursor: pointer; }
        button:hover { background-color: #004182; }
        .success { color: green; text-align: center; margin-bottom: 10px; }
        .error { color: red; text-align: center; margin-bottom: 10px; }
        .links { text-align: center; font-size: 14px; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="box">
        <h2>Create an Account</h2>
        <?php echo $message; ?>
        <form method="POST">
            <input type="text" name="name" placeholder="Full Name" required>
            <input type="email" name="email" placeholder="Email Address" required>
            <input type="password" name="password" placeholder="Password" required>
            
            <label for="role">I am an:</label>
            <select name="role" required>
                <option value="applicant">Applicant</option>
                <option value="employer">Employer</option>
            </select>
            
            <button type="submit">Register</button>
        </form>
        <div class="links">
            <a href="login.php">Already have an account? Login</a>
        </div>
    </div>
</body>
</html>