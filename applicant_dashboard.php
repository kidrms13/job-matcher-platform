<?php
session_start();
require 'db.php';

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'applicant') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

// --- 1. HANDLE RESUME DELETION ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_resume'])) {
    // Check if the file exists on the server
    $check_query = $conn->query("SELECT resume_path FROM users WHERE id = $user_id");
    $check_data = $check_query->fetch_assoc();
    
    if (!empty($check_data['resume_path']) && file_exists($check_data['resume_path'])) {
        unlink($check_data['resume_path']); // Permanently delete the file from the uploads folder
    }
    
    // Wipe the path from the database
    $conn->query("UPDATE users SET resume_path = NULL WHERE id = $user_id");
    $message = "<div class='success' style='color: #721c24; background: #f8d7da; border-color: #f5c6cb;'>Your resume has been deleted. Your profile is now hidden from employers.</div>";
}

// --- 2. HANDLE FILE UPLOAD & UPDATE (Auto-Parsing) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['resume'])) {
    $upload_dir = 'uploads/';
    $file_name = time() . "_" . basename($_FILES['resume']['name']);
    $target_filepath = $upload_dir . $file_name; 
    $file_type = strtolower(pathinfo($target_filepath, PATHINFO_EXTENSION));

    if ($file_type != "pdf") {
        $message = "<div class='error'>Only PDF files are allowed.</div>";
    } else {
        if (move_uploaded_file($_FILES['resume']['tmp_name'], $target_filepath)) {
            
            // Delete the OLD resume to save server hard drive space before updating!
            $old_file_check = $conn->query("SELECT resume_path FROM users WHERE id = $user_id");
            $old_file_data = $old_file_check->fetch_assoc();
            if (!empty($old_file_data['resume_path']) && file_exists($old_file_data['resume_path'])) {
                unlink($old_file_data['resume_path']);
            }
            
            // Save the new file path to the database
            $conn->query("UPDATE users SET resume_path = '$target_filepath' WHERE id = $user_id");
            
            // Run the Auto-Parser!
            $command = '"C:\\Program Files\\Python314\\python.exe" auto_parser.py ' . escapeshellarg($target_filepath) . ' 2>&1';
            $json_output = shell_exec($command);
            $parsed_data = json_decode($json_output, true);
            
            if ($parsed_data && !isset($parsed_data['error'])) {
                $phone = $conn->real_escape_string($parsed_data['phone_found']);
                $skills = $conn->real_escape_string($parsed_data['skills_found']);
                $conn->query("UPDATE users SET phone = '$phone', skills = '$skills' WHERE id = $user_id");
                
                $message = "<div class='success'>Resume updated! We automatically extracted your skills and contact info.</div>";
            } else {
                $message = "<div class='success'>Resume updated, but we couldn't auto-parse the text. You can fill your profile manually.</div>";
            }
            
        } else {
            $message = "<div class='error'>Error uploading file.</div>";
        }
    }
}

// --- 3. FETCH DATA FOR THE UI ---
// Fetch current user data (happens after uploads/deletes so the screen is always accurate)
$result = $conn->query("SELECT resume_path FROM users WHERE id = $user_id");
$user_data = $result->fetch_assoc();

// Fetch all live jobs for the Job Board
$all_jobs = $conn->query("SELECT * FROM jobs ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Applicant Hub - Job Matcher</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f2ee; margin: 0; color: #333; }
        .navbar { background-color: #fff; padding: 15px 40px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100;}
        .navbar h2 { margin: 0; color: #0a66c2; font-size: 24px; }
        .logout-btn { background-color: #fff; color: #666; padding: 8px 15px; text-decoration: none; border-radius: 20px; font-weight: bold; border: 1px solid #666; transition: 0.3s;}
        .logout-btn:hover { background-color: #f3f2ef; }
        
        .main-layout { display: flex; max-width: 1200px; margin: 30px auto; gap: 30px; padding: 0 20px; }
        .column-left { flex: 1; }
        .column-right { flex: 2; }
        
        .card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .card h3 { margin-top: 0; color: #333; border-bottom: 2px solid #f3f2ef; padding-bottom: 10px; }
        
        .upload-form { background: #fafafa; padding: 20px; border: 1px solid #ddd; border-radius: 8px; margin-top: 20px;}
        input[type="file"], button { width: 100%; padding: 12px; margin: 15px 0; box-sizing: border-box; border: 1px solid #ccc; border-radius: 5px; font-family: inherit;}
        button { background-color: #0a66c2; color: white; border: none; cursor: pointer; border-radius: 25px; font-weight: bold; font-size: 16px; transition: 0.3s;}
        button:hover { background-color: #004182; }
        
        .status-box { padding: 15px; border-radius: 8px; margin-bottom: 15px; text-align: center; font-weight: bold; }
        .status-active { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-missing { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        
        .action-buttons { display: flex; gap: 10px; margin-bottom: 20px; }
        .btn-view { flex: 1; text-align: center; background: #e8f4fd; color: #0a66c2; text-decoration: none; font-weight: bold; padding: 12px; border-radius: 25px; border: 1px solid #0a66c2; transition: 0.3s; }
        .btn-view:hover { background: #d0e8fc; }
        .btn-delete { background: white; color: #d9534f; border: 1px solid #d9534f; margin: 0; }
        .btn-delete:hover { background: #fdf2f2; color: #c9302c; }
        
        .job-card { border: 1px solid #e0e0e0; padding: 20px; border-radius: 8px; margin-bottom: 15px; transition: 0.3s; }
        .job-card:hover { box-shadow: 0 4px 8px rgba(0,0,0,0.1); border-color: #0a66c2; }
        .job-title { color: #0a66c2; font-size: 20px; margin: 0 0 5px 0; }
        .job-company { color: #333; font-weight: bold; font-size: 16px; margin: 0 0 10px 0; }
        .job-reqs { color: #666; font-size: 14px; line-height: 1.5; background: #f3f2ef; padding: 10px; border-radius: 5px;}
        
        .success { color: green; background: #e6f4ea; padding: 10px; border-radius: 5px; margin-bottom: 15px; border: 1px solid #c3e6cb;}
        .error { color: red; background: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
    </style>
</head>
<body>

<div class="navbar">
    <h2>Job Matcher <span style="color:#666; font-size:16px;">| Applicant Dashboard</span></h2>
    <div style="display: flex; gap: 15px;">
        <a href="profile.php" style="color: #0a66c2; font-weight: bold; text-decoration: none; align-self: center;">My Profile</a>
        <a href="logout.php" class="logout-btn">Log Out</a>
    </div>
</div>

<div class="main-layout">
    <div class="column-left">
        <div class="card">
            <h2 style="margin-top:0; color:#0a66c2;">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</h2>
            
            <?php echo $message; ?>

            <?php if($user_data['resume_path']): ?>
                <div class="status-box status-active">
                    ✅ Profile Active<br>
                    <span style="font-size: 12px; font-weight: normal;">Your resume is visible to employers.</span>
                </div>
                
                <div class="action-buttons">
                    <a href="<?php echo htmlspecialchars($user_data['resume_path']); ?>" target="_blank" class="btn-view">📄 View File</a>
                    
                    <form method="POST" style="flex: 1; margin: 0; padding: 0;">
                        <button type="submit" name="delete_resume" class="btn-delete" onclick="return confirm('Are you sure you want to delete your resume? Employers will no longer be able to find you in the search engine.');">🗑️ Delete</button>
                    </form>
                </div>
                
                <form method="POST" enctype="multipart/form-data" class="upload-form">
                    <label style="font-weight: bold; color: #333; font-size: 14px;">Want to update your resume?</label>
                    <p style="font-size: 12px; color: #666; margin-top: 5px;">Uploading a new PDF will automatically replace your old one.</p>
                    <input type="file" name="resume" accept=".pdf" required>
                    <button type="submit">Upload Replacement</button>
                </form>

            <?php else: ?>
                <div class="status-box status-missing">
                    ⚠️ Profile Incomplete<br>
                    <span style="font-size: 12px; font-weight: normal;">Upload a resume to be discovered!</span>
                </div>
                
                <form method="POST" enctype="multipart/form-data" class="upload-form">
                    <label style="font-weight: bold; color: #333;">Upload Your Resume (PDF):</label>
                    <input type="file" name="resume" accept=".pdf" required>
                    <button type="submit">Upload to Profile</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="column-right">
        <div class="card">
            <h3>Live Job Board</h3>
            <p style="color: #666; font-size: 14px; margin-top: -5px;">Ensure your resume is updated. Employers scan our database daily to fill these positions!</p>
            
            <?php if ($all_jobs->num_rows > 0): ?>
                <?php while($job = $all_jobs->fetch_assoc()): ?>
                    <div class="job-card">
                        <a href="apply.php?job_id=<?php echo $job['id']; ?>" style="text-decoration: none;">
                        <h4 class="job-title" style="cursor: pointer; transition: 0.2s;"><?php echo htmlspecialchars($job['title']); ?></h4>
                        </a>
                        <p class="job-company">🏢 <?php echo htmlspecialchars($job['company_name']); ?> <span style="color:#999; font-size:12px; font-weight:normal;">• Posted <?php echo date('M d, Y', strtotime($job['created_at'])); ?></span></p>
                        <p style="font-weight:bold; font-size: 14px; margin-bottom:5px;">Required Skills & Experience:</p>
                        <div class="job-reqs">
                            <?php echo nl2br(htmlspecialchars($job['requirements'])); ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="color: #666; text-align: center; padding: 20px;">No jobs have been posted yet. Check back soon!</p>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>