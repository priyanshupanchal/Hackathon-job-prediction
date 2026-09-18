<?php
session_start();

// ── Session guard ──────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header("Location: http://localhost/hackathon-employability-ml/index.html");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: http://localhost/hackathon-employability-ml/student_details.php");
    exit();
}

$userId = (int) $_SESSION['user_id'];

// ── DB connection ──────────────────────────────────────────────────────────────
$con = mysqli_connect("localhost", "root", "", "hackathon-employability-ml");
if (!$con) {
    die("Connection failed: " . mysqli_connect_error());
}

// ── Sanitize scalar fields ──────────────────────────────────────────────────────
$degree          = trim($_POST['degree']          ?? '');
$college         = trim($_POST['college']         ?? '');
$cgpa            = trim($_POST['cgpa']            ?? '0');
$graduation_year = trim($_POST['graduation_year'] ?? '');

// ── AI Readiness Score Fields ─────────────────────────────────────────────────
$age                   = max(17, min(35, (int)($_POST['age']                   ?? 22)));
$python_score          = max(0, min(10, (float)($_POST['python_score']          ?? 5.0)));
$sql_score             = max(0, min(10, (float)($_POST['sql_score']             ?? 5.0)));
$statistics_score      = max(0, min(10, (float)($_POST['statistics_score']      ?? 5.0)));
$ml_score              = max(0, min(10, (float)($_POST['ml_score']              ?? 5.0)));
$dl_score              = max(0, min(10, (float)($_POST['dl_score']              ?? 5.0)));
$genai_score           = max(0, min(10, (float)($_POST['genai_score']           ?? 5.0)));
$ml_projects           = max(0, (int)($_POST['ml_projects']           ?? 0));
$end_to_end_projects   = max(0, (int)($_POST['end_to_end_projects']   ?? 0));
$deployed_projects     = max(0, (int)($_POST['deployed_projects']     ?? 0));
$kaggle_competitions   = max(0, (int)($_POST['kaggle_competitions']   ?? 0));
$best_rank_raw         = trim($_POST['best_competition_rank'] ?? '');
$best_competition_rank = ($best_rank_raw !== '' && is_numeric($best_rank_raw)) ? (int)$best_rank_raw : null;
$hackathons_attended   = max(0, (int)($_POST['hackathons_attended']   ?? 0));
$hackathons_won        = max(0, (int)($_POST['hackathons_won']        ?? 0));
$finalist_status       = max(0, (int)($_POST['finalist_status']       ?? 0));
$github_projects       = max(0, (int)($_POST['github_projects']       ?? 0));
$internship_months     = max(0, (int)($_POST['internship_months']     ?? 0));
$certifications        = max(0, (int)($_POST['certifications']        ?? 0));
$ml_interview_score    = max(0, min(10, (float)($_POST['ml_interview_score']    ?? 5.0)));
$communication_score   = max(0, min(10, (float)($_POST['communication_score']   ?? 5.0)));
$dsa_score             = max(0, min(10, (float)($_POST['dsa_score']             ?? 5.0)));
$resume_score          = max(0, min(10, (float)($_POST['resume_score']          ?? 5.0)));
$mock_interview_score  = max(0, min(10, (float)($_POST['mock_interview_score']  ?? 5.0)));

// ── Validate ───────────────────────────────────────────────────────────────────
$errors = [];
if (empty($degree))          $errors[] = "Degree is required.";
if (empty($college))         $errors[] = "College name is required.";
if (!is_numeric($cgpa) || (float)$cgpa < 0 || (float)$cgpa > 10)
                             $errors[] = "CGPA must be between 0 and 10.";
if (!preg_match('/^\d{4}$/', $graduation_year))
                             $errors[] = "Invalid graduation year.";

if (!empty($errors)) {
    $msg = urlencode(implode(' | ', $errors));
    header("Location: http://localhost/hackathon-employability-ml/student_details.php?error=$msg");
    exit();
}

// ── Skills (array from hidden input JSON) ─────────────────────────────────────
$skillsRaw = trim($_POST['skills_json'] ?? '[]');
$skills    = json_decode($skillsRaw, true);
if (!is_array($skills)) $skills = [];
$skillsJson = json_encode(array_values(array_filter(array_map('htmlspecialchars', $skills))));

// ── Experience rows ────────────────────────────────────────────────────────────
$expCompanies  = $_POST['exp_company']  ?? [];
$expRoles      = $_POST['exp_role']     ?? [];
$expDurations  = $_POST['exp_duration'] ?? [];
$experience    = [];
foreach ($expCompanies as $i => $company) {
    $company  = trim(htmlspecialchars($company));
    $role     = trim(htmlspecialchars($expRoles[$i]     ?? ''));
    $duration = trim(htmlspecialchars($expDurations[$i] ?? ''));
    if (!empty($company) || !empty($role)) {
        $experience[] = ['company' => $company, 'role' => $role, 'duration' => $duration];
    }
}
$experienceJson = json_encode($experience);

// ── Certificate rows ───────────────────────────────────────────────────────────
$certNames   = $_POST['cert_name']   ?? [];
$certIssuers = $_POST['cert_issuer'] ?? [];
$certYears   = $_POST['cert_year']   ?? [];
$certificates = [];
foreach ($certNames as $i => $name) {
    $name   = trim(htmlspecialchars($name));
    $issuer = trim(htmlspecialchars($certIssuers[$i] ?? ''));
    $year   = trim(htmlspecialchars($certYears[$i]   ?? ''));
    if (!empty($name)) {
        $certificates[] = ['name' => $name, 'issuer' => $issuer, 'year' => $year];
    }
}
$certificatesJson = json_encode($certificates);

// ── CV Upload ──────────────────────────────────────────────────────────────────
$cvFilename = null;

// First check if user kept their existing CV
$keepExisting = ($_POST['keep_existing_cv'] ?? '') === '1';

if (isset($_FILES['cv']) && $_FILES['cv']['error'] === UPLOAD_ERR_OK) {
    $file     = $_FILES['cv'];
    $tmpPath  = $file['tmp_name'];
    $origName = basename($file['name']);
    $mimeType = mime_content_type($tmpPath);
    $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $maxSize  = 5 * 1024 * 1024; // 5 MB

    if ($ext !== 'pdf' || $mimeType !== 'application/pdf') {
        $con->close();
        header("Location: http://localhost/hackathon-employability-ml/student_details.php?error=" . urlencode("Only PDF files are allowed for CV upload."));
        exit();
    }
    if ($file['size'] > $maxSize) {
        $con->close();
        header("Location: http://localhost/hackathon-employability-ml/student_details.php?error=" . urlencode("CV file must be under 5 MB."));
        exit();
    }

    // Delete old CV if any
    $oldQ = $con->prepare("SELECT cv_filename FROM student_details WHERE user_id = ?");
    $oldQ->bind_param("i", $userId);
    $oldQ->execute();
    $oldQ->bind_result($oldFile);
    $oldQ->fetch();
    $oldQ->close();
    if ($oldFile) {
        $oldPath = __DIR__ . "/uploads/cv/" . $oldFile;
        if (file_exists($oldPath)) @unlink($oldPath);
    }

    // Save with a unique name
    $cvFilename = "cv_{$userId}_" . time() . ".pdf";
    $destPath   = __DIR__ . "/uploads/cv/" . $cvFilename;
    if (!move_uploaded_file($tmpPath, $destPath)) {
        $con->close();
        header("Location: http://localhost/hackathon-employability-ml/student_details.php?error=" . urlencode("Failed to save the CV file. Check folder permissions."));
        exit();
    }
} elseif ($keepExisting) {
    // Retrieve current filename from DB so we don't overwrite with NULL
    $oldQ = $con->prepare("SELECT cv_filename FROM student_details WHERE user_id = ?");
    $oldQ->bind_param("i", $userId);
    $oldQ->execute();
    $oldQ->bind_result($cvFilename);
    $oldQ->fetch();
    $oldQ->close();
}

// ── UPSERT student_details ─────────────────────────────────────────────────────
$sql = "INSERT INTO student_details
            (user_id, degree, college, cgpa, graduation_year, skills, experience, certificates, cv_filename,
             age, python_score, sql_score, statistics_score, ml_score, dl_score, genai_score,
             ml_projects, end_to_end_projects, deployed_projects, kaggle_competitions, best_competition_rank,
             hackathons_attended, hackathons_won, finalist_status, github_projects, internship_months, certifications,
             ml_interview_score, communication_score, dsa_score, resume_score, mock_interview_score)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            degree               = VALUES(degree),
            college              = VALUES(college),
            cgpa                 = VALUES(cgpa),
            graduation_year      = VALUES(graduation_year),
            skills               = VALUES(skills),
            experience           = VALUES(experience),
            certificates         = VALUES(certificates),
            cv_filename          = VALUES(cv_filename),
            age                  = VALUES(age),
            python_score         = VALUES(python_score),
            sql_score            = VALUES(sql_score),
            statistics_score     = VALUES(statistics_score),
            ml_score             = VALUES(ml_score),
            dl_score             = VALUES(dl_score),
            genai_score          = VALUES(genai_score),
            ml_projects          = VALUES(ml_projects),
            end_to_end_projects  = VALUES(end_to_end_projects),
            deployed_projects    = VALUES(deployed_projects),
            kaggle_competitions  = VALUES(kaggle_competitions),
            best_competition_rank= VALUES(best_competition_rank),
            hackathons_attended  = VALUES(hackathons_attended),
            hackathons_won       = VALUES(hackathons_won),
            finalist_status      = VALUES(finalist_status),
            github_projects      = VALUES(github_projects),
            internship_months    = VALUES(internship_months),
            certifications       = VALUES(certifications),
            ml_interview_score   = VALUES(ml_interview_score),
            communication_score  = VALUES(communication_score),
            dsa_score            = VALUES(dsa_score),
            resume_score         = VALUES(resume_score),
            mock_interview_score = VALUES(mock_interview_score),
            updated_at           = CURRENT_TIMESTAMP";

$stmt = $con->prepare($sql);
$stmt->bind_param(
    "issdissss" . "i" . "dddddd" . "iiiid" . "iiiiii" . "ddddd",
    $userId, $degree, $college, $cgpa, $graduation_year,
    $skillsJson, $experienceJson, $certificatesJson, $cvFilename,
    $age,
    $python_score, $sql_score, $statistics_score, $ml_score, $dl_score, $genai_score,
    $ml_projects, $end_to_end_projects, $deployed_projects, $kaggle_competitions, $best_competition_rank,
    $hackathons_attended, $hackathons_won, $finalist_status, $github_projects, $internship_months, $certifications,
    $ml_interview_score, $communication_score, $dsa_score, $resume_score, $mock_interview_score
);

if ($stmt->execute()) {
    $stmt->close();
    $con->close();
    // Redirect to predict.php so user immediately sees their AI prediction
    header('Location: http://localhost/hackathon-employability-ml/predict.php?from=save');
    exit();
} else {
    $err = urlencode("Save failed: " . $stmt->error);
    $stmt->close();
    $con->close();
    header("Location: http://localhost/hackathon-employability-ml/student_details.php?error=$err");
    exit();
}
?>
