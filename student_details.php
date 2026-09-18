<?php
session_start();

// ── Session guard ──────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header("Location: http://localhost/hackathon-employability-ml/index.html");
    exit();
}

$userId    = (int) $_SESSION['user_id'];
$userName  = htmlspecialchars($_SESSION['user_name']  ?? 'Student');
$userEmail = htmlspecialchars($_SESSION['user_email'] ?? '');

// ── Load existing profile (if any) ─────────────────────────────────────────────
$con = mysqli_connect("localhost", "root", "", "hackathon-employability-ml");
if (!$con) die("DB connection failed: " . mysqli_connect_error());

$profile = null;
$stmt = $con->prepare("SELECT degree, college, cgpa, graduation_year, skills, experience, certificates, cv_filename,
    age, python_score, sql_score, statistics_score, ml_score, dl_score, genai_score,
    ml_projects, end_to_end_projects, deployed_projects, kaggle_competitions, best_competition_rank,
    hackathons_attended, hackathons_won, finalist_status, github_projects, internship_months, certifications,
    ml_interview_score, communication_score, dsa_score, resume_score, mock_interview_score
    FROM student_details WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result(
    $p_degree, $p_college, $p_cgpa, $p_grad_year, $p_skills, $p_experience, $p_certificates, $p_cv,
    $p_age, $p_python, $p_sql, $p_statistics, $p_ml, $p_dl, $p_genai,
    $p_ml_proj, $p_e2e_proj, $p_deployed, $p_kaggle, $p_best_rank,
    $p_hack_att, $p_hack_won, $p_finalist, $p_github, $p_intern, $p_certs_count,
    $p_ml_interview, $p_comm, $p_dsa, $p_resume, $p_mock
);
if ($stmt->fetch()) {
    $profile = [
        'degree'               => $p_degree,
        'college'              => $p_college,
        'cgpa'                 => $p_cgpa,
        'graduation_year'      => $p_grad_year,
        'skills'               => json_decode($p_skills ?: '[]', true),
        'experience'           => json_decode($p_experience ?: '[]', true),
        'certificates'         => json_decode($p_certificates ?: '[]', true),
        'cv_filename'          => $p_cv,
        // AI readiness fields
        'age'                  => $p_age ?? 22,
        'python_score'         => $p_python ?? 5.0,
        'sql_score'            => $p_sql ?? 5.0,
        'statistics_score'     => $p_statistics ?? 5.0,
        'ml_score'             => $p_ml ?? 5.0,
        'dl_score'             => $p_dl ?? 5.0,
        'genai_score'          => $p_genai ?? 5.0,
        'ml_projects'          => $p_ml_proj ?? 0,
        'end_to_end_projects'  => $p_e2e_proj ?? 0,
        'deployed_projects'    => $p_deployed ?? 0,
        'kaggle_competitions'  => $p_kaggle ?? 0,
        'best_competition_rank'=> $p_best_rank,
        'hackathons_attended'  => $p_hack_att ?? 0,
        'hackathons_won'       => $p_hack_won ?? 0,
        'finalist_status'      => $p_finalist ?? 0,
        'github_projects'      => $p_github ?? 0,
        'internship_months'    => $p_intern ?? 0,
        'certifications'       => $p_certs_count ?? 0,
        'ml_interview_score'   => $p_ml_interview ?? 5.0,
        'communication_score'  => $p_comm ?? 5.0,
        'dsa_score'            => $p_dsa ?? 5.0,
        'resume_score'         => $p_resume ?? 5.0,
        'mock_interview_score' => $p_mock ?? 5.0,
    ];
}
$stmt->close();
$con->close();

$isEdit   = $profile !== null;
$errorMsg = htmlspecialchars($_GET['error'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile | Hackathon Career Readiness</title>
    <meta name="description" content="Complete your student profile with skills, experience, certificates and CV to get personalised AI career readiness predictions.">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:      #0f0c29;
            --bg2:     #1a1740;
            --card:    rgba(255,255,255,0.05);
            --border:  rgba(255,255,255,0.10);
            --indigo:  #6366f1;
            --purple:  #8b5cf6;
            --pink:    #ec4899;
            --green:   #4ade80;
            --text:    #f1f5f9;
            --muted:   rgba(255,255,255,0.50);
            --danger:  #f87171;
            --input-bg: rgba(255,255,255,0.06);
            --input-border: rgba(255,255,255,0.15);
            --input-focus: rgba(99,102,241,0.5);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--bg), #302b63, #24243e);
            min-height: 100vh;
            color: var(--text);
        }

        /* ── NAV ── */
        nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 2.5rem;
            background: rgba(255,255,255,0.04);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .nav-brand { display: flex; align-items: center; gap: .75rem; }
        .nav-brand img { height: 36px; border-radius: 6px; }
        .nav-brand span { font-weight: 700; font-size: .95rem; }
        .nav-right { display: flex; align-items: center; gap: 1rem; }
        .user-pill {
            display: flex; align-items: center; gap: .5rem;
            background: rgba(99,102,241,.15);
            border: 1px solid rgba(99,102,241,.3);
            border-radius: 999px; padding: .4rem 1rem;
            font-size: .85rem; font-weight: 500;
        }
        .user-pill i { color: var(--indigo); }
        .nav-link {
            display: flex; align-items: center; gap: .4rem;
            background: rgba(99,102,241,.12);
            border: 1px solid rgba(99,102,241,.25);
            color: #a5b4fc;
            border-radius: 999px; padding: .4rem 1rem;
            font-size: .85rem; font-weight: 500; text-decoration: none;
            transition: background .2s;
        }
        .nav-link:hover { background: rgba(99,102,241,.25); }
        .logout-btn {
            display: flex; align-items: center; gap: .4rem;
            background: rgba(248,113,113,.12);
            border: 1px solid rgba(248,113,113,.25);
            color: #f87171;
            border-radius: 999px; padding: .4rem 1rem;
            font-size: .85rem; font-weight: 500; text-decoration: none;
            transition: background .2s;
        }
        .logout-btn:hover { background: rgba(248,113,113,.22); }

        /* ── PAGE WRAPPER ── */
        .page-wrapper {
            max-width: 900px;
            margin: 0 auto;
            padding: 2.5rem 1.5rem 4rem;
        }

        /* ── PAGE HEADER ── */
        .page-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        .page-badge {
            display: inline-flex; align-items: center; gap: .5rem;
            background: rgba(99,102,241,.15);
            border: 1px solid rgba(99,102,241,.3);
            border-radius: 999px; padding: .35rem 1rem;
            font-size: .8rem; color: #a5b4fc;
            margin-bottom: 1rem;
        }
        .page-header h1 {
            font-size: clamp(1.6rem, 4vw, 2.4rem);
            font-weight: 800; line-height: 1.2;
            margin-bottom: .5rem;
        }
        .page-header h1 span {
            background: linear-gradient(135deg, var(--indigo), var(--purple), var(--pink));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .page-header p { color: var(--muted); font-size: .95rem; }

        /* ── PROGRESS BAR ── */
        .progress-container {
            background: rgba(255,255,255,.06);
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 1.25rem 2rem;
            margin-bottom: 2rem;
            backdrop-filter: blur(12px);
        }
        .progress-steps {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
        }
        .progress-steps::before {
            content: '';
            position: absolute;
            top: 20px; left: 20px; right: 20px;
            height: 2px;
            background: rgba(255,255,255,.1);
            z-index: 0;
        }
        .progress-fill {
            position: absolute;
            top: 20px; left: 20px;
            height: 2px;
            background: linear-gradient(90deg, var(--indigo), var(--purple));
            z-index: 1;
            transition: width .4s ease;
            box-shadow: 0 0 8px rgba(99,102,241,.5);
        }
        .prog-step {
            display: flex; flex-direction: column; align-items: center;
            gap: .4rem; position: relative; z-index: 2;
        }
        .prog-dot {
            width: 40px; height: 40px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: .85rem;
            border: 2px solid rgba(255,255,255,.15);
            background: var(--bg2);
            transition: all .3s;
            cursor: pointer;
        }
        .prog-dot.active {
            background: linear-gradient(135deg, var(--indigo), var(--purple));
            border-color: var(--indigo);
            box-shadow: 0 0 18px rgba(99,102,241,.5);
        }
        .prog-dot.done {
            background: linear-gradient(135deg, #4ade80, #22d3ee);
            border-color: #4ade80;
            box-shadow: 0 0 12px rgba(74,222,128,.4);
        }
        .prog-label {
            font-size: .7rem; color: var(--muted);
            text-align: center; max-width: 70px;
            transition: color .3s;
        }
        .prog-step.active .prog-label { color: var(--text); }

        /* ── ERROR ALERT ── */
        .alert-error {
            background: rgba(248,113,113,.1);
            border: 1px solid rgba(248,113,113,.3);
            border-radius: .75rem;
            padding: .9rem 1.25rem;
            margin-bottom: 1.5rem;
            display: flex; align-items: center; gap: .75rem;
            font-size: .9rem; color: #fca5a5;
        }

        /* ── SUCCESS ALERT ── */
        .alert-success {
            background: rgba(74,222,128,.1);
            border: 1px solid rgba(74,222,128,.3);
            border-radius: .75rem;
            padding: .9rem 1.25rem;
            margin-bottom: 1.5rem;
            display: flex; align-items: center; gap: .75rem;
            font-size: .9rem; color: #86efac;
        }

        /* ── FORM CARD ── */
        .form-card {
            background: rgba(255,255,255,.05);
            border: 1px solid var(--border);
            border-radius: 1.5rem;
            backdrop-filter: blur(20px);
            box-shadow: 0 25px 60px rgba(0,0,0,.4);
            overflow: hidden;
        }

        /* ── STEP PANELS ── */
        .step-panel { display: none; padding: 2.5rem; }
        .step-panel.active { display: block; animation: fadeIn .35s ease; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }

        .step-title {
            display: flex; align-items: center; gap: .75rem;
            font-size: 1.15rem; font-weight: 700;
            margin-bottom: 1.75rem;
            padding-bottom: .75rem;
            border-bottom: 1px solid var(--border);
        }
        .step-icon {
            width: 40px; height: 40px; border-radius: .6rem;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem;
        }
        .icon-indigo { background: rgba(99,102,241,.2); color: #818cf8; }
        .icon-purple { background: rgba(139,92,246,.2); color: #a78bfa; }
        .icon-pink   { background: rgba(236,72,153,.2);  color: #f472b6; }
        .icon-green  { background: rgba(74,222,128,.2);  color: #4ade80; }

        /* ── FORM GRID ── */
        .form-grid { display: grid; gap: 1.25rem; }
        .form-grid.cols-2 { grid-template-columns: 1fr 1fr; }
        .form-grid.cols-3 { grid-template-columns: 1fr 1fr 1fr; }
        @media (max-width: 600px) {
            .form-grid.cols-2,
            .form-grid.cols-3 { grid-template-columns: 1fr; }
        }
        .form-group { display: flex; flex-direction: column; gap: .5rem; }
        .form-group.full { grid-column: 1 / -1; }

        label {
            font-size: .85rem; font-weight: 600;
            color: rgba(255,255,255,.75);
        }
        label .req { color: var(--pink); margin-left: 2px; }

        input[type=text], input[type=number], input[type=email],
        select, textarea {
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: .65rem;
            padding: .75rem 1rem;
            color: var(--text);
            font-size: .9rem;
            font-family: 'Inter', sans-serif;
            width: 100%;
            transition: border-color .2s, box-shadow .2s;
            outline: none;
        }
        input::placeholder, textarea::placeholder { color: rgba(255,255,255,.3); }
        input:focus, select:focus, textarea:focus {
            border-color: var(--indigo);
            box-shadow: 0 0 0 3px var(--input-focus);
        }
        select option { background: #1e1b4b; color: var(--text); }
        textarea { resize: vertical; min-height: 90px; }

        /* ── SKILLS TAG INPUT ── */
        .tag-input-container {
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: .65rem;
            padding: .6rem .75rem;
            display: flex; flex-wrap: wrap; gap: .4rem;
            min-height: 54px;
            cursor: text;
            transition: border-color .2s, box-shadow .2s;
        }
        .tag-input-container:focus-within {
            border-color: var(--indigo);
            box-shadow: 0 0 0 3px var(--input-focus);
        }
        .skill-tag {
            display: inline-flex; align-items: center; gap: .35rem;
            background: rgba(99,102,241,.25);
            border: 1px solid rgba(99,102,241,.4);
            border-radius: 999px;
            padding: .25rem .7rem;
            font-size: .8rem; color: #c7d2fe;
            animation: popIn .2s ease;
        }
        @keyframes popIn {
            from { transform: scale(.7); opacity: 0; }
            to   { transform: scale(1); opacity: 1; }
        }
        .skill-tag .remove-tag {
            cursor: pointer; color: rgba(255,255,255,.5);
            font-size: .75rem; line-height: 1;
            transition: color .2s;
        }
        .skill-tag .remove-tag:hover { color: var(--danger); }
        #skillInput {
            background: transparent; border: none;
            outline: none; color: var(--text);
            font-size: .88rem; min-width: 120px;
            font-family: 'Inter', sans-serif;
            flex: 1;
        }
        .skill-suggestions {
            background: #1e1b4b;
            border: 1px solid rgba(99,102,241,.3);
            border-radius: .65rem;
            padding: .4rem 0;
            margin-top: .25rem;
            display: none;
            max-height: 180px; overflow-y: auto;
        }
        .skill-suggestions.show { display: block; }
        .suggestion-item {
            padding: .5rem 1rem;
            cursor: pointer; font-size: .875rem;
            transition: background .15s;
        }
        .suggestion-item:hover { background: rgba(99,102,241,.2); }

        /* ── DYNAMIC ROWS (Experience / Certs) ── */
        .dynamic-section { display: flex; flex-direction: column; gap: 1rem; }
        .dynamic-row {
            background: rgba(255,255,255,.04);
            border: 1px solid var(--border);
            border-radius: .9rem;
            padding: 1.25rem;
            position: relative;
            animation: fadeIn .3s ease;
        }
        .row-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 1rem;
        }
        .row-num {
            font-size: .8rem; font-weight: 700;
            color: var(--muted); text-transform: uppercase; letter-spacing: .05em;
        }
        .remove-row {
            background: rgba(248,113,113,.1);
            border: 1px solid rgba(248,113,113,.25);
            color: #f87171; border-radius: .5rem;
            padding: .25rem .6rem; cursor: pointer;
            font-size: .78rem; font-weight: 600;
            transition: background .2s;
        }
        .remove-row:hover { background: rgba(248,113,113,.25); }
        .add-row-btn {
            display: inline-flex; align-items: center; gap: .5rem;
            background: rgba(99,102,241,.1);
            border: 1px dashed rgba(99,102,241,.4);
            color: #818cf8; border-radius: .75rem;
            padding: .7rem 1.25rem; cursor: pointer;
            font-size: .875rem; font-weight: 600;
            transition: all .2s; width: 100%;
            justify-content: center;
        }
        .add-row-btn:hover {
            background: rgba(99,102,241,.2);
            border-style: solid;
        }

        /* ── FILE UPLOAD ── */
        .file-upload-area {
            border: 2px dashed rgba(99,102,241,.4);
            border-radius: .9rem;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all .25s;
            background: rgba(99,102,241,.04);
            position: relative;
        }
        .file-upload-area:hover, .file-upload-area.drag-over {
            border-color: var(--indigo);
            background: rgba(99,102,241,.1);
        }
        .file-upload-area input[type=file] {
            position: absolute; inset: 0; opacity: 0;
            cursor: pointer; width: 100%; height: 100%;
        }
        .upload-icon { font-size: 2.5rem; color: var(--indigo); margin-bottom: .75rem; }
        .upload-title { font-weight: 700; font-size: 1rem; margin-bottom: .35rem; }
        .upload-sub { font-size: .82rem; color: var(--muted); }
        .file-selected {
            display: flex; align-items: center; gap: .75rem;
            background: rgba(74,222,128,.1);
            border: 1px solid rgba(74,222,128,.3);
            border-radius: .65rem; padding: .75rem 1rem;
            font-size: .875rem; color: #86efac;
            margin-top: .75rem;
        }
        .existing-cv {
            display: flex; align-items: center; gap: .75rem;
            background: rgba(99,102,241,.1);
            border: 1px solid rgba(99,102,241,.3);
            border-radius: .65rem; padding: .75rem 1rem;
            font-size: .875rem; color: #a5b4fc;
            margin-bottom: .75rem;
        }

        /* ── NAV BUTTONS ── */
        .form-nav {
            display: flex; justify-content: space-between; align-items: center;
            padding: 1.5rem 2.5rem;
            border-top: 1px solid var(--border);
            background: rgba(255,255,255,.02);
        }
        .btn {
            display: inline-flex; align-items: center; gap: .5rem;
            font-size: .9rem; font-weight: 700;
            border-radius: .75rem; padding: .75rem 1.75rem;
            cursor: pointer; border: none; transition: all .2s;
            font-family: 'Inter', sans-serif;
        }
        .btn-outline {
            background: rgba(255,255,255,.06);
            border: 1px solid var(--border);
            color: var(--text);
        }
        .btn-outline:hover { background: rgba(255,255,255,.12); }
        .btn-primary {
            background: linear-gradient(135deg, var(--indigo), var(--purple));
            color: white;
            box-shadow: 0 0 20px rgba(99,102,241,.35);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 30px rgba(99,102,241,.55);
        }
        .btn-success {
            background: linear-gradient(135deg, #4ade80, #22d3ee);
            color: #0f172a;
            box-shadow: 0 0 20px rgba(74,222,128,.35);
        }
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 30px rgba(74,222,128,.55);
        }

        /* ── HELPER TEXT ── */
        .help-text { font-size: .78rem; color: var(--muted); margin-top: .2rem; }

        /* ── RANGE SLIDER ── */
        .slider-group { display: flex; flex-direction: column; gap: .4rem; }
        .slider-row { display: flex; align-items: center; gap: .75rem; }
        .slider-value {
            min-width: 2.2rem; text-align: center;
            background: rgba(99,102,241,.2);
            border: 1px solid rgba(99,102,241,.35);
            border-radius: .45rem;
            padding: .15rem .4rem;
            font-size: .85rem; font-weight: 700; color: #c7d2fe;
        }
        input[type=range] {
            -webkit-appearance: none;
            width: 100%; height: 6px;
            border-radius: 3px;
            background: rgba(255,255,255,.1);
            outline: none;
            border: none;
            padding: 0;
            box-shadow: none;
        }
        input[type=range]::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 18px; height: 18px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--indigo), var(--purple));
            cursor: pointer;
            box-shadow: 0 0 8px rgba(99,102,241,.5);
            transition: transform .15s;
        }
        input[type=range]::-webkit-slider-thumb:hover { transform: scale(1.2); }
        input[type=range]:focus { box-shadow: none; border: none; }

        .score-section-label {
            font-size: .78rem; font-weight: 700;
            color: var(--muted); text-transform: uppercase;
            letter-spacing: .08em; margin: 1rem 0 .6rem;
            padding-bottom: .4rem;
            border-bottom: 1px solid var(--border);
        }

        /* ── FOOTER ── */
        footer {
            text-align: center;
            padding: 2rem;
            border-top: 1px solid var(--border);
            color: var(--muted);
            font-size: .85rem;
        }
        footer strong { color: var(--text); }
    </style>
</head>
<body>

<!-- ── NAV ── -->
<nav>
    <div class="nav-brand">
        <img src="logo.png" alt="Logo">
        <span>Hackathon Career Readiness</span>
    </div>
    <div class="nav-right">
        <a href="dashboard.php" class="nav-link"><i class="fa-solid fa-gauge"></i> Dashboard</a>
        <div class="user-pill">
            <i class="fa-solid fa-circle-user"></i>
            <?= $userName ?>
        </div>
        <a href="logout.php" class="logout-btn">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </div>
</nav>

<!-- ── PAGE ── -->
<div class="page-wrapper">

    <!-- HEADER -->
    <div class="page-header">
        <div class="page-badge">
            <i class="fa-solid fa-id-card"></i>
            <?= $isEdit ? 'Update Your Profile' : 'Complete Your Profile' ?>
        </div>
        <h1><?= $isEdit ? 'Edit Your <span>Student Profile</span>' : 'Build Your <span>Career Profile</span>' ?></h1>
        <p><?= $isEdit
            ? 'Keep your profile up-to-date so the AI predictor gives you the most accurate results.'
            : 'Fill in your academic details, skills, experience and certificates to unlock your personalised AI career prediction.' ?></p>
    </div>

    <?php if ($errorMsg): ?>
    <div class="alert-error">
        <i class="fa-solid fa-circle-exclamation"></i>
        <?= $errorMsg ?>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['profile']) && $_GET['profile'] === 'saved'): ?>
    <div class="alert-success">
        <i class="fa-solid fa-circle-check"></i>
        Profile saved successfully! Head to the <a href="dashboard.php" style="color:#4ade80;font-weight:700;">Dashboard</a> to launch the AI Predictor.
    </div>
    <?php endif; ?>

    <!-- PROGRESS BAR -->
    <div class="progress-container">
        <div class="progress-steps" id="progressSteps">
            <div class="progress-fill" id="progressFill" style="width:0%"></div>
            <div class="prog-step active" data-step="1" onclick="goToStep(1)">
                <div class="prog-dot active" id="dot-1"><i class="fa-solid fa-graduation-cap"></i></div>
                <span class="prog-label">Academic Info</span>
            </div>
            <div class="prog-step" data-step="2" onclick="goToStep(2)">
                <div class="prog-dot" id="dot-2"><i class="fa-solid fa-code"></i></div>
                <span class="prog-label">Skills</span>
            </div>
            <div class="prog-step" data-step="3" onclick="goToStep(3)">
                <div class="prog-dot" id="dot-3"><i class="fa-solid fa-briefcase"></i></div>
                <span class="prog-label">Experience</span>
            </div>
            <div class="prog-step" data-step="4" onclick="goToStep(4)">
                <div class="prog-dot" id="dot-4"><i class="fa-solid fa-certificate"></i></div>
                <span class="prog-label">Certs & CV</span>
            </div>
            <div class="prog-step" data-step="5" onclick="goToStep(5)">
                <div class="prog-dot" id="dot-5"><i class="fa-solid fa-brain"></i></div>
                <span class="prog-label">AI Scores</span>
            </div>
        </div>
    </div>

    <!-- FORM -->
    <form id="profileForm" method="POST" action="save_student_details.php" enctype="multipart/form-data">
        <input type="hidden" name="skills_json" id="skillsJson" value="<?= htmlspecialchars(json_encode($profile['skills'] ?? [])) ?>">
        <input type="hidden" name="keep_existing_cv" id="keepExistingCv" value="<?= $isEdit && $profile['cv_filename'] ? '1' : '0' ?>">

        <div class="form-card">

            <!-- ───── STEP 1: Academic Info ───── -->
            <div class="step-panel active" id="step-1">
                <div class="step-title">
                    <div class="step-icon icon-indigo"><i class="fa-solid fa-graduation-cap"></i></div>
                    Academic Information
                </div>
                <div class="form-grid cols-2">
                    <div class="form-group">
                        <label for="degree">Degree / Programme <span class="req">*</span></label>
                        <input type="text" id="degree" name="degree"
                               placeholder="e.g. B.Tech Computer Science"
                               value="<?= htmlspecialchars($profile['degree'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="graduation_year">Graduation Year <span class="req">*</span></label>
                        <select id="graduation_year" name="graduation_year" required>
                            <option value="">— Select year —</option>
                            <?php
                            $curYear = (int)date('Y');
                            for ($y = $curYear - 6; $y <= $curYear + 4; $y++) {
                                $sel = (isset($profile['graduation_year']) && (int)$profile['graduation_year'] === $y) ? 'selected' : '';
                                echo "<option value=\"$y\" $sel>$y</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label for="college">College / University <span class="req">*</span></label>
                        <input type="text" id="college" name="college"
                               placeholder="e.g. IIT Delhi, VIT Vellore"
                               value="<?= htmlspecialchars($profile['college'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="cgpa">CGPA / Percentage <span class="req">*</span></label>
                        <input type="number" id="cgpa" name="cgpa"
                               step="0.01" min="0" max="10"
                               placeholder="e.g. 8.5"
                               value="<?= htmlspecialchars($profile['cgpa'] ?? '') ?>" required>
                        <span class="help-text">Enter on a 10-point scale (or percentage ÷ 10).</span>
                    </div>
                </div>
            </div>

            <!-- ───── STEP 2: Skills ───── -->
            <div class="step-panel" id="step-2">
                <div class="step-title">
                    <div class="step-icon icon-purple"><i class="fa-solid fa-code"></i></div>
                    Skills
                </div>
                <div class="form-group">
                    <label>Add your technical & soft skills</label>
                    <div class="tag-input-container" id="tagContainer" onclick="document.getElementById('skillInput').focus()">
                        <!-- tags injected by JS -->
                        <input type="text" id="skillInput" placeholder="Type a skill and press Enter or comma…" autocomplete="off">
                    </div>
                    <div class="skill-suggestions" id="skillSuggestions"></div>
                    <span class="help-text">Press <kbd style="background:rgba(255,255,255,.1);border-radius:4px;padding:1px 5px">Enter</kbd> or <kbd style="background:rgba(255,255,255,.1);border-radius:4px;padding:1px 5px">,</kbd> to add. Click × to remove.</span>
                </div>
            </div>

            <!-- ───── STEP 3: Experience ───── -->
            <div class="step-panel" id="step-3">
                <div class="step-title">
                    <div class="step-icon icon-pink"><i class="fa-solid fa-briefcase"></i></div>
                    Work Experience & Internships
                </div>
                <div class="dynamic-section" id="expSection">
                    <!-- Rows injected by JS -->
                </div>
                <button type="button" class="add-row-btn" id="addExpBtn" style="margin-top:.75rem">
                    <i class="fa-solid fa-plus"></i> Add Experience / Internship
                </button>
                <p class="help-text" style="text-align:center;margin-top:.5rem">Leave blank if you have no experience yet — that's okay!</p>
            </div>

            <!-- ───── STEP 4: Certificates & CV ───── -->
            <div class="step-panel" id="step-4">
                <div class="step-title">
                    <div class="step-icon icon-green"><i class="fa-solid fa-certificate"></i></div>
                    Certificates & CV Upload
                </div>

                <!-- Certificates -->
                <div class="dynamic-section" id="certSection"></div>
                <button type="button" class="add-row-btn" id="addCertBtn" style="margin-top:.75rem;margin-bottom:2rem">
                    <i class="fa-solid fa-plus"></i> Add Certificate
                </button>

                <!-- CV Upload -->
                <label style="margin-bottom:.5rem;display:block">Upload CV (PDF only, max 5 MB)</label>

                <?php if ($isEdit && $profile['cv_filename']): ?>
                <div class="existing-cv">
                    <i class="fa-solid fa-file-pdf" style="font-size:1.2rem"></i>
                    <div>
                        <div style="font-weight:700">Existing CV on file</div>
                        <div style="font-size:.78rem;opacity:.7"><?= htmlspecialchars($profile['cv_filename']) ?></div>
                    </div>
                    <a href="download_cv.php" target="_blank"
                       style="margin-left:auto;color:#a5b4fc;font-size:.8rem;text-decoration:none">
                       <i class="fa-solid fa-download"></i> Download
                    </a>
                </div>
                <p class="help-text" style="margin-bottom:.75rem">Upload a new PDF below to replace it, or leave blank to keep the existing one.</p>
                <?php endif; ?>

                <div class="file-upload-area" id="uploadArea">
                    <input type="file" name="cv" id="cvInput" accept="application/pdf">
                    <div class="upload-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                    <div class="upload-title">Drag & drop your CV here</div>
                    <div class="upload-sub">PDF format · Max 5 MB</div>
                </div>
                <div class="file-selected" id="fileSelected" style="display:none">
                    <i class="fa-solid fa-file-pdf" style="font-size:1.2rem"></i>
                    <span id="fileSelectedName"></span>
                    <button type="button" id="clearFile"
                            style="margin-left:auto;background:none;border:none;color:#f87171;cursor:pointer;font-size:.85rem">
                        <i class="fa-solid fa-xmark"></i> Remove
                    </button>
                </div>
            </div>

            <!-- ───── STEP 5: AI Readiness Scores ───── -->
            <div class="step-panel" id="step-5">
                <div class="step-title">
                    <div class="step-icon" style="background:rgba(251,191,36,.15);color:#fbbf24"><i class="fa-solid fa-brain"></i></div>
                    AI Readiness Scores
                </div>
                <p style="color:var(--muted);font-size:.875rem;margin-bottom:1.5rem">Rate yourself honestly from 0–10 on the following dimensions. These scores feed directly into the ML model.</p>

                <div class="score-section-label"><i class="fa-solid fa-code" style="margin-right:.4rem"></i>Core Technical Skills</div>
                <div class="form-grid cols-2">
                    <?php
                    $scoreFields = [
                        'python_score'       => ['Python Proficiency',       'fa-brands fa-python'],
                        'sql_score'          => ['SQL & Databases',           'fa-solid fa-database'],
                        'statistics_score'   => ['Statistics & Math',         'fa-solid fa-chart-line'],
                        'ml_score'           => ['Machine Learning',          'fa-solid fa-robot'],
                        'dl_score'           => ['Deep Learning / Neural Nets','fa-solid fa-network-wired'],
                        'genai_score'        => ['Generative AI / LLMs',      'fa-solid fa-wand-magic-sparkles'],
                    ];
                    foreach ($scoreFields as $field => [$label, $icon]):
                        $val = htmlspecialchars($profile[$field] ?? 5.0);
                    ?>
                    <div class="form-group slider-group">
                        <label for="<?= $field ?>"><i class="<?= $icon ?>" style="margin-right:.4rem;opacity:.75"></i><?= $label ?></label>
                        <div class="slider-row">
                            <input type="range" id="<?= $field ?>" name="<?= $field ?>" min="0" max="10" step="0.5"
                                   value="<?= $val ?>"
                                   oninput="document.getElementById('v_<?= $field ?>').textContent=this.value">
                            <span class="slider-value" id="v_<?= $field ?>"><?= $val ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="score-section-label" style="margin-top:1.5rem"><i class="fa-solid fa-folder-open" style="margin-right:.4rem"></i>Projects & Portfolio</div>
                <div class="form-grid cols-3">
                    <?php
                    $countFields = [
                        'ml_projects'         => ['ML Projects Built',       0, 30],
                        'end_to_end_projects' => ['End-to-End Projects',      0, 20],
                        'deployed_projects'   => ['Deployed Projects',        0, 20],
                        'github_projects'     => ['GitHub Repos',             0, 100],
                        'internship_months'   => ['Internship Months',        0, 24],
                        'certifications'      => ['Certifications Earned',    0, 30],
                    ];
                    foreach ($countFields as $field => [$label, $min, $max]):
                        $val = (int)($profile[$field] ?? 0);
                    ?>
                    <div class="form-group">
                        <label for="<?= $field ?>"><?= $label ?></label>
                        <input type="number" id="<?= $field ?>" name="<?= $field ?>" min="<?= $min ?>" max="<?= $max ?>" value="<?= $val ?>" placeholder="0">
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="score-section-label" style="margin-top:1.5rem"><i class="fa-solid fa-trophy" style="margin-right:.4rem"></i>Competitions & Hackathons</div>
                <div class="form-grid cols-3">
                    <?php
                    $hackFields = [
                        'kaggle_competitions' => ['Kaggle Competitions',      0, 50],
                        'hackathons_attended' => ['Hackathons Attended',      0, 50],
                        'hackathons_won'      => ['Hackathons Won',           0, 20],
                        'finalist_status'     => ['Finalist Appearances',     0, 20],
                        'age'                 => ['Your Age',                 17, 35],
                    ];
                    foreach ($hackFields as $field => [$label, $min, $max]):
                        $val = (int)($profile[$field] ?? ($field === 'age' ? 22 : 0));
                    ?>
                    <div class="form-group">
                        <label for="<?= $field ?>"><?= $label ?></label>
                        <input type="number" id="<?= $field ?>" name="<?= $field ?>" min="<?= $min ?>" max="<?= $max ?>" value="<?= $val ?>" placeholder="<?= $min ?>">
                    </div>
                    <?php endforeach; ?>
                    <div class="form-group">
                        <label for="best_competition_rank">Best Competition Rank <span style="color:var(--muted);font-weight:400">(optional)</span></label>
                        <input type="number" id="best_competition_rank" name="best_competition_rank" min="1" max="10000"
                               value="<?= htmlspecialchars($profile['best_competition_rank'] ?? '') ?>" placeholder="e.g. 15 (leave blank if never competed)">
                    </div>
                </div>

                <div class="score-section-label" style="margin-top:1.5rem"><i class="fa-solid fa-comments" style="margin-right:.4rem"></i>Interview & Soft Skills</div>
                <div class="form-grid cols-2">
                    <?php
                    $interviewFields = [
                        'ml_interview_score'  => ['ML / Technical Interview',    'fa-solid fa-laptop-code'],
                        'communication_score' => ['Communication Skills',         'fa-solid fa-comments'],
                        'dsa_score'           => ['Data Structures & Algorithms', 'fa-solid fa-sitemap'],
                        'resume_score'        => ['Resume Quality',               'fa-solid fa-file-alt'],
                        'mock_interview_score'=> ['Mock Interview Performance',   'fa-solid fa-user-tie'],
                    ];
                    foreach ($interviewFields as $field => [$label, $icon]):
                        $val = htmlspecialchars($profile[$field] ?? 5.0);
                    ?>
                    <div class="form-group slider-group">
                        <label for="<?= $field ?>"><i class="<?= $icon ?>" style="margin-right:.4rem;opacity:.75"></i><?= $label ?></label>
                        <div class="slider-row">
                            <input type="range" id="<?= $field ?>" name="<?= $field ?>" min="0" max="10" step="0.5"
                                   value="<?= $val ?>"
                                   oninput="document.getElementById('v_<?= $field ?>').textContent=this.value">
                            <span class="slider-value" id="v_<?= $field ?>"><?= $val ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div><!-- /form-card -->

        <!-- ── NAVIGATION BUTTONS ── -->
        <div class="form-nav">
            <button type="button" class="btn btn-outline" id="prevBtn" style="visibility:hidden" onclick="changeStep(-1)">
                <i class="fa-solid fa-chevron-left"></i> Back
            </button>
            <span id="stepIndicator" style="color:var(--muted);font-size:.85rem">Step 1 of 5</span>
            <button type="button" class="btn btn-primary" id="nextBtn" onclick="changeStep(1)">
                Next <i class="fa-solid fa-chevron-right"></i>
            </button>
            <button type="submit" class="btn btn-success" id="submitBtn" style="display:none">
                <i class="fa-solid fa-rocket"></i>
                <?= $isEdit ? 'Update & Predict' : 'Save & Predict' ?>
            </button>
        </div>

    </form>
</div><!-- /page-wrapper -->

<footer>
    <i class="fa-solid fa-trophy"></i>&nbsp;
    <strong>Hackathon Success & AI Career Readiness Prediction System</strong>
    &nbsp;|&nbsp; Built by Shilpi, Dushyant & Vedika · B.Tech CSE Group Project
</footer>

<script>
// ═══════════════════════════════════════════════════════════════
// INITIAL DATA from PHP
// ═══════════════════════════════════════════════════════════════
const initialSkills      = <?= json_encode($profile['skills']       ?? []) ?>;
const initialExperience  = <?= json_encode($profile['experience']   ?? []) ?>;
const initialCerts       = <?= json_encode($profile['certificates'] ?? []) ?>;

// ═══════════════════════════════════════════════════════════════
// STEP NAVIGATION
// ═══════════════════════════════════════════════════════════════
let currentStep = 1;
const totalSteps = 5;

function goToStep(n) {
    if (n < 1 || n > totalSteps) return;
    document.getElementById('step-' + currentStep).classList.remove('active');
    document.querySelectorAll('.prog-step').forEach(s => s.classList.remove('active'));

    currentStep = n;
    document.getElementById('step-' + currentStep).classList.add('active');
    document.querySelectorAll('.prog-step[data-step="' + currentStep + '"]')[0].classList.add('active');

    updateProgressBar();
    updateNavButtons();
}

function changeStep(dir) {
    if (dir === 1 && !validateCurrentStep()) return;
    goToStep(currentStep + dir);
}

function updateProgressBar() {
    const pct = ((currentStep - 1) / (totalSteps - 1)) * 100;
    document.getElementById('progressFill').style.width = pct + '%';

    // Update dots
    for (let i = 1; i <= totalSteps; i++) {
        const dot = document.getElementById('dot-' + i);
        dot.classList.remove('active', 'done');
        if (i < currentStep) dot.classList.add('done');
        else if (i === currentStep) dot.classList.add('active');
    }
}

function updateNavButtons() {
    document.getElementById('prevBtn').style.visibility = currentStep > 1 ? 'visible' : 'hidden';
    document.getElementById('nextBtn').style.display     = currentStep < totalSteps ? 'inline-flex' : 'none';
    document.getElementById('submitBtn').style.display   = currentStep === totalSteps ? 'inline-flex' : 'none';
    document.getElementById('stepIndicator').textContent = `Step ${currentStep} of ${totalSteps}`;
}

// Colour range sliders dynamically based on value
function updateSliderTrack(input) {
    const pct = (parseFloat(input.value) / parseFloat(input.max)) * 100;
    const color1 = '#6366f1';
    const color2 = '#8b5cf6';
    input.style.background = `linear-gradient(90deg, ${color1} ${pct}%, rgba(255,255,255,0.1) ${pct}%)`;
}
document.querySelectorAll('input[type=range]').forEach(r => {
    updateSliderTrack(r);
    r.addEventListener('input', () => updateSliderTrack(r));
});

function validateCurrentStep() {
    if (currentStep === 1) {
        const degree  = document.getElementById('degree').value.trim();
        const college = document.getElementById('college').value.trim();
        const cgpa    = parseFloat(document.getElementById('cgpa').value);
        const year    = document.getElementById('graduation_year').value;
        if (!degree)  { shake('degree');  return false; }
        if (!college) { shake('college'); return false; }
        if (isNaN(cgpa) || cgpa < 0 || cgpa > 10) { shake('cgpa'); return false; }
        if (!year)    { shake('graduation_year'); return false; }
    }
    return true;
}

function shake(id) {
    const el = document.getElementById(id);
    el.style.animation = 'none';
    el.style.borderColor = '#f87171';
    el.style.boxShadow  = '0 0 0 3px rgba(248,113,113,.3)';
    setTimeout(() => {
        el.style.borderColor = '';
        el.style.boxShadow   = '';
    }, 1800);
    el.focus();
}

// ═══════════════════════════════════════════════════════════════
// SKILLS TAG INPUT
// ═══════════════════════════════════════════════════════════════
const SKILL_SUGGESTIONS = [
    'Python','JavaScript','Java','C++','C#','TypeScript','Go','Rust','PHP','Swift',
    'SQL','MySQL','PostgreSQL','MongoDB','Redis','Firebase',
    'React','Angular','Vue.js','Next.js','Node.js','Django','Flask','FastAPI','Spring Boot',
    'Machine Learning','Deep Learning','TensorFlow','PyTorch','Scikit-learn','OpenCV','NLP',
    'Data Analysis','Pandas','NumPy','Matplotlib','Seaborn','Power BI','Tableau',
    'HTML','CSS','Bootstrap','Tailwind CSS',
    'Docker','Kubernetes','AWS','Azure','GCP','Git','GitHub','CI/CD','Linux',
    'Communication','Teamwork','Leadership','Problem Solving','Critical Thinking',
    'Agile','Scrum','REST API','GraphQL','Cybersecurity','Blockchain'
];

let skills = [...initialSkills];

function renderTags() {
    const container = document.getElementById('tagContainer');
    // Remove old tags only
    container.querySelectorAll('.skill-tag').forEach(t => t.remove());
    skills.forEach((s, i) => {
        const tag = document.createElement('span');
        tag.className = 'skill-tag';
        tag.innerHTML = `${s} <span class="remove-tag" onclick="removeSkill(${i})">×</span>`;
        container.insertBefore(tag, document.getElementById('skillInput'));
    });
    document.getElementById('skillsJson').value = JSON.stringify(skills);
}

function addSkill(val) {
    val = val.trim();
    if (!val || skills.includes(val)) return;
    skills.push(val);
    renderTags();
}

function removeSkill(i) {
    skills.splice(i, 1);
    renderTags();
}

const skillInput = document.getElementById('skillInput');
const suggBox    = document.getElementById('skillSuggestions');

skillInput.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        addSkill(skillInput.value.replace(/,/g, ''));
        skillInput.value = '';
        suggBox.classList.remove('show');
    } else if (e.key === 'Backspace' && !skillInput.value && skills.length) {
        skills.pop();
        renderTags();
    }
});

skillInput.addEventListener('input', () => {
    const q = skillInput.value.trim().toLowerCase();
    if (!q) { suggBox.classList.remove('show'); return; }
    const matches = SKILL_SUGGESTIONS.filter(s => s.toLowerCase().includes(q) && !skills.includes(s)).slice(0, 8);
    if (!matches.length) { suggBox.classList.remove('show'); return; }
    suggBox.innerHTML = matches.map(s =>
        `<div class="suggestion-item" onclick="addSkill('${s}');skillInput.value='';suggBox.classList.remove('show')">${s}</div>`
    ).join('');
    suggBox.classList.add('show');
});

document.addEventListener('click', e => {
    if (!e.target.closest('#tagContainer') && !e.target.closest('#skillSuggestions')) {
        suggBox.classList.remove('show');
    }
});

// ═══════════════════════════════════════════════════════════════
// EXPERIENCE DYNAMIC ROWS
// ═══════════════════════════════════════════════════════════════
let expCount = 0;

function addExpRow(data = {}) {
    expCount++;
    const n = expCount;
    const div = document.createElement('div');
    div.className = 'dynamic-row';
    div.id = 'exp-row-' + n;
    div.innerHTML = `
        <div class="row-header">
            <span class="row-num">Experience / Internship #${n}</span>
            <button type="button" class="remove-row" onclick="removeRow('exp-row-${n}')">✕ Remove</button>
        </div>
        <div class="form-grid cols-3">
            <div class="form-group">
                <label>Company / Organisation</label>
                <input type="text" name="exp_company[]" placeholder="e.g. Google, TCS" value="${escHtml(data.company||'')}">
            </div>
            <div class="form-group">
                <label>Role / Designation</label>
                <input type="text" name="exp_role[]" placeholder="e.g. ML Intern" value="${escHtml(data.role||'')}">
            </div>
            <div class="form-group">
                <label>Duration</label>
                <input type="text" name="exp_duration[]" placeholder="e.g. Jun 2024 – Aug 2024" value="${escHtml(data.duration||'')}">
            </div>
        </div>`;
    document.getElementById('expSection').appendChild(div);
}

document.getElementById('addExpBtn').addEventListener('click', () => addExpRow());

// ═══════════════════════════════════════════════════════════════
// CERTIFICATE DYNAMIC ROWS
// ═══════════════════════════════════════════════════════════════
let certCount = 0;

function addCertRow(data = {}) {
    certCount++;
    const n = certCount;
    const div = document.createElement('div');
    div.className = 'dynamic-row';
    div.id = 'cert-row-' + n;
    div.innerHTML = `
        <div class="row-header">
            <span class="row-num">Certificate #${n}</span>
            <button type="button" class="remove-row" onclick="removeRow('cert-row-${n}')">✕ Remove</button>
        </div>
        <div class="form-grid cols-3">
            <div class="form-group">
                <label>Certificate Name</label>
                <input type="text" name="cert_name[]" placeholder="e.g. AWS Cloud Practitioner" value="${escHtml(data.name||'')}">
            </div>
            <div class="form-group">
                <label>Issuing Organisation</label>
                <input type="text" name="cert_issuer[]" placeholder="e.g. Amazon, Coursera" value="${escHtml(data.issuer||'')}">
            </div>
            <div class="form-group">
                <label>Year</label>
                <input type="text" name="cert_year[]" placeholder="e.g. 2024" value="${escHtml(data.year||'')}">
            </div>
        </div>`;
    document.getElementById('certSection').appendChild(div);
}

document.getElementById('addCertBtn').addEventListener('click', () => addCertRow());

function removeRow(id) {
    const el = document.getElementById(id);
    if (el) el.remove();
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ═══════════════════════════════════════════════════════════════
// FILE UPLOAD UI
// ═══════════════════════════════════════════════════════════════
const cvInput    = document.getElementById('cvInput');
const uploadArea = document.getElementById('uploadArea');
const fileSelected    = document.getElementById('fileSelected');
const fileSelectedName = document.getElementById('fileSelectedName');

cvInput.addEventListener('change', () => {
    if (cvInput.files.length) {
        const f = cvInput.files[0];
        fileSelectedName.textContent = f.name + ' (' + (f.size/1024/1024).toFixed(2) + ' MB)';
        fileSelected.style.display = 'flex';
        document.getElementById('keepExistingCv').value = '0';
    }
});

document.getElementById('clearFile').addEventListener('click', () => {
    cvInput.value = '';
    fileSelected.style.display = 'none';
    document.getElementById('keepExistingCv').value = '<?= ($isEdit && $profile['cv_filename']) ? '1' : '0' ?>';
});

uploadArea.addEventListener('dragover', e => { e.preventDefault(); uploadArea.classList.add('drag-over'); });
uploadArea.addEventListener('dragleave', () => uploadArea.classList.remove('drag-over'));
uploadArea.addEventListener('drop', e => {
    e.preventDefault(); uploadArea.classList.remove('drag-over');
    if (e.dataTransfer.files.length) {
        cvInput.files = e.dataTransfer.files;
        cvInput.dispatchEvent(new Event('change'));
    }
});

// ═══════════════════════════════════════════════════════════════
// INIT — populate existing data
// ═══════════════════════════════════════════════════════════════
renderTags();

if (initialExperience.length) {
    initialExperience.forEach(row => addExpRow(row));
} else {
    addExpRow(); // start with one blank row
}

if (initialCerts.length) {
    initialCerts.forEach(row => addCertRow(row));
} else {
    addCertRow(); // start with one blank row
}

updateProgressBar();
updateNavButtons();
</script>
</body>
</html>
