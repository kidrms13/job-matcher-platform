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

// 1. Handle PDF Resume Uploads/Replacements
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_resume'])) {
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] == 0) {
        $allowed = ['pdf'];
        $filename = $_FILES['resume']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $target_dir = "uploads/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $new_filename = "resume_" . $user_id . "_" . time() . ".pdf";
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['resume']['tmp_name'], $target_file)) {
                // Remove old file reference from disk if exists
                $old_res = $conn->query("SELECT resume_path FROM users WHERE id = $user_id")->fetch_assoc();
                if(!empty($old_res['resume_path']) && file_exists($old_res['resume_path'])) {
                    @unlink($old_res['resume_path']);
                }
                
                $conn->query("UPDATE users SET resume_path = '$target_file' WHERE id = $user_id");
                $message = "<div class='success'>Resume uploaded and processed successfully! Your scores have been updated.</div>";
            }
        } else {
            $message = "<div class='error'>Invalid format. Please upload a PDF file only.</div>";
        }
    }
}

// 2. Handle Resume Deletions
if (isset($_POST['delete_resume'])) {
    $old_res = $conn->query("SELECT resume_path FROM users WHERE id = $user_id")->fetch_assoc();
    if(!empty($old_res['resume_path']) && file_exists($old_res['resume_path'])) {
        @unlink($old_res['resume_path']);
    }
    $conn->query("UPDATE users SET resume_path = NULL WHERE id = $user_id");
    $message = "<div class='success'>Resume removed successfully.</div>";
}

// 3. Fetch Applicant Context Data to feed the AI Model
$user_sql = "
    SELECT u.name, u.resume_path, 
           IFNULL(u.bio, '') as bio, 
           IFNULL(u.skills, '') as skills, 
           IFNULL(u.education, '') as education, 
           IFNULL(u.certifications, '') as certifications,
           GROUP_CONCAT(CONCAT(e.job_title, ' at ', e.company, ' ', IFNULL(e.description, '')) SEPARATOR ' ') as exp_text
    FROM users u
    LEFT JOIN experience e ON u.id = e.user_id
    WHERE u.id = $user_id
    GROUP BY u.id
";
$user_data = $conn->query($user_sql)->fetch_assoc();

$pdf_path = !empty($user_data['resume_path']) ? $user_data['resume_path'] : "NO_RESUME";
$profile_text = $user_data['skills'] . " " . $user_data['bio'] . " " . $user_data['education'] . " " . $user_data['certifications'] . " " . $user_data['exp_text'];

// 4. Fetch and Process All Available Live Job Vacancies
$jobs_query = $conn->query("SELECT * FROM jobs ORDER BY created_at DESC");
$jobs_list = [];
$payload_jobs = [];

while($job = $jobs_query->fetch_assoc()) {
    $jobs_list[$job['id']] = $job;
    
    // We package the jobs like "applicants" so our existing Python model can read it seamlessly
    $payload_jobs[] = [
        'id' => $job['id'],
        'resume_path' => "NO_RESUME", // The job posting doesn't have a path
        'db_text' => $job['requirements'] // The text to compare against the profile_text
    ];
}

$ranked_jobs = [];
$ai_server_active = true;

if (count($payload_jobs) > 0) {
    // Send the data package over to our local port 5000 server
    $payload = json_encode([
        'job_requirements' => $profile_text, // Passing applicant text as baseline target
        'applicants' => $payload_jobs       // Passing all jobs to be checked against it
    ]);
    
    $ch = curl_init('http://127.0.0.1:5000/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $scores = json_decode($response, true);
    
    if (is_array($scores)) {
        foreach ($scores as $score_data) {
            $job_id = $score_data['id'];
            $score = floatval($score_data['score']);
            $job_info = $jobs_list[$job_id];
            
            $ranked_jobs[] = [
                'id' => $job_id,
                'title' => $job_info['title'],
                'company_name' => $job_info['company_name'],
                'requirements' => $job_info['requirements'],
                'created_at' => $job_info['created_at'],
                'score' => $score
            ];
        }
        // Sort jobs from highest matching score percentage down to lowest
        usort($ranked_jobs, function($a, $b) { return $b['score'] <=> $a['score']; });
    } else {
        $ai_server_active = false;
        // Fallback configuration if API is disconnected
        foreach($jobs_list as $job_id => $job_info) {
            $ranked_jobs[] = array_merge($job_info, ['score' => null]);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Applicant Dashboard - Job Matcher</title>
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
        .card h3 { margin-top: 0; color: #0a66c2; border-bottom: 2px solid #f3f2ef; padding-bottom: 10px; }
        
        .status-badge { background-color: #e6f4ea; color: #137333; padding: 10px; border-radius: 5px; text-align: center; font-weight: bold; margin-bottom: 15px; border: 1px solid #ceead6;}
        .status-badge-empty { background-color: #fce8e6; color: #c5221f; padding: 10px; border-radius: 5px; text-align: center; font-weight: bold; margin-bottom: 15px; border: 1px solid #fad2cf;}
        
        .btn-action { width: 48%; padding: 10px; font-weight: bold; font-size: 14px; border-radius: 20px; border: 1px solid #0a66c2; cursor: pointer; text-align: center; text-decoration: none; display: inline-block; box-sizing: border-box;}
        .btn-view { background: #e8f4fd; color: #0a66c2; }
        .btn-view:hover { background: #d0e8fc; }
        .btn-delete { background: white; color: #d9534f; border-color: #d9534f; }
        .btn-delete:hover { background: #fdf2f2; }
        
        .upload-box { border: 1px solid #ccc; padding: 15px; border-radius: 5px; background: #fafafa; margin-top: 15px;}
        .btn-submit { background-color: #0a66c2; color: white; border: none; padding: 12px; border-radius: 25px; font-weight: bold; width: 100%; margin-top: 10px; cursor: pointer; font-size: 15px;}
        .btn-submit:hover { background-color: #004182; }
        
        .job-card { border: 1px solid #e0e0e0; padding: 20px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-start; background: #fff; transition: 0.2s;}
        .job-card:hover { box-shadow: 0 4px 8px rgba(0,0,0,0.08); }
        .job-details { flex: 3; }
        .job-score-area { flex: 1; text-align: right; display: flex; flex-direction: column; align-items: flex-end; justify-content: center; height: 100%; min-height: 80px;}
        
        .match-badge { background: #e6f4ea; color: #137333; padding: 12px 20px; border-radius: 30px; font-weight: bold; font-size: 18px; box-shadow: 0 2px 4px rgba(19,115,51,0.15); border: 1px solid #ceead6;}
        .match-badge-low { background: #fff3cd; color: #856404; padding: 12px 20px; border-radius: 30px; font-weight: bold; font-size: 18px; border: 1px solid #ffeeba;}
        
        .requirements-preview { background: #f8f9fa; padding: 12px; border-radius: 6px; margin-top: 12px; font-size: 14px; color: #555; white-space: pre-line; border-left: 3px solid #ccc;}
        
        .success { color: green; background: #e6f4ea; padding: 10px; border-radius: 5px; margin-bottom: 15px; border: 1px solid #c3e6cb; font-size: 14px;}
        .error { color: red; background: #fce8e6; padding: 10px; border-radius: 5px; margin-bottom: 15px; border: 1px solid #fad2cf; font-size: 14px;}
        .notice-banner { background: #fff3cd; color: #856404; padding: 12px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #ffeeba; font-size: 14px;}
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
            <h3>Welcome, <?php echo htmlspecialchars($user_data['name']); ?>!</h3>
            <?php echo $message; ?>
            
            <?php if (!empty($user_data['resume_path']) && file_exists($user_data['resume_path'])): ?>
                <div class="status-badge">✓ Profile Active <br><span style="font-size:11px; font-weight:normal;">Your resume is fully optimized and visible to employers.</span></div>
                <div style="display:flex; justify-content: space-between; margin-bottom:10px;">
                    <a href="<?php echo htmlspecialchars($user_data['resume_path']); ?>" target="_blank" class="btn-action btn-view">📄 View File</a>
                    <form method="POST" style="width:48%; margin:0;">
                        <button type="submit" name="delete_resume" class="btn-action btn-delete">🗑 Delete</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="status-badge-empty">⚠ No PDF Resume Uploaded<br><span style="font-size:11px; font-weight:normal;">Upload a PDF to activate semantic ranking metrics!</span></div>
            <?php endif; ?>
            
            <div class="upload-box">
                <h4 style="margin-top:0; color:#555;">Want to update your resume?</h4>
                <p style="font-size:12px; color:#666; margin-bottom:10px;">Uploading a new PDF will automatically recalculate matching metrics against all live job feeds.</p>
                <form method="POST" enctype="multipart/form-data">
                    <input type="file" name="resume" accept=".pdf" required style="font-size:13px;">
                    <button type="submit" name="upload_resume" class="btn-submit">Upload Replacement</button>
                </form>
            </div>
        </div>
    </div>

    <div class="column-right">
        <div class="card">
            <h3>Live Job Board <span style="font-size:13px; color:#666; font-weight:normal; float:right; margin-top:5px;">AI Smart Ranked</span></h3>
            <p style="color:#666; font-size:14px; margin-bottom:20px;">These jobs are arranged based on how closely their requirements complement your skills, education, and resume context.</p>
            
            <?php if (!$ai_server_active): ?>
                <div class="notice-banner">
                    <strong>AI Matching Engine Standby:</strong> Displaying listings by date. To sort listings intelligently, boot up your engine by entering <code>python matcher.py</code> inside the project terminal.
                </div>
            <?php endif; ?>

            <?php if (count($ranked_jobs) > 0): ?>
                <?php foreach ($ranked_jobs as $job): ?>
                    <div class="job-card">
                        <div class="job-details">
                            <h3 style="margin:0 0 5px 0; color:#0a66c2; font-size:20px; border:none; padding:0;">
                                <?php echo htmlspecialchars($job['title']); ?>
                            </h3>
                            <div style="font-size:14px; color:#444; font-weight:bold; margin-bottom:5px;">
                                🏢 <?php echo htmlspecialchars($job['company_name']); ?> 
                                <span style="color:#888; font-weight:normal; font-size:12px; margin-left:10px;">
                                    • Posted on <?php echo date('M d, Y', strtotime($job['created_at'])); ?>
                                </span>
                            </div>
                            <div class="requirements-preview"><strong>Required Qualifications:</strong><br><?php echo htmlspecialchars($job['requirements']); ?></div>
                        </div>
                        
                        <div class="job-score-area">
                            <?php if ($job['score'] !== null): ?>
                                <?php if ($job['score'] >= 50): ?>
                                    <div class="match-badge"><?php echo $job['score']; ?>% Match</div>
                                <?php else: ?>
                                    <div class="match-badge-low"><?php echo $job['score']; ?>% Match</div>
                                <?php endif; ?>
                                <span style="font-size:11px; color:#666; margin-top:5px; font-weight:500;">Semantic Compatibility</span>
                            <?php else: ?>
                                <div style="background:#f3f2ef; color:#666; padding:10px 15px; border-radius:20px; font-size:13px; font-weight:bold;">Pending Scan</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color:#666; text-align:center; padding:30px; background:#f9f9f9; border-radius:8px;">No active employment opportunities listed on the board yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>