<?php
session_start();

// If the user is not logged in, redirect them to the login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>

<?php
$match_score = null;
$applicant_name = "";
$error_message = "";
$python_debug_output = ""; 

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['resume'])) {
    $applicant_name = htmlspecialchars($_POST['applicant_name']);
    
    $upload_dir = 'uploads/';
    $file_name = basename($_FILES['resume']['name']);
    $target_filepath = $upload_dir . time() . "_" . $file_name; 
    $file_type = strtolower(pathinfo($target_filepath, PATHINFO_EXTENSION));

    if ($file_type != "pdf") {
        $error_message = "Sorry, only PDF files are allowed.";
    } else {
        if (move_uploaded_file($_FILES['resume']['tmp_name'], $target_filepath)) {
            
            // THE BULLETPROOF COMMAND:
            // We are using your exact installation path and adding 2>&1 to catch any hidden errors.
            $command = '"C:\\Program Files\\Python314\\python.exe" matcher.py ' . escapeshellarg($target_filepath) . ' 2>&1';
            
            $output = shell_exec($command);
            
            if ($output !== null) {
                if (is_numeric(trim($output))) {
                    $match_score = trim($output);
                } else {
                    $error_message = "Python encountered an error.";
                    $python_debug_output = trim($output); 
                }
            } else {
                $error_message = "System failed to run Python entirely.";
            }
        } else {
            $error_message = "There was an error uploading your file.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Matching Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f3f2ef; padding: 20px; color: #333; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        h1 { color: #0a66c2; border-bottom: 2px solid #0a66c2; padding-bottom: 10px; }
        .job-card { background: #e8f4fd; padding: 20px; border-radius: 8px; margin-bottom: 25px; border-left: 5px solid #0a66c2; }
        form { background: #fafafa; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        label { font-weight: bold; display: block; margin-top: 10px; }
        input[type="text"], input[type="file"] { width: 100%; padding: 10px; margin: 8px 0 20px 0; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { background-color: #0a66c2; color: white; padding: 12px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; font-weight: bold; width: 100%; }
        button:hover { background-color: #004182; }
        .result { background: #d4edda; padding: 20px; border: 1px solid #c3e6cb; border-radius: 8px; margin-top: 25px; color: #155724; text-align: center; }
        .score-text { font-size: 24px; font-weight: bold; color: #0a66c2; }
        .error { color: #721c24; background: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
        .debug-box { background: #333; color: #0f0; padding: 15px; font-family: monospace; border-radius: 5px; margin-top: 10px; white-space: pre-wrap; word-wrap: break-word; }
    </style>
</head>
<body>

<div class="container">
    <h1>Employer Dashboard</h1>
    
    <div class="job-card">
        <h2>Job Title: Software Engineer</h2>
        <p><strong>Requirements:</strong> Python, SQL, web development, PHP, problem solving, 2 years experience in coding.</p>
    </div>

    <?php if ($error_message): ?>
        <div class="error"><?php echo $error_message; ?></div>
        <?php if ($python_debug_output): ?>
            <div class="debug-box"><strong>Terminal Output:</strong><br><?php echo htmlspecialchars($python_debug_output); ?></div>
        <?php endif; ?>
    <?php endif; ?>

    <h3>Evaluate Applicant</h3>
    <form method="POST" action="" enctype="multipart/form-data">
        <label for="applicant_name">Applicant Name:</label>
        <input type="text" name="applicant_name" required placeholder="e.g., John Smith">
        
        <label for="resume">Upload Resume (.pdf):</label>
        <input type="file" name="resume" accept=".pdf" required>
        
        <button type="submit">Upload and Match</button>
    </form>

    <?php if ($match_score !== null && !$error_message): ?>
        <div class="result">
            <h2>Match Analysis Complete</h2>
            <p>Applicant: <strong><?php echo $applicant_name; ?></strong></p>
            <p class="score-text"><?php echo $match_score; ?>% Match</p>
        </div>
    <?php endif; ?>
</div>

</body>
</html>