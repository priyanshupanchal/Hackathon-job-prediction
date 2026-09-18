<?php
session_start();

// ── Session guard ──────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header("Location: http://localhost/hackathon-employability-ml/index.html");
    exit();
}

$userId   = (int) $_SESSION['user_id'];
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Student');

// ── Load student profile from DB ───────────────────────────────────────────────
$con = mysqli_connect("localhost", "root", "", "hackathon-employability-ml");
if (!$con) die("DB connection failed: " . mysqli_connect_error());

// Suppress mysqli exceptions so we can handle them gracefully
mysqli_report(MYSQLI_REPORT_OFF);

$stmt = $con->prepare("SELECT degree, college, cgpa, graduation_year,
    age, python_score, sql_score, statistics_score, ml_score, dl_score, genai_score,
    ml_projects, end_to_end_projects, deployed_projects, kaggle_competitions, best_competition_rank,
    hackathons_attended, hackathons_won, finalist_status, github_projects, internship_months, certifications,
    ml_interview_score, communication_score, dsa_score, resume_score, mock_interview_score
    FROM student_details WHERE user_id = ?");

// If prepare() fails (e.g. missing columns) show a helpful migration notice
if ($stmt === false) {
    // Capture error BEFORE closing (reading $con->error after close() causes fatal crash)
    $dbErr = htmlspecialchars($con->error ?: 'Unknown column — the database needs a migration.');
    $con->close();

    echo '<!DOCTYPE html><html lang="en"><head>
        <meta charset="UTF-8"><title>Database Migration Required</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
        <style>
            body{font-family:Inter,sans-serif;background:#0a0818;color:#f1f5f9;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:2rem}
            .box{background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.3);border-radius:1.5rem;max-width:680px;width:100%;padding:2.5rem;text-align:center}
            h1{font-size:1.5rem;margin-bottom:.75rem;color:#f87171}
            p{color:rgba(255,255,255,.65);line-height:1.7;margin-bottom:1rem;font-size:.95rem}
            .cmd{background:rgba(0,0,0,.5);border:1px solid rgba(255,255,255,.1);border-radius:.75rem;padding:1rem 1.5rem;
                 font-family:monospace;color:#fbbf24;font-size:.9rem;text-align:left;margin:1rem 0;line-height:2}
            .steps{text-align:left;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);
                   border-radius:.75rem;padding:1.25rem 1.5rem;margin-bottom:1.5rem;font-size:.88rem;line-height:2}
            a{display:inline-flex;align-items:center;gap:.4rem;background:linear-gradient(135deg,#6366f1,#8b5cf6);
              color:white;border-radius:.75rem;padding:.7rem 1.5rem;text-decoration:none;font-weight:700;margin-top:.5rem}
        </style></head><body>
        <div class="box">
            <h1>⚠️ Database Migration Required</h1>
            <p>The <strong>student_details</strong> table is missing the new AI score columns.<br>
               Run the migration SQL to add them — your existing data will <strong>not</strong> be deleted.</p>
            <div class="steps">
                <strong>Steps:</strong><br>
                1. Open <strong>phpMyAdmin</strong> → select <code>hackathon-employability-ml</code><br>
                2. Click the <strong>SQL</strong> tab<br>
                3. Paste and run the contents of <code>add_ml_columns.sql</code><br>
                4. Click <strong>Go</strong> — then reload this page
            </div>
            <div class="cmd">add_ml_columns.sql → Run in phpMyAdmin SQL tab</div>
            <p style="font-size:.8rem;color:rgba(255,255,255,.4)">Error: ' . $dbErr . '</p>
            <a href="dashboard.php">← Back to Dashboard</a>
        </div></body></html>';
    exit();
}

$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result(
    $degree, $college, $cgpa, $grad_year,
    $age, $python_score, $sql_score, $statistics_score, $ml_score, $dl_score, $genai_score,
    $ml_projects, $end_to_end_projects, $deployed_projects, $kaggle_competitions, $best_competition_rank,
    $hackathons_attended, $hackathons_won, $finalist_status, $github_projects, $internship_months, $certifications,
    $ml_interview_score, $communication_score, $dsa_score, $resume_score, $mock_interview_score
);
$hasProfile = $stmt->fetch();
$stmt->close();

if (!$hasProfile) {
    $con->close();
    header("Location: http://localhost/hackathon-employability-ml/student_details.php?error="
        . urlencode("Please complete your profile first to get a prediction."));
    exit();
}


// ── Build payload for Flask API ────────────────────────────────────────────────
$payload = [
    "age"                   => (int)   $age,
    "cgpa"                  => (float) $cgpa,
    "degree"                => (string)$degree,
    "python_score"          => (float) $python_score,
    "sql_score"             => (float) $sql_score,
    "statistics_score"      => (float) $statistics_score,
    "ml_score"              => (float) $ml_score,
    "dl_score"              => (float) $dl_score,
    "genai_score"           => (float) $genai_score,
    "ml_projects"           => (int)   $ml_projects,
    "end_to_end_projects"   => (int)   $end_to_end_projects,
    "deployed_projects"     => (int)   $deployed_projects,
    "kaggle_competitions"   => (int)   $kaggle_competitions,
    "best_competition_rank" => $best_competition_rank !== null ? (int)$best_competition_rank : null,
    "hackathons_attended"   => (int)   $hackathons_attended,
    "hackathons_won"        => (int)   $hackathons_won,
    "finalist_status"       => (int)   $finalist_status,
    "github_projects"       => (int)   $github_projects,
    "internship_months"     => (int)   $internship_months,
    "certifications"        => (int)   $certifications,
    "ml_interview_score"    => (float) $ml_interview_score,
    "communication_score"   => (float) $communication_score,
    "dsa_score"             => (float) $dsa_score,
    "resume_score"          => (float) $resume_score,
    "mock_interview_score"  => (float) $mock_interview_score,
];

// ── Call Flask Prediction API ──────────────────────────────────────────────────
$apiError   = null;
$prediction = null;

$ch = curl_init("http://127.0.0.1:5001/predict");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => ["Content-Type: application/json", "Accept: application/json"],
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_CONNECTTIMEOUT => 5,
]);
$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError || $httpCode !== 200) {
    $apiError = $curlError
        ? "Could not reach the Python AI server. Make sure you have started it with: <code>python predict_api.py</code>"
        : "API Error ({$httpCode}): " . (json_decode($response, true)['error'] ?? $response);
} else {
    $prediction = json_decode($response, true);
    if (!$prediction) $apiError = "Invalid response from AI server.";
}

// ── Cache result to DB (prediction_results) ────────────────────────────────────
if ($prediction) {
    $upsert = $con->prepare("INSERT INTO prediction_results
        (user_id, job_ready, job_ready_probability, career_track, career_track_label,
         career_track_probabilities, top_positive_factors, areas_to_improve)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            job_ready                  = VALUES(job_ready),
            job_ready_probability      = VALUES(job_ready_probability),
            career_track               = VALUES(career_track),
            career_track_label         = VALUES(career_track_label),
            career_track_probabilities = VALUES(career_track_probabilities),
            top_positive_factors       = VALUES(top_positive_factors),
            areas_to_improve           = VALUES(areas_to_improve),
            predicted_at               = CURRENT_TIMESTAMP");
    if ($upsert) {
        $jr  = (int)   $prediction['job_ready'];
        $jrp = (float) $prediction['job_ready_probability'];
        $ct  = (int)   $prediction['career_track'];
        $ctl =         $prediction['career_track_label'];
        $ctp = json_encode($prediction['career_track_probabilities']);
        $tpf = json_encode($prediction['top_positive_factors']);
        $ati = json_encode($prediction['areas_to_improve']);
        $upsert->bind_param("iidissss", $userId, $jr, $jrp, $ct, $ctl, $ctp, $tpf, $ati);
        $upsert->execute();
        $upsert->close();
    }
}
$con->close();

// ── Pull final display values ──────────────────────────────────────────────────
$jobReadyPct  = $prediction ? (float) $prediction['job_ready_probability'] : 0.0;
$jobReady     = $prediction ? (bool)  $prediction['job_ready']             : false;
$trackLabel   = $prediction ? $prediction['career_track_label']            : 'N/A';
$trackProbs   = $prediction ? $prediction['career_track_probabilities']    : [];
$topFactors   = $prediction ? $prediction['top_positive_factors']          : [];
$areasImprove = $prediction ? $prediction['areas_to_improve']              : [];

// Sort track probs descending
if ($trackProbs) arsort($trackProbs);

// Colour palette per track
$trackColors = [
    'Data Analyst'           => ['#60a5fa', '#3b82f6'],
    'Data Scientist'         => ['#a78bfa', '#8b5cf6'],
    'ML Engineer'            => ['#f472b6', '#ec4899'],
    'Deep Learning Engineer' => ['#fb923c', '#f97316'],
    'GenAI Engineer'         => ['#34d399', '#10b981'],
    'Not Yet Ready'          => ['#94a3b8', '#64748b'],
];

$fromSave = (($_GET['from'] ?? '') === 'save');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Career Prediction | Hackathon Career Readiness</title>
    <meta name="description" content="Your personalised AI-powered job readiness prediction and career track recommendation.">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:     #0a0818;
            --bg2:    #110f2a;
            --card:   rgba(255,255,255,0.04);
            --border: rgba(255,255,255,0.08);
            --indigo: #6366f1;
            --purple: #8b5cf6;
            --pink:   #ec4899;
            --green:  #4ade80;
            --amber:  #fbbf24;
            --text:   #f1f5f9;
            --muted:  rgba(255,255,255,0.5);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: radial-gradient(ellipse at top, #1e1254 0%, #0a0818 60%);
            min-height: 100vh;
            color: var(--text);
        }

        /* ── NAV ── */
        nav {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1rem 2.5rem;
            background: rgba(255,255,255,0.03);
            backdrop-filter: blur(24px);
            border-bottom: 1px solid var(--border);
            position: sticky; top: 0; z-index: 100;
        }
        .nav-brand { display: flex; align-items: center; gap: .75rem; }
        .nav-brand img { height: 36px; border-radius: 6px; }
        .nav-brand span { font-weight: 700; font-size: .95rem; }
        .nav-right { display: flex; align-items: center; gap: .75rem; }
        .nav-pill {
            display: flex; align-items: center; gap: .4rem;
            border-radius: 999px; padding: .4rem 1rem;
            font-size: .85rem; font-weight: 500;
            text-decoration: none; transition: background .2s;
        }
        .np-indigo { background:rgba(99,102,241,.12); border:1px solid rgba(99,102,241,.25); color:#a5b4fc; }
        .np-indigo:hover { background:rgba(99,102,241,.25); }
        .np-text   { background:rgba(255,255,255,.06); border:1px solid var(--border); color:var(--text); }
        .np-text:hover { background:rgba(255,255,255,.12); }
        .np-red    { background:rgba(248,113,113,.12); border:1px solid rgba(248,113,113,.25); color:#f87171; }
        .np-red:hover { background:rgba(248,113,113,.22); }

        /* ── PAGE ── */
        .page { max-width: 960px; margin: 0 auto; padding: 2.5rem 1.5rem 5rem; }

        /* ── HEADER ── */
        .page-header { text-align: center; margin-bottom: 2.5rem; }
        .badge {
            display: inline-flex; align-items: center; gap: .5rem;
            background:rgba(99,102,241,.15); border:1px solid rgba(99,102,241,.3);
            border-radius:999px; padding:.35rem 1rem; font-size:.8rem; color:#a5b4fc;
            margin-bottom:1rem;
        }
        .page-header h1 {
            font-size: clamp(1.8rem,4vw,2.8rem); font-weight:900; line-height:1.2; margin-bottom:.5rem;
        }
        .page-header h1 span {
            background:linear-gradient(135deg,var(--indigo),var(--purple),var(--pink));
            -webkit-background-clip:text; -webkit-text-fill-color:transparent;
        }
        .page-header p { color:var(--muted); font-size:.95rem; }

        /* ── SUCCESS BANNER ── */
        .success-banner {
            background:linear-gradient(135deg,rgba(74,222,128,.1),rgba(34,211,238,.1));
            border:1px solid rgba(74,222,128,.3); border-radius:1rem;
            padding:1rem 1.5rem; display:flex; align-items:center; gap:.75rem;
            margin-bottom:2rem; font-size:.9rem; color:#86efac;
        }

        /* ── ERROR CARD ── */
        .error-card {
            background:rgba(248,113,113,.08); border:1px solid rgba(248,113,113,.3);
            border-radius:1.5rem; padding:2.5rem; text-align:center;
        }
        .error-card .err-icon { font-size:3.5rem; color:#f87171; margin-bottom:1rem; }
        .error-card h2 { font-size:1.4rem; margin-bottom:.5rem; }
        .error-card p { color:var(--muted); font-size:.9rem; line-height:1.7; margin-bottom:1rem; }
        .error-card code {
            background:rgba(255,255,255,.1); border-radius:.4rem;
            padding:.2rem .55rem; font-size:.85rem; color:#fbbf24;
        }
        .cmd-block {
            background:rgba(0,0,0,.4); border:1px solid rgba(255,255,255,.08);
            border-radius:.85rem; padding:1.1rem 1.5rem;
            font-family:monospace; color:#fbbf24; font-size:.9rem;
            text-align:left; margin:1rem 0 1.5rem; line-height:1.8;
        }

        /* ── CARD ── */
        .card {
            background:var(--card); border:1px solid var(--border);
            border-radius:1.5rem; backdrop-filter:blur(20px);
            box-shadow:0 20px 60px rgba(0,0,0,.35); padding:2rem;
            margin-bottom:1.5rem;
        }
        .card-title {
            display:flex; align-items:center; gap:.6rem;
            font-size:1rem; font-weight:700; margin-bottom:1.5rem;
            padding-bottom:.75rem; border-bottom:1px solid var(--border);
        }

        /* ── TOP GRID: Ring + Track ── */
        .top-row {
            display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; margin-bottom:1.5rem;
        }
        @media(max-width:640px){.top-row{grid-template-columns:1fr;}}

        /* ── RADIAL RING ── */
        .ring-wrap {
            display:flex; flex-direction:column; align-items:center;
            justify-content:center; gap:1rem; min-height:270px;
        }
        .ring-container { position:relative; width:210px; height:210px; }
        .ring-svg { transform:rotate(-90deg); }
        .ring-bg { fill:none; stroke:rgba(255,255,255,.06); stroke-width:14; }
        .ring-fill {
            fill:none; stroke-width:14; stroke-linecap:round;
            stroke-dasharray:565; stroke-dashoffset:565;
            transition:stroke-dashoffset 2s cubic-bezier(.4,0,.2,1);
        }
        .ring-center {
            position:absolute; inset:0;
            display:flex; flex-direction:column; align-items:center; justify-content:center;
        }
        .ring-pct {
            font-size:3rem; font-weight:900; line-height:1;
            background:linear-gradient(135deg,var(--indigo),var(--purple));
            -webkit-background-clip:text; -webkit-text-fill-color:transparent;
        }
        .ring-sub { font-size:.73rem; color:var(--muted); margin-top:.2rem; }
        .ring-status {
            display:inline-flex; align-items:center; gap:.5rem;
            border-radius:999px; padding:.5rem 1.4rem;
            font-size:.92rem; font-weight:700;
        }
        .status-ready    { background:rgba(74,222,128,.15); border:1px solid rgba(74,222,128,.35); color:#4ade80; }
        .status-not-ready{ background:rgba(248,113,113,.15); border:1px solid rgba(248,113,113,.35); color:#f87171; }

        /* ── TRACK BARS ── */
        .track-item { margin-bottom:1.1rem; }
        .track-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:.35rem; }
        .track-name { font-size:.88rem; font-weight:600; }
        .track-pct  { font-size:.82rem; font-weight:700; min-width:3rem; text-align:right; }
        .track-bar-bg { background:rgba(255,255,255,.06); border-radius:999px; height:8px; overflow:hidden; }
        .track-bar-fill {
            height:100%; border-radius:999px; width:0%;
            transition:width 1.6s cubic-bezier(.4,0,.2,1);
        }
        .best-badge {
            display:inline-flex; align-items:center; gap:.3rem;
            background:rgba(99,102,241,.2); border:1px solid rgba(99,102,241,.4);
            color:#a5b4fc; border-radius:999px;
            padding:.18rem .65rem; font-size:.72rem; font-weight:700;
        }

        /* ── BOTTOM GRID: Strengths + Improve ── */
        .bottom-row { display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; }
        @media(max-width:640px){.bottom-row{grid-template-columns:1fr;}}

        /* ── FACTOR ITEMS ── */
        .factor-item {
            display:flex; align-items:center; gap:.85rem;
            padding:.85rem 1rem;
            background:rgba(255,255,255,.03); border:1px solid var(--border);
            border-radius:.9rem; margin-bottom:.65rem;
            transition:background .2s, transform .2s;
        }
        .factor-item:hover { background:rgba(255,255,255,.07); transform:translateX(4px); }
        .factor-icon {
            width:38px; height:38px; border-radius:.6rem;
            display:flex; align-items:center; justify-content:center;
            font-size:1rem; flex-shrink:0;
        }
        .factor-name { font-size:.88rem; font-weight:600; }
        .factor-val  { font-size:.78rem; color:var(--muted); margin-top:.1rem; }
        .factor-badge {
            font-size:.72rem; font-weight:700; margin-left:auto;
            padding:.2rem .6rem; border-radius:999px; flex-shrink:0;
        }

        /* ── IMPROVE ITEMS ── */
        .improve-item {
            display:flex; align-items:center; gap:.75rem;
            padding:.8rem 1rem;
            background:rgba(248,113,113,.05); border:1px solid rgba(248,113,113,.18);
            border-radius:.9rem; margin-bottom:.65rem; transition:background .2s;
        }
        .improve-item:hover { background:rgba(248,113,113,.1); }
        .improve-item i   { color:#f87171; font-size:.9rem; flex-shrink:0; }
        .improve-item span{ font-size:.88rem; font-weight:600; }

        /* ── ACTION BUTTONS ── */
        .actions-row {
            display:flex; gap:1rem; flex-wrap:wrap;
            justify-content:center; margin-top:2rem;
        }
        .btn {
            display:inline-flex; align-items:center; gap:.5rem;
            border-radius:.85rem; padding:.8rem 1.75rem;
            font-size:.9rem; font-weight:700; cursor:pointer;
            border:none; font-family:'Inter',sans-serif;
            text-decoration:none; transition:all .2s;
        }
        .btn-primary {
            background:linear-gradient(135deg,var(--indigo),var(--purple));
            color:white; box-shadow:0 0 24px rgba(99,102,241,.4);
        }
        .btn-primary:hover { transform:translateY(-2px); box-shadow:0 0 36px rgba(99,102,241,.6); }
        .btn-amber {
            background:linear-gradient(135deg,#f59e0b,#d97706);
            color:#0a0818; box-shadow:0 0 20px rgba(251,191,36,.3);
        }
        .btn-amber:hover { transform:translateY(-2px); box-shadow:0 0 32px rgba(251,191,36,.5); }
        .btn-outline {
            background:rgba(255,255,255,.06); border:1px solid var(--border); color:var(--text);
        }
        .btn-outline:hover { background:rgba(255,255,255,.12); }

        /* ── EMPTY STATE ── */
        .empty-note { text-align:center; color:var(--muted); padding:1.5rem; font-size:.88rem; }

        /* ── DECORATIVE GLOWS ── */
        .glow { position:fixed; border-radius:50%; filter:blur(90px); pointer-events:none; z-index:-1; }
        .g1 { width:420px; height:420px; background:rgba(99,102,241,.14);  top:-120px; right:-80px; }
        .g2 { width:320px; height:320px; background:rgba(236,72,153,.1);   bottom:80px; left:-80px; }
        .g3 { width:260px; height:260px; background:rgba(74,222,128,.08);  bottom:200px; right:50px; }

        /* ── FOOTER ── */
        footer {
            text-align:center; padding:2rem;
            border-top:1px solid var(--border);
            color:var(--muted); font-size:.85rem;
        }
        footer strong { color:var(--text); }
    </style>
</head>
<body>
<div class="glow g1"></div>
<div class="glow g2"></div>
<div class="glow g3"></div>

<!-- NAV -->
<nav>
    <div class="nav-brand">
        <img src="logo.png" alt="Logo">
        <span>Hackathon Career Readiness</span>
    </div>
    <div class="nav-right">
        <a href="dashboard.php"     class="nav-pill np-indigo"><i class="fa-solid fa-gauge"></i> Dashboard</a>
        <a href="student_details.php" class="nav-pill np-text"><i class="fa-solid fa-pen-to-square"></i> Edit Profile</a>
        <a href="logout.php"        class="nav-pill np-red"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
</nav>

<div class="page">

    <!-- HEADER -->
    <div class="page-header">
        <div class="badge"><i class="fa-solid fa-brain"></i> AI Career Intelligence</div>
        <h1>Your <span>AI Prediction</span> Results</h1>
        <p>Powered by our Random Forest &amp; Logistic Regression models trained on 1,000+ student records</p>
    </div>

    <?php if ($fromSave): ?>
    <div class="success-banner">
        <i class="fa-solid fa-circle-check" style="font-size:1.4rem;flex-shrink:0"></i>
        <div>
            <strong>Profile saved successfully!</strong>
            Here are your live AI predictions based on your updated data.
        </div>
    </div>
    <?php endif; ?>

    <?php if ($apiError): ?>
    <!-- ═══ API ERROR STATE ═══ -->
    <div class="error-card">
        <div class="err-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <h2>AI Server Unreachable</h2>
        <p><?= $apiError ?></p>
        <p>Open a new terminal in the project root and run:</p>
        <div class="cmd-block">
            cd C:\xampp\htdocs\hackathon-employability-ml<br>
            python predict_api.py
        </div>
        <p style="margin-bottom:1.5rem;font-size:.85rem">The server will start on <code>http://127.0.0.1:5001</code> — keep that window open.</p>
        <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap">
            <a href="predict.php" class="btn btn-primary"><i class="fa-solid fa-rotate"></i> Retry Prediction</a>
            <a href="dashboard.php" class="btn btn-outline"><i class="fa-solid fa-gauge"></i> Back to Dashboard</a>
        </div>
    </div>

    <?php else: ?>

    <!-- ═══ RESULTS ═══ -->

    <!-- TOP ROW -->
    <div class="top-row">

        <!-- ── Job Readiness Ring ── -->
        <div class="card">
            <div class="card-title" style="color:#818cf8">
                <i class="fa-solid fa-bullseye"></i> Job Readiness Score
            </div>
            <div class="ring-wrap">
                <div class="ring-container">
                    <svg class="ring-svg" viewBox="0 0 200 200" width="210" height="210">
                        <defs>
                            <linearGradient id="rg" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%"   stop-color="#6366f1"/>
                                <stop offset="100%" stop-color="#ec4899"/>
                            </linearGradient>
                        </defs>
                        <circle class="ring-bg"   cx="100" cy="100" r="90"/>
                        <circle class="ring-fill" id="ringFill" cx="100" cy="100" r="90" stroke="url(#rg)"/>
                    </svg>
                    <div class="ring-center">
                        <div class="ring-pct" id="ringPct">0%</div>
                        <div class="ring-sub">Readiness</div>
                    </div>
                </div>
                <div class="ring-status <?= $jobReady ? 'status-ready' : 'status-not-ready' ?>">
                    <i class="fa-solid <?= $jobReady ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                    <?= $jobReady ? 'Job Ready! 🎉' : 'Not Quite Yet' ?>
                </div>
                <p style="text-align:center;color:var(--muted);font-size:.82rem;max-width:230px;line-height:1.5">
                    <?php if ($jobReady): ?>
                        Great job! You appear ready for an entry-level Data / AI role. 🚀
                    <?php else: ?>
                        Keep building your skills — every point matters!
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <!-- ── Career Track Probabilities ── -->
        <div class="card">
            <div class="card-title" style="color:#a78bfa">
                <i class="fa-solid fa-map-signs"></i> Career Track Recommendation
            </div>
            <?php if (!empty($trackProbs)):
                $isTop = true;
                foreach ($trackProbs as $track => $prob):
                    $pct = round($prob, 1);
                    $col = $trackColors[$track] ?? ['#94a3b8', '#64748b'];
            ?>
            <div class="track-item">
                <div class="track-header">
                    <div style="display:flex;align-items:center;gap:.45rem">
                        <span class="track-name"><?= htmlspecialchars($track) ?></span>
                        <?php if ($isTop): ?>
                        <span class="best-badge"><i class="fa-solid fa-star" style="font-size:.6rem"></i> Best Fit</span>
                        <?php endif; ?>
                    </div>
                    <span class="track-pct" style="color:<?= $col[0] ?>"><?= $pct ?>%</span>
                </div>
                <div class="track-bar-bg">
                    <div class="track-bar-fill" data-pct="<?= $pct ?>"
                         style="background:linear-gradient(90deg,<?= $col[1] ?>,<?= $col[0] ?>)"></div>
                </div>
            </div>
            <?php $isTop = false; endforeach;
            else: ?>
            <div class="empty-note">No track data available.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- BOTTOM ROW -->
    <div class="bottom-row">

        <!-- ── Top Strengths ── -->
        <div class="card">
            <div class="card-title" style="color:#4ade80">
                <i class="fa-solid fa-trophy"></i> Your Top Strengths
            </div>
            <?php if (!empty($topFactors)):
                foreach ($topFactors as $factor):
                    // Support both array and object formats
                    $feat = is_array($factor) ? ($factor['feature'] ?? $factor[0] ?? '') : '';
                    $val  = is_array($factor) ? ($factor['value']   ?? $factor[1] ?? '') : '';
                    // Icon mapping
                    $icon = 'fa-solid fa-star';
                    if (stripos($feat,'python') !== false)            $icon = 'fa-brands fa-python';
                    elseif (stripos($feat,'sql') !== false)           $icon = 'fa-solid fa-database';
                    elseif (stripos($feat,'hackathon') !== false)     $icon = 'fa-solid fa-trophy';
                    elseif (stripos($feat,'project') !== false
                         || stripos($feat,'github') !== false)        $icon = 'fa-brands fa-github';
                    elseif (stripos($feat,'intern') !== false)        $icon = 'fa-solid fa-briefcase';
                    elseif (stripos($feat,'cert') !== false)          $icon = 'fa-solid fa-certificate';
                    elseif (stripos($feat,'ml') !== false
                         || stripos($feat,'machine') !== false)       $icon = 'fa-solid fa-robot';
                    elseif (stripos($feat,'communication') !== false) $icon = 'fa-solid fa-comments';
                    elseif (stripos($feat,'interview') !== false
                         || stripos($feat,'mock') !== false)          $icon = 'fa-solid fa-user-tie';
                    elseif (stripos($feat,'cgpa') !== false
                         || stripos($feat,'grade') !== false)         $icon = 'fa-solid fa-graduation-cap';
                    elseif (stripos($feat,'kaggle') !== false)        $icon = 'fa-solid fa-medal';
                    elseif (stripos($feat,'genai') !== false
                         || stripos($feat,'llm') !== false)           $icon = 'fa-solid fa-wand-magic-sparkles';
                    $valDisplay = is_numeric($val)
                        ? (is_float($val + 0) ? number_format((float)$val, 2) : (int)$val)
                        : $val;
            ?>
            <div class="factor-item">
                <div class="factor-icon" style="background:rgba(74,222,128,.15);color:#4ade80">
                    <i class="<?= $icon ?>"></i>
                </div>
                <div style="flex:1;min-width:0">
                    <div class="factor-name"><?= htmlspecialchars(ucwords(str_replace('_',' ',$feat))) ?></div>
                    <div class="factor-val">Score / Count: <?= htmlspecialchars((string)$valDisplay) ?></div>
                </div>
                <span class="factor-badge"
                      style="background:rgba(74,222,128,.15);border:1px solid rgba(74,222,128,.3);color:#4ade80">
                    ✓ Strong
                </span>
            </div>
            <?php endforeach;
            else: ?>
            <div class="empty-note"><i class="fa-solid fa-info-circle"></i> No specific strengths identified.</div>
            <?php endif; ?>
        </div>

        <!-- ── Areas to Improve ── -->
        <div class="card">
            <div class="card-title" style="color:#fb923c">
                <i class="fa-solid fa-arrow-trend-up"></i> Areas to Improve
            </div>
            <?php if (!empty($areasImprove)):
                foreach ($areasImprove as $area): ?>
            <div class="improve-item">
                <i class="fa-solid fa-chevron-up"></i>
                <span><?= htmlspecialchars(ucwords(str_replace('_',' ',$area))) ?></span>
            </div>
            <?php endforeach; ?>
            <p style="color:var(--muted);font-size:.8rem;margin-top:.75rem;text-align:center">
                Focus on these to significantly boost your readiness score.
            </p>
            <?php else: ?>
            <div class="empty-note">
                <i class="fa-solid fa-check-circle" style="color:#4ade80;font-size:1.8rem;display:block;margin-bottom:.5rem"></i>
                Excellent! No obvious weak areas detected.
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ACTION BUTTONS -->
    <div class="actions-row">
        <a href="predict.php" class="btn btn-primary">
            <i class="fa-solid fa-rotate"></i> Re-run Prediction
        </a>
        <a href="student_details.php" class="btn btn-amber">
            <i class="fa-solid fa-pen-to-square"></i> Update My Scores
        </a>
        <a href="dashboard.php" class="btn btn-outline">
            <i class="fa-solid fa-gauge"></i> Back to Dashboard
        </a>
    </div>

    <?php endif; ?>

</div><!-- /page -->

<footer>
    <i class="fa-solid fa-brain"></i>&nbsp;
    <strong>Hackathon Success &amp; AI Career Readiness Prediction System</strong>
    &nbsp;|&nbsp; Built by Shilpi, Priyanshu, Dushyant &amp; Vedika · B.Tech CSE Group Project
</footer>

<script>
// ── Animate radial ring ──────────────────────────────────────────────────────
const TARGET   = <?= round($jobReadyPct, 1) ?>;
const CIRC     = 565;
const ringEl   = document.getElementById('ringFill');
const pctEl    = document.getElementById('ringPct');

if (ringEl && pctEl) {
    ringEl.style.strokeDashoffset = CIRC;
    let startTs = null;
    function animateRing(ts) {
        if (!startTs) startTs = ts;
        const t = Math.min((ts - startTs) / 2000, 1);
        const ease = 1 - Math.pow(1 - t, 4);          // ease-out quart
        const cur  = TARGET * ease;
        pctEl.textContent = cur.toFixed(1) + '%';
        ringEl.style.strokeDashoffset = CIRC - (CIRC * cur / 100);
        if (t < 1) requestAnimationFrame(animateRing);
    }
    requestAnimationFrame(animateRing);
}

// ── Animate track bars ───────────────────────────────────────────────────────
window.addEventListener('load', () => {
    document.querySelectorAll('.track-bar-fill').forEach(bar => {
        const pct = parseFloat(bar.dataset.pct) || 0;
        setTimeout(() => { bar.style.width = pct + '%'; }, 350);
    });
});
</script>
</body>
</html>
