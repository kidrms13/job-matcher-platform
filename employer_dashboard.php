<?php
session_start();
require 'db.php';

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employer') {
    header("Location: login.php");
    exit();
}

$employer_id = $_SESSION['user_id'];
$message = "";
$ranked_applicants = [];
$unmatched_applicants = []; 
$active_scan_title = "";

// Handle Posting a New Job
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['post_job'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $company = $conn->real_escape_string($_POST['company']);
    $requirements = $conn->real_escape_string($_POST['requirements']);
    
    $sql = "INSERT INTO jobs (employer_id, title, company_name, requirements) VALUES ($employer_id, '$title', '$company', '$requirements')";
    if ($conn->query($sql)) {
        $message = "<div class='success'>Job posted successfully! It is now live on the Job Board.</div>";
    }
}

// Handle Scanning for a Specific Job (API PROCESSED SEMANTIC AI)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['scan_job'])) {
    $job_reqs = $_POST['job_requirements'];
    $active_scan_title = $_POST['job_title'];
    
    $applicants_data = [];
    $applicant_details = []; 
    
    $sql = "
        SELECT u.id, u.name, u.resume_path, 
               IFNULL(u.bio, '') as bio, 
               IFNULL(u.skills, '') as skills, 
               IFNULL(u.education, '') as education, 
               IFNULL(u.certifications, '') as certifications,
               GROUP_CONCAT(CONCAT(e.job_title, ' at ', e.company, ' ', IFNULL(e.description, '')) SEPARATOR ' ') as exp_text
        FROM users u
        LEFT JOIN experience e ON u.id = e.user_id
        WHERE u.role = 'applicant'
        GROUP BY u.id
    ";
    $result = $conn->query($sql);
    
    while ($applicant = $result->fetch_assoc()) {
        $pdf_path = !empty($applicant['resume_path']) ? $applicant['resume_path'] : "NO_RESUME";
        $profile_text = $applicant['skills'] . " " . $applicant['bio'] . " " . $applicant['education'] . " " . $applicant['certifications'] . " " . $applicant['exp_text'];
        
        $applicants_data[] = [
            'id' => $applicant['id'],
            'resume_path' => $pdf_path,
            'db_text' => $profile_text
        ];
        
        $applicant_details[$applicant['id']] = [
            'name' => $applicant['name'],
            'resume' => $applicant['resume_path']
        ];
    }
    
    if (count($applicants_data) > 0) {
        // Bundle requirements and applicant structures for delivery
        $payload = json_encode([
            'job_requirements' => $job_reqs,
            'applicants' => $applicants_data
        ]);
        
        // Use clean network stream communication (Bypasses Windows command routing permissions)
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
                $id = $score_data['id'];
                $score = floatval($score_data['score']);
                $details = $applicant_details[$id];
                
                $app_array = [
                    'id' => $id,
                    'name' => $details['name'],
                    'resume' => $details['resume'],
                    'score' => $score
                ];

                if ($score > 0) {
                    $ranked_applicants[] = $app_array;
                } else {
                    $unmatched_applicants[] = $app_array;
                }
            }
        } else {
             $message = "<div class='success' style='background: #fff3cd; color: #856404; border-color: #ffeeba;'>
                            <strong>AI Connection Notice:</strong> Background processing server unreachable. Ensure <code>python matcher.py</code> is actively running in your terminal window.
                         </div>";
        }
        
        usort($ranked_applicants, function($a, $b) { return $b['score'] <=> $a['score']; });
        usort($unmatched_applicants, function($a, $b) { return strcmp($a['name'], $b['name']); });
    }
}

// Fetch all jobs posted by this employer
$my_jobs = $conn->query("SELECT * FROM jobs WHERE employer_id = $employer_id ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employer Hub - Job Matcher</title>
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
        
        input, textarea, button { width: 100%; padding: 12px; margin: 8px 0 15px 0; box-sizing: border-box; border: 1px solid #ccc; border-radius: 5px; font-family: inherit;}
        button { background-color: #0a66c2; color: white; border: none; cursor: pointer; border-radius: 25px; font-weight: bold; font-size: 16px; transition: 0.3s;}
        button:hover { background-color: #004182; }
        .btn-scan { background-color: #057642; margin: 0; width: auto; padding: 8px 20px; }
        .btn-scan:hover { background-color: #03522e; }
        
        .job-item { border: 1px solid #e0e0e0; padding: 15px; border-radius: 8px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; background: #fafafa;}
        .applicant-card { display: flex; justify-content: space-between; align-items: center; border: 1px solid #e0e0e0; padding: 15px; border-radius: 8px; margin-bottom: 10px; background: #fff;}
        .score-badge { background: #d4edda; color: #155724; padding: 10px 15px; border-radius: 20px; font-weight: bold; font-size: 18px; }
        .score-badge-zero { background: #f8d7da; color: #721c24; padding: 10px 15px; border-radius: 20px; font-weight: bold; font-size: 16px; }
        .view-resume-btn { background: white; color: #0a66c2; border: 1px solid #0a66c2; padding: 5px 15px; border-radius: 15px; text-decoration: none; font-size: 14px; font-weight: bold;}
        .view-resume-btn:hover { background: #f4f2ee; }
        .success { color: green; background: #e6f4ea; padding: 10px; border-radius: 5px; margin-bottom: 15px; border: 1px solid #c3e6cb;}
    </style>
</head>
<body>

<div class="navbar">
    <h2>Job Matcher <span style="color:#666; font-size:16px;">| Employer Hub</span></h2>
    <div style="display: flex; gap: 15px;">
        <a href="profile.php" style="color: #0a66c2; font-weight: bold; text-decoration: none; align-self: center;">My Profile</a>
        <a href="logout.php" class="logout-btn">Log Out</a>
    </div>
</div>

<div class="main-layout">
    <div class="column-left">
        <div class="card">
            <h3>Post a New Job</h3>
            <?php echo $message; ?>
            <form method="POST">
                <label>Job Title:</label>
                <input type="text" name="title" required placeholder="e.g. Senior Python Developer">
                
                <label>Company Name:</label>
                <input type="text" name="company" required placeholder="Your Company">
                
                <label>Skills & Requirements (Including Education/Certs):</label>
                <textarea name="requirements" rows="5" required placeholder="e.g. Bachelor's in CS, AWS Certified, 3 years Python..."></textarea>
                
                <button type="submit" name="post_job">Publish to Job Board</button>
            </form>
        </div>
    </div>

    <div class="column-right">
        <?php if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['scan_job'])): ?>
            <div class="card" style="border-left: 5px solid #057642;">
                <h3>Top Matches for: <?php echo htmlspecialchars($active_scan_title); ?></h3>
                <a href="employer_dashboard.php" style="color: #666; text-decoration: none; font-size: 14px;">&larr; Back to all jobs</a>
                <br><br>
                
                <?php if (count($ranked_applicants) > 0): ?>
                    <?php foreach ($ranked_applicants as $app): ?>
                        <div class="applicant-card">
                            <div>
                                <h4 style="margin: 0; color: #333; font-size: 18px;">
                                    <a href="profile.php?id=<?php echo $app['id']; ?>" style="text-decoration: none; color: #0a66c2;"><?php echo htmlspecialchars($app['name']); ?></a>
                                </h4>
                                <?php if ($app['resume']): ?>
                                    <a href="<?php echo htmlspecialchars($app['resume']); ?>" target="_blank" class="view-resume-btn" style="display: inline-block; margin-top: 8px;">📄 View Resume PDF</a>
                                <?php else: ?>
                                    <span style="font-size: 12px; color: #999; display: inline-block; margin-top: 8px;">No PDF uploaded</span>
                                <?php endif; ?>
                            </div>
                            <div class="score-badge">
                                <?php echo $app['score']; ?>% Match
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #666; padding: 10px; background: #f9f9f9; border-radius: 5px;">No strong matches found based on your requirements.</p>
                <?php endif; ?>

                <h3 style="margin-top: 40px; color: #d9534f; border-bottom: 2px solid #fdf2f2; padding-bottom: 10px;">Applicants that are not a match</h3>
                <?php if (count($unmatched_applicants) > 0): ?>
                    <?php foreach ($unmatched_applicants as $app): ?>
                        <div class="applicant-card" style="opacity: 0.8; background: #fafafa;">
                            <div>
                                <h4 style="margin: 0; color: #666; font-size: 16px;">
                                    <a href="profile.php?id=<?php echo $app['id']; ?>" style="text-decoration: none; color: #666;"><?php echo htmlspecialchars($app['name']); ?></a>
                                </h4>
                                <?php if ($app['resume']): ?>
                                    <a href="<?php echo htmlspecialchars($app['resume']); ?>" target="_blank" style="font-size: 12px; color: #0a66c2; text-decoration: none; display: inline-block; margin-top: 5px;">View Resume</a>
                                <?php endif; ?>
                            </div>
                            <div class="score-badge-zero">
                                0% Match
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #666;">There are no unmatched applicants in the database.</p>
                <?php endif; ?>

            </div>
        <?php endif; ?>

        <div class="card">
            <h3>Your Active Job Postings</h3>
            <?php if ($my_jobs->num_rows > 0): ?>
                <?php while($job = $my_jobs->fetch_assoc()): ?>
                    <div class="job-item">
                        <div>
                            <h4 style="margin: 0; color: #333; font-size: 18px;"><?php echo htmlspecialchars($job['title']); ?></h4>
                            <p style="margin: 5px 0 0 0; color: #666; font-size: 14px;">Posted: <?php echo date('M d, Y', strtotime($job['created_at'])); ?></p>
                            
                            <a href="edit_job.php?id=<?php echo $job['id']; ?>" style="font-size: 13px; color: #0a66c2; text-decoration: none; font-weight: bold; display: inline-block; margin-top: 5px;">✏️ Edit Job</a>
                        </div>
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="job_title" value="<?php echo htmlspecialchars($job['title']); ?>">
                            <input type="hidden" name="job_requirements" value="<?php echo htmlspecialchars($job['requirements']); ?>">
                            <button type="submit" name="scan_job" class="btn-scan">Find Matches</button>
                        </form>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="color: #666;">You haven't posted any jobs yet. Create one on the left!</p>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>