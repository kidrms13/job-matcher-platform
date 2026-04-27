<?php
session_start();
require 'db.php';

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'applicant') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$job_id = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;
$message = "";

// Fetch Job Details
$job_query = $conn->query("SELECT * FROM jobs WHERE id = $job_id");
if ($job_query->num_rows == 0) {
    die("Job not found. <a href='applicant_dashboard.php'>Go back</a>");
}
$job = $job_query->fetch_assoc();

// Check if user has already applied
$check_applied = $conn->query("SELECT * FROM applications WHERE job_id = $job_id AND applicant_id = $user_id");
$has_applied = ($check_applied->num_rows > 0);

// Handle the "Apply" button click
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apply_now'])) {
    if (!$has_applied) {
        $sql = "INSERT INTO applications (job_id, applicant_id) VALUES ($job_id, $user_id)";
        if ($conn->query($sql)) {
            $has_applied = true; // Update status so the button changes
            $message = "<div class='success'>🎉 Success! Your resume has been submitted for this position.</div>";
        } else {
            $message = "<div class='error'>Error submitting application.</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Apply - <?php echo htmlspecialchars($job['title']); ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f2ee; margin: 0; color: #333; }
        .navbar { background-color: #fff; padding: 15px 40px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .navbar h2 { margin: 0; color: #0a66c2; font-size: 24px; }
        
        .container { max-width: 800px; margin: 40px auto; background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .back-btn { display: inline-block; margin-bottom: 20px; color: #666; text-decoration: none; font-weight: bold; border: 1px solid #ccc; padding: 8px 15px; border-radius: 20px; transition: 0.3s;}
        .back-btn:hover { background-color: #f3f2ef; color: #333; }
        
        .job-header { border-bottom: 2px solid #f3f2ef; padding-bottom: 20px; margin-bottom: 20px; }
        .job-title { color: #0a66c2; font-size: 28px; margin: 0 0 10px 0; }
        .job-company { font-size: 18px; font-weight: bold; margin: 0; color: #555; }
        .job-reqs { background: #f9f9f9; padding: 20px; border-radius: 8px; line-height: 1.6; border: 1px solid #eee; margin-bottom: 30px;}
        
        .apply-btn { background-color: #0a66c2; color: white; border: none; padding: 15px 30px; font-size: 18px; font-weight: bold; border-radius: 30px; cursor: pointer; width: 100%; transition: 0.3s; }
        .apply-btn:hover { background-color: #004182; }
        .applied-badge { background-color: #d4edda; color: #155724; padding: 15px; text-align: center; border-radius: 8px; font-weight: bold; border: 1px solid #c3e6cb; }
        
        .success { color: green; background: #e6f4ea; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c3e6cb; font-weight: bold; text-align: center;}
    </style>
</head>
<body>

<div class="navbar">
    <h2>Job Matcher <span style="color:#666; font-size:16px;">| Application Portal</span></h2>
</div>

<div class="container">
    <a href="applicant_dashboard.php" class="back-btn">&larr; Back to Job Board</a>

    <?php echo $message; ?>

    <div class="job-header">
        <h1 class="job-title"><?php echo htmlspecialchars($job['title']); ?></h1>
        <p class="job-company">🏢 <?php echo htmlspecialchars($job['company_name']); ?></p>
        <p style="color: #999; font-size: 14px; margin-top: 5px;">Posted on: <?php echo date('F j, Y', strtotime($job['created_at'])); ?></p>
    </div>

    <h3>Requirements & Details</h3>
    <div class="job-reqs">
        <?php echo nl2br(htmlspecialchars($job['requirements'])); ?>
    </div>

    <?php if ($has_applied): ?>
        <div class="applied-badge">
            ✅ You have already applied for this position.
        </div>
    <?php else: ?>
        <form method="POST">
            <button type="submit" name="apply_now" class="apply-btn">Apply Now</button>
        </form>
        <p style="text-align: center; font-size: 13px; color: #666; margin-top: 10px;">Clicking apply will automatically send your uploaded resume to the employer.</p>
    <?php endif; ?>
</div>

</body>
</html>