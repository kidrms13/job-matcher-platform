<?php
session_start();
require 'db.php';

// Security check: Must be an employer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employer') {
    header("Location: login.php");
    exit();
}

$employer_id = $_SESSION['user_id'];
$job_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = "";

// Security check: Verify this job actually belongs to THIS employer
$check_query = $conn->query("SELECT * FROM jobs WHERE id = $job_id AND employer_id = $employer_id");
if ($check_query->num_rows == 0) {
    die("Error: Job not found or you do not have permission to edit it. <a href='employer_dashboard.php'>Go Back</a>");
}
$job = $check_query->fetch_assoc();

// Handle the Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_job'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $company = $conn->real_escape_string($_POST['company']);
    $requirements = $conn->real_escape_string($_POST['requirements']);
    
    $sql = "UPDATE jobs SET title = '$title', company_name = '$company', requirements = '$requirements' WHERE id = $job_id AND employer_id = $employer_id";
    if ($conn->query($sql)) {
        $message = "<div class='success'>Job updated successfully! The matching engine will now use these new requirements.</div>";
        // Refresh job data to show updates in the form
        $job['title'] = $title;
        $job['company_name'] = $company;
        $job['requirements'] = $requirements;
    } else {
        $message = "<div class='error'>Error updating job.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit Job - <?php echo htmlspecialchars($job['title']); ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f2ee; margin: 0; color: #333; }
        .navbar { background-color: #fff; padding: 15px 40px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .navbar h2 { margin: 0; color: #0a66c2; font-size: 24px; }
        .container { max-width: 600px; margin: 40px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        input, textarea, button { width: 100%; padding: 12px; margin: 8px 0 15px 0; box-sizing: border-box; border: 1px solid #ccc; border-radius: 5px; font-family: inherit;}
        button { background-color: #0a66c2; color: white; border: none; cursor: pointer; font-weight: bold; border-radius: 25px; transition: 0.3s;}
        button:hover { background-color: #004182; }
        .back-btn { color: #666; text-decoration: none; font-weight: bold; display: inline-block; margin-bottom: 20px;}
        .success { color: green; background: #e6f4ea; padding: 10px; border-radius: 5px; margin-bottom: 15px; border: 1px solid #c3e6cb;}
    </style>
</head>
<body>

<div class="navbar"><h2>Job Matcher <span style="color:#666; font-size:16px;">| Employer Hub</span></h2></div>

<div class="container">
    <a href="employer_dashboard.php" class="back-btn">&larr; Back to Dashboard</a>
    <h2 style="margin-top: 0;">Edit Job Listing</h2>
    <?php echo $message; ?>
    
    <form method="POST">
        <label><strong>Job Title:</strong></label>
        <input type="text" name="title" value="<?php echo htmlspecialchars($job['title']); ?>" required>
        
        <label><strong>Company Name:</strong></label>
        <input type="text" name="company" value="<?php echo htmlspecialchars($job['company_name']); ?>" required>
        
        <label><strong>Skills & Requirements:</strong></label>
        <textarea name="requirements" rows="6" required><?php echo htmlspecialchars($job['requirements']); ?></textarea>
        
        <button type="submit" name="update_job">💾 Save Changes</button>
    </form>
</div>

</body>
</html>