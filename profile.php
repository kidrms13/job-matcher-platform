<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$target_id = isset($_GET['id']) ? intval($_GET['id']) : $_SESSION['user_id'];
$is_own_profile = ($target_id == $_SESSION['user_id']);
$is_editing = (isset($_GET['edit']) && $_GET['edit'] == '1' && $is_own_profile);

$message = "";

// 1. Handle Main Profile Updates (Now includes Edu & Certs)
if ($is_own_profile && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $bio = $conn->real_escape_string($_POST['bio']);
    $github = $conn->real_escape_string($_POST['github_username']);
    $website = $conn->real_escape_string($_POST['company_website']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $skills = $conn->real_escape_string($_POST['skills']);
    $education = $conn->real_escape_string($_POST['education']);
    $certifications = $conn->real_escape_string($_POST['certifications']);
    
    $conn->query("UPDATE users SET bio = '$bio', github_username = '$github', company_website = '$website', phone = '$phone', skills = '$skills', education = '$education', certifications = '$certifications' WHERE id = $target_id");
    header("Location: profile.php?success=1");
    exit();
}

// 2. Handle Adding a Job History
if ($is_own_profile && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_experience'])) {
    $job_title = $conn->real_escape_string($_POST['job_title']);
    $exp_company = $conn->real_escape_string($_POST['exp_company']);
    $duration = $conn->real_escape_string($_POST['duration']);
    $exp_desc = $conn->real_escape_string($_POST['exp_desc']);
    
    $conn->query("INSERT INTO experience (user_id, job_title, company, duration, description) VALUES ($target_id, '$job_title', '$exp_company', '$duration', '$exp_desc')");
    header("Location: profile.php?edit=1&success=added");
    exit();
}

// 3. Handle Deleting a Job History
if ($is_own_profile && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_experience'])) {
    $exp_id = intval($_POST['exp_id']);
    $conn->query("DELETE FROM experience WHERE id = $exp_id AND user_id = $target_id");
    header("Location: profile.php?edit=1&success=deleted");
    exit();
}

if (isset($_GET['success'])) {
    if ($_GET['success'] == '1') $message = "<div class='success'>Profile updated successfully!</div>";
    if ($_GET['success'] == 'added') $message = "<div class='success'>Job experience added successfully!</div>";
    if ($_GET['success'] == 'deleted') $message = "<div class='success' style='color: #721c24; background: #f8d7da; border-color: #f5c6cb;'>Job experience removed.</div>";
}

$query = $conn->query("SELECT * FROM users WHERE id = $target_id");
if ($query->num_rows == 0) die("User not found.");
$profile = $query->fetch_assoc();

$experience_query = $conn->query("SELECT * FROM experience WHERE user_id = $target_id ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title><?php echo htmlspecialchars($profile['name']); ?> - Profile</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f2ee; margin: 0; color: #333; }
        .navbar { background-color: #fff; padding: 15px 40px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .navbar h2 { margin: 0; color: #0a66c2; font-size: 24px; }
        .back-btn { color: #666; text-decoration: none; font-weight: bold; }
        .container { max-width: 800px; margin: 40px auto; background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .profile-header { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #f3f2ef; padding-bottom: 20px; margin-bottom: 20px; }
        .profile-info-left { display: flex; align-items: center; gap: 20px; }
        .avatar { width: 80px; height: 80px; background: #0a66c2; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: bold; }
        .company-logo { width: 80px; height: 80px; border-radius: 10px; object-fit: contain; background: #fff; border: 1px solid #eee; padding: 5px;}
        .badge { background: #e8f4fd; color: #0a66c2; padding: 5px 12px; border-radius: 15px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .section-title { font-size: 18px; color: #333; margin-top: 30px; border-bottom: 1px solid #eee; padding-bottom: 5px; margin-bottom: 15px;}
        .data-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px; }
        .data-box { background: #f9f9f9; padding: 15px; border-radius: 8px; border: 1px solid #eee; }
        .data-box strong { display: block; color: #666; font-size: 12px; text-transform: uppercase; margin-bottom: 5px; }
        .exp-card { border-left: 3px solid #0a66c2; padding-left: 15px; margin-bottom: 25px;}
        .exp-card h4 { margin: 0 0 5px 0; color: #333; font-size: 18px; }
        .exp-card .company { font-weight: bold; color: #555; margin: 0; }
        .exp-card .duration { color: #888; font-size: 13px; margin: 2px 0 10px 0; }
        .exp-card .desc { color: #444; line-height: 1.5; font-size: 14px; margin: 0; }
        input, textarea, button { width: 100%; padding: 10px; margin: 8px 0; box-sizing: border-box; border: 1px solid #ccc; border-radius: 5px; font-family: inherit;}
        .btn-primary { background-color: #0a66c2; color: white; border: none; cursor: pointer; font-weight: bold; transition: 0.3s; border-radius: 5px; padding: 12px;}
        .btn-primary:hover { background-color: #004182; }
        .edit-btn { background: white; color: #0a66c2; border: 1px solid #0a66c2; padding: 8px 15px; border-radius: 20px; text-decoration: none; font-size: 14px; font-weight: bold; transition: 0.2s;}
        .edit-btn:hover { background: #f4f2ee; }
        .cancel-btn { background: white; color: #d9534f; border: 1px solid #d9534f; padding: 10px; border-radius: 5px; text-decoration: none; text-align: center; display: block; margin-top: 10px; font-weight: bold;}
        .btn-small-danger { background: white; color: #d9534f; border: 1px solid #d9534f; padding: 5px 10px; border-radius: 4px; font-size: 12px; cursor: pointer; width: auto; }
        .success { color: green; background: #e6f4ea; padding: 10px; border-radius: 5px; margin-bottom: 15px; border: 1px solid #c3e6cb;}
        .edit-form-box { background: #fafafa; padding: 25px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 30px; }
    </style>
</head>
<body>

<div class="navbar">
    <h2>Job Matcher <span style="color:#666; font-size:16px;">| Profile Network</span></h2>
    <a href="index.php" class="back-btn">&larr; Back to Dashboard</a>
</div>

<div class="container">
    <?php echo $message; ?>

    <div class="profile-header">
        <div class="profile-info-left">
            <?php if ($profile['role'] == 'employer' && !empty($profile['company_website'])): ?>
                <?php $domain = parse_url($profile['company_website'], PHP_URL_HOST) ?? $profile['company_website']; ?>
                <img src="https://logo.clearbit.com/<?php echo htmlspecialchars($domain); ?>" class="company-logo" alt="Company Logo" onerror="this.style.display='none'">
            <?php else: ?>
                <div class="avatar"><?php echo strtoupper(substr($profile['name'], 0, 1)); ?></div>
            <?php endif; ?>
            
            <div>
                <h1 style="margin: 0;"><?php echo htmlspecialchars($profile['name']); ?></h1>
                <span class="badge"><?php echo htmlspecialchars($profile['role']); ?></span>
            </div>
        </div>
        
        <?php if ($is_own_profile && !$is_editing): ?>
            <a href="?edit=1" class="edit-btn" onclick="return confirm('Ready to update your profile and experience?');">✏️ Edit Profile</a>
        <?php endif; ?>
    </div>

    <?php if ($is_editing): ?>
        <div class="edit-form-box">
            <h3 style="margin-top: 0; color: #0a66c2;">1. Edit Basic Details</h3>
            <form method="POST">
                <label><strong>Bio:</strong></label>
                <textarea name="bio" rows="3"><?php echo htmlspecialchars($profile['bio']); ?></textarea>
                
                <?php if ($profile['role'] == 'applicant'): ?>
                    <label><strong>GitHub Username:</strong></label>
                    <input type="text" name="github_username" value="<?php echo htmlspecialchars($profile['github_username']); ?>">
                    
                    <div style="display: flex; gap: 15px;">
                        <div style="flex: 1;">
                            <label><strong>Phone Number:</strong></label>
                            <input type="text" name="phone" value="<?php echo htmlspecialchars($profile['phone']); ?>">
                        </div>
                        <div style="flex: 1;">
                            <label><strong>Contact Email:</strong></label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($profile['email']); ?>" readonly style="background: #eee; cursor: not-allowed;" title="Email cannot be changed">
                        </div>
                    </div>
                    
                    <label><strong>Highest Education <span style="color:red;">*</span></strong></label>
                    <input type="text" name="education" value="<?php echo htmlspecialchars($profile['education'] ?? ''); ?>" required placeholder="e.g. Bachelor of Science in Computer Science">
                    
                    <label><strong>Certifications (Optional):</strong></label>
                    <input type="text" name="certifications" value="<?php echo htmlspecialchars($profile['certifications'] ?? ''); ?>" placeholder="e.g. AWS Certified Developer, CompTIA Security+">

                    <label><strong>Skills (Comma separated):</strong></label>
                    <input type="text" name="skills" value="<?php echo htmlspecialchars($profile['skills']); ?>">
                    
                <?php else: ?>
                    <label><strong>Company Website URL:</strong></label>
                    <input type="text" name="company_website" value="<?php echo htmlspecialchars($profile['company_website']); ?>">
                    <input type="hidden" name="phone" value="">
                    <input type="hidden" name="skills" value="">
                    <input type="hidden" name="education" value="">
                    <input type="hidden" name="certifications" value="">
                <?php endif; ?>
                
                <button type="submit" name="update_profile" class="btn-primary">💾 Save Basic Details</button>
            </form>
        </div>

        <?php if ($profile['role'] == 'applicant'): ?>
            <div class="edit-form-box" style="background: #fff; border-color: #0a66c2;">
                <h3 style="margin-top: 0; color: #0a66c2;">2. Add Job Experience</h3>
                <form method="POST">
                    <label><strong>Job Title:</strong></label>
                    <input type="text" name="job_title" required placeholder="e.g. Software Engineer">
                    <div style="display: flex; gap: 15px;">
                        <div style="flex: 1;"><label><strong>Company:</strong></label><input type="text" name="exp_company" required></div>
                        <div style="flex: 1;"><label><strong>Dates:</strong></label><input type="text" name="duration" required></div>
                    </div>
                    <label><strong>Description:</strong></label>
                    <textarea name="exp_desc" rows="3"></textarea>
                    <button type="submit" name="add_experience" class="btn-primary" style="background: #28a745;">➕ Add Experience</button>
                </form>

                <?php if ($experience_query->num_rows > 0): ?>
                    <h4 style="margin-top: 30px; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Manage Current Experience</h4>
                    <?php while($exp = $experience_query->fetch_assoc()): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; border: 1px solid #eee; padding: 10px; margin-bottom: 10px; border-radius: 5px;">
                            <div><strong><?php echo htmlspecialchars($exp['job_title']); ?></strong> at <?php echo htmlspecialchars($exp['company']); ?></div>
                            <form method="POST" style="margin: 0;">
                                <input type="hidden" name="exp_id" value="<?php echo $exp['id']; ?>">
                                <button type="submit" name="delete_experience" class="btn-small-danger" onclick="return confirm('Delete this job?');">Remove</button>
                            </form>
                        </div>
                    <?php endwhile; ?>
                    <?php $experience_query->data_seek(0); ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <a href="profile.php" class="cancel-btn">Close Edit Mode</a>

    <?php else: ?>
        <?php if (!empty($profile['bio'])): ?>
            <div class="section-title">About</div>
            <p style="line-height: 1.6; color: #444;"><?php echo nl2br(htmlspecialchars($profile['bio'])); ?></p>
        <?php endif; ?>

        <?php if ($profile['role'] == 'applicant'): ?>
            
            <div class="section-title">Work Experience</div>
            <?php if ($experience_query->num_rows > 0): ?>
                <?php while($exp = $experience_query->fetch_assoc()): ?>
                    <div class="exp-card">
                        <h4><?php echo htmlspecialchars($exp['job_title']); ?></h4>
                        <p class="company"><?php echo htmlspecialchars($exp['company']); ?></p>
                        <p class="duration"><?php echo htmlspecialchars($exp['duration']); ?></p>
                        <?php if (!empty($exp['description'])): ?>
                            <p class="desc"><?php echo nl2br(htmlspecialchars($exp['description'])); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="color: #666; font-style: italic;">No work experience listed yet.</p>
            <?php endif; ?>

            <div class="section-title">Qualifications & Data</div>
            <div class="data-grid">
                <div class="data-box" style="grid-column: span 2; background: #e8f4fd; border-color: #b8daff;">
                    <strong>Education</strong>
                    <?php echo !empty($profile['education']) ? htmlspecialchars($profile['education']) : "<span style='color:red;'>Required: Please update your profile.</span>"; ?>
                </div>
                
                <?php if (!empty($profile['certifications'])): ?>
                <div class="data-box" style="grid-column: span 2;">
                    <strong>Certifications</strong>
                    <?php echo htmlspecialchars($profile['certifications']); ?>
                </div>
                <?php endif; ?>

                <div class="data-box">
                    <strong>Phone</strong>
                    <?php echo $profile['phone'] ? htmlspecialchars($profile['phone']) : "<em>Not provided</em>"; ?>
                </div>
                <div class="data-box">
                    <strong>Email</strong>
                    <?php echo htmlspecialchars($profile['email']); ?>
                </div>
                <div class="data-box" style="grid-column: span 2;">
                    <strong>Skills</strong>
                    <?php echo $profile['skills'] ? htmlspecialchars($profile['skills']) : "<em>No skills listed</em>"; ?>
                </div>
            </div>

            <?php if ($profile['resume_path']): ?>
                <br>
                <a href="<?php echo htmlspecialchars($profile['resume_path']); ?>" target="_blank" style="display:inline-block; background: #e8f4fd; color: #0a66c2; border: 1px solid #0a66c2; padding: 10px 20px; border-radius: 20px; text-decoration: none; font-weight: bold; transition: 0.2s;">📄 View Full PDF Resume</a>
            <?php endif; ?>

            <?php if (!empty($profile['github_username'])): ?>
                <div class="section-title">Live Coding Portfolio (GitHub)</div>
                <div id="github-repos">Loading repositories...</div>
                <script>
                    const username = "<?php echo htmlspecialchars($profile['github_username']); ?>";
                    fetch(`https://api.github.com/users/${username}/repos?sort=updated&per_page=3`)
                        .then(response => response.json())
                        .then(data => {
                            const repoContainer = document.getElementById('github-repos');
                            repoContainer.innerHTML = ''; 
                            if(data.message && data.message === "Not Found") { repoContainer.innerHTML = '<p style="color:red;">GitHub user not found.</p>'; return; }
                            if(data.length === 0) { repoContainer.innerHTML = '<p>No public repositories found.</p>'; return; }
                            data.forEach(repo => {
                                repoContainer.innerHTML += `
                                    <div style="border: 1px solid #ddd; padding: 15px; border-radius: 8px; margin-bottom: 10px;">
                                        <h4 style="margin: 0 0 5px 0;"><a href="${repo.html_url}" target="_blank" style="text-decoration:none; color:#0a66c2;">${repo.name}</a></h4>
                                        <p style="font-size:13px; color:#666; margin:0;">${repo.description || 'No description provided.'}</p>
                                        <span style="font-size:12px; color:#999; font-weight:bold;">Language: ${repo.language || 'Multiple'}</span>
                                    </div>
                                `;
                            });
                        }).catch(() => document.getElementById('github-repos').innerHTML = '<p style="color:red;">Error loading GitHub data.</p>');
                </script>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>