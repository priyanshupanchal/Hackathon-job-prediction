<?php
session_start();

// ── Session guard: redirect to login if not authenticated ─────────────────────
if (!isset($_SESSION['user_id'])) {
    header("Location: http://localhost/hackathon-employability-ml/index.html");
    exit();
}

$userName  = htmlspecialchars($_SESSION['user_name']  ?? 'User');
$userEmail = htmlspecialchars($_SESSION['user_email'] ?? '');
$userId    = (int) $_SESSION['user_id'];

// ── Load student profile (if exists) ─────────────────────────
$profile = null;
$db = mysqli_connect("localhost", "root", "", "hackathon-employability-ml");
if ($db) {
    $ps = $db->prepare("SELECT degree, college, cgpa, graduation_year, skills, experience, certificates, cv_filename FROM student_details WHERE user_id = ?");
    $ps->bind_param("i", $userId);
    $ps->execute();
    $ps->bind_result($p_degree, $p_college, $p_cgpa, $p_grad, $p_skills, $p_exp, $p_certs, $p_cv);
    if ($ps->fetch()) {
        $profile = [
            'degree'   => $p_degree,
            'college'  => $p_college,
            'cgpa'     => $p_cgpa,
            'grad'     => $p_grad,
            'skills'   => json_decode($p_skills  ?: '[]', true),
            'exp'      => json_decode($p_exp     ?: '[]', true),
            'certs'    => json_decode($p_certs   ?: '[]', true),
            'cv'       => $p_cv,
        ];
    }
    $ps->close();
    $db->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Hackathon Career Readiness</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:        #0f0c29;
            --bg2:       #1a1740;
            --card:      rgba(255,255,255,0.05);
            --border:    rgba(255,255,255,0.1);
            --indigo:    #6366f1;
            --purple:    #8b5cf6;
            --pink:      #ec4899;
            --green:     #4ade80;
            --text:      #f1f5f9;
            --muted:     rgba(255,255,255,0.5);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--bg), #302b63, #24243e);
            min-height: 100vh;
            color: var(--text);
        }

        /* ── TOP NAV ── */
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

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .nav-brand img { height: 38px; border-radius: 6px; }
        .nav-brand span { font-weight: 700; font-size: 1rem; }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .nav-profile-btn {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(99,102,241,0.12);
            border: 1px solid rgba(99,102,241,0.25);
            color: #a5b4fc;
            border-radius: 999px;
            padding: 0.4rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: background 0.2s;
        }
        .nav-profile-btn:hover { background: rgba(99,102,241,0.25); }

        /* ── PROFILE SUMMARY CARD ── */
        .profile-card {
            max-width: 900px;
            margin: 0 auto 2.5rem;
            padding: 0 1.5rem;
        }
        .profile-card-inner {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 1.25rem;
            padding: 1.75rem 2rem;
            backdrop-filter: blur(14px);
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 1.5rem;
            align-items: center;
        }
        @media (max-width: 680px) {
            .profile-card-inner { grid-template-columns: 1fr; text-align: center; }
        }
        .profile-avatar {
            width: 70px; height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem; color: white;
            box-shadow: 0 0 20px rgba(99,102,241,0.4);
            flex-shrink: 0;
        }
        .profile-meta h3 { font-size: 1.05rem; font-weight: 700; margin-bottom: 0.25rem; }
        .profile-meta p  { color: rgba(255,255,255,0.55); font-size: 0.83rem; }
        .profile-stats {
            display: flex; gap: 1rem; flex-wrap: wrap;
            margin-top: 0.75rem;
        }
        .p-stat {
            display: flex; align-items: center; gap: 0.4rem;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: .6rem;
            padding: 0.3rem 0.7rem;
            font-size: 0.78rem;
        }
        .p-stat i { color: #818cf8; font-size: 0.75rem; }
        .profile-actions { display: flex; flex-direction: column; gap: 0.6rem; }
        .profile-edit-btn {
            display: inline-flex; align-items: center; gap: 0.4rem;
            background: rgba(99,102,241,0.15);
            border: 1px solid rgba(99,102,241,0.35);
            color: #a5b4fc; border-radius: 0.75rem;
            padding: 0.55rem 1.1rem; font-size: 0.82rem;
            font-weight: 600; text-decoration: none;
            transition: background 0.2s;
            white-space: nowrap;
        }
        .profile-edit-btn:hover { background: rgba(99,102,241,0.28); }
        .profile-cv-btn {
            display: inline-flex; align-items: center; gap: 0.4rem;
            background: rgba(74,222,128,0.1);
            border: 1px solid rgba(74,222,128,0.3);
            color: #86efac; border-radius: 0.75rem;
            padding: 0.55rem 1.1rem; font-size: 0.82rem;
            font-weight: 600; text-decoration: none;
            transition: background 0.2s;
            white-space: nowrap;
        }
        .profile-cv-btn:hover { background: rgba(74,222,128,0.2); }
        .profile-no-card {
            background: rgba(99,102,241,0.08);
            border: 1px dashed rgba(99,102,241,0.4);
            border-radius: 1.25rem;
            padding: 1.5rem 2rem;
            display: flex; align-items: center; gap: 1.25rem;
            backdrop-filter: blur(12px);
        }
        .profile-no-card p { color: rgba(255,255,255,0.6); font-size: 0.88rem; }
        .profile-no-card a {
            margin-left: auto; white-space: nowrap;
            display: inline-flex; align-items: center; gap: 0.4rem;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white; border-radius: 0.75rem;
            padding: 0.6rem 1.2rem; font-size: 0.85rem;
            font-weight: 700; text-decoration: none;
            box-shadow: 0 0 16px rgba(99,102,241,0.35);
            transition: transform 0.2s;
        }
        .profile-no-card a:hover { transform: translateY(-2px); }

        .user-pill {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(99,102,241,0.15);
            border: 1px solid rgba(99,102,241,0.3);
            border-radius: 999px;
            padding: 0.4rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .user-pill i { color: var(--indigo); }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(248,113,113,0.12);
            border: 1px solid rgba(248,113,113,0.25);
            color: #f87171;
            border-radius: 999px;
            padding: 0.4rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.2s;
        }
        .logout-btn:hover { background: rgba(248,113,113,0.22); }

        /* ── HERO ── */
        .hero {
            text-align: center;
            padding: 4rem 1.5rem 2.5rem;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(99,102,241,0.15);
            border: 1px solid rgba(99,102,241,0.3);
            border-radius: 999px;
            padding: 0.35rem 1rem;
            font-size: 0.8rem;
            color: #a5b4fc;
            margin-bottom: 1.5rem;
        }
        .hero h1 {
            font-size: clamp(1.8rem, 4vw, 3rem);
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 0.75rem;
        }
        .hero h1 span {
            background: linear-gradient(135deg, var(--indigo), var(--purple), var(--pink));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero p {
            color: var(--muted);
            max-width: 560px;
            margin: 0 auto 2.5rem;
            font-size: 1rem;
            line-height: 1.6;
        }

        /* ── LAUNCH BUTTON ── */
        .launch-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            background: linear-gradient(135deg, var(--indigo), var(--purple));
            color: white;
            font-size: 1.1rem;
            font-weight: 700;
            padding: 1rem 2.5rem;
            border-radius: 999px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            box-shadow: 0 0 30px rgba(99,102,241,0.45);
            transition: transform 0.2s, box-shadow 0.2s;
            animation: pulse-glow 2.5s ease-in-out infinite;
        }
        .launch-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 0 50px rgba(99,102,241,0.65);
        }
        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 0 30px rgba(99,102,241,0.45); }
            50%       { box-shadow: 0 0 55px rgba(139,92,246,0.65); }
        }

        /* ── STATS ROW ── */
        .stats-row {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            flex-wrap: wrap;
            padding: 0 1.5rem 3rem;
        }
        .stat-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 1.5rem 2rem;
            text-align: center;
            min-width: 160px;
            backdrop-filter: blur(12px);
            transition: transform 0.2s, border-color 0.2s;
        }
        .stat-card:hover { transform: translateY(-4px); border-color: var(--indigo); }
        .stat-card .stat-icon {
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }
        .stat-card .stat-value {
            font-size: 1.6rem;
            font-weight: 800;
        }
        .stat-card .stat-label {
            color: var(--muted);
            font-size: 0.8rem;
            margin-top: 0.25rem;
        }

        /* ── FEATURES GRID ── */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(270px, 1fr));
            gap: 1.25rem;
            padding: 0 2.5rem 3rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        .feature-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 1.25rem;
            padding: 1.75rem;
            backdrop-filter: blur(12px);
            transition: transform 0.2s, border-color 0.2s;
        }
        .feature-card:hover { transform: translateY(-4px); border-color: rgba(99,102,241,0.4); }
        .feature-card .fc-icon {
            width: 52px;
            height: 52px;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 1rem;
        }
        .fc-icon.indigo { background: rgba(99,102,241,0.2); color: #818cf8; }
        .fc-icon.purple { background: rgba(139,92,246,0.2); color: #a78bfa; }
        .fc-icon.pink   { background: rgba(236,72,153,0.2); color: #f472b6; }
        .fc-icon.green  { background: rgba(74,222,128,0.2); color: #4ade80; }

        .feature-card h3 { font-size: 1rem; font-weight: 700; margin-bottom: 0.5rem; }
        .feature-card p  { color: var(--muted); font-size: 0.875rem; line-height: 1.6; }

        /* ── HOW IT WORKS ── */
        .section-title {
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 2rem;
            padding: 0 1.5rem;
        }
        .steps-row {
            display: flex;
            justify-content: center;
            gap: 0;
            flex-wrap: wrap;
            padding: 0 2rem 4rem;
            max-width: 900px;
            margin: 0 auto;
        }
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            flex: 1;
            min-width: 160px;
            position: relative;
        }
        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 28px;
            right: -10%;
            width: 20%;
            height: 2px;
            background: linear-gradient(90deg, var(--indigo), var(--purple));
        }
        .step-num {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--indigo), var(--purple));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.25rem;
            margin-bottom: 0.75rem;
            box-shadow: 0 0 20px rgba(99,102,241,0.4);
        }
        .step h4 { font-size: 0.9rem; font-weight: 600; margin-bottom: 0.3rem; }
        .step p  { font-size: 0.78rem; color: var(--muted); }

        /* ── FOOTER ── */
        footer {
            text-align: center;
            padding: 2rem;
            border-top: 1px solid var(--border);
            color: var(--muted);
            font-size: 0.85rem;
        }
        footer strong { color: var(--text); }
    </style>
</head>
<body>

    <!-- NAV -->
    <nav>
        <div class="nav-brand">
            <img src="logo.png" alt="Logo">
            <span>Hackathon Career Readiness</span>
        </div>
        <div class="nav-right">
            <a href="student_details.php" class="nav-profile-btn">
                <i class="fa-solid fa-id-card"></i> My Profile
            </a>
            <div class="user-pill">
                <i class="fa-solid fa-circle-user"></i>
                Welcome, <strong><?= $userName ?></strong>
            </div>
            <a href="logout.php" class="logout-btn">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>
        </div>
    </nav>

    <!-- HERO -->
    <section class="hero">
        <div class="hero-badge">
            <i class="fa-solid fa-sparkles"></i>
            AI-Powered Career Intelligence
        </div>
        <h1>
            Hello, <?= $userName ?>! 👋<br>
            Ready to <span>Predict Your Future?</span>
        </h1>
        <p>
            Use our machine learning model to assess your job readiness,
            discover the best career track in Data & AI, and see exactly
            how your hackathon experience stacks up.
        </p>

        <!-- Success notice after profile save -->
        <?php if (isset($_GET['profile']) && $_GET['profile'] === 'saved'): ?>
        <div style="display:inline-flex;align-items:center;gap:.6rem;background:rgba(74,222,128,.1);border:1px solid rgba(74,222,128,.3);border-radius:1rem;padding:.75rem 1.25rem;margin-bottom:1.5rem;font-size:.9rem;color:#86efac">
            <i class="fa-solid fa-circle-check"></i>
            Profile saved! <a href="predict.php" style="color:#4ade80;font-weight:700;text-decoration:none">View your AI Prediction →</a>
        </div>
        <?php endif; ?>

        <a href="predict.php" class="launch-btn" id="launchPredictorBtn">
            <i class="fa-solid fa-brain"></i>
            Launch AI Predictor
        </a>
    </section>

    <!-- PROFILE SUMMARY CARD -->
    <div class="profile-card">
        <?php if ($profile): ?>
        <div class="profile-card-inner">
            <div class="profile-avatar"><i class="fa-solid fa-user-graduate"></i></div>
            <div class="profile-meta">
                <h3><?= htmlspecialchars($profile['degree']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($profile['college']) ?></h3>
                <p>Graduation: <?= htmlspecialchars($profile['grad']) ?>&emsp;|&emsp;CGPA: <?= number_format((float)$profile['cgpa'],2) ?></p>
                <div class="profile-stats">
                    <?php
                    $skillList = array_slice($profile['skills'], 0, 5);
                    foreach ($skillList as $sk):
                    ?>
                    <span class="p-stat"><i class="fa-solid fa-check"></i><?= htmlspecialchars($sk) ?></span>
                    <?php endforeach;
                    $extra = count($profile['skills']) - 5;
                    if ($extra > 0): ?>
                    <span class="p-stat"><i class="fa-solid fa-ellipsis"></i>+<?= $extra ?> more</span>
                    <?php endif; ?>
                    <?php if (count($profile['exp'])): ?>
                    <span class="p-stat" style="border-color:rgba(236,72,153,.3)"><i class="fa-solid fa-briefcase" style="color:#f472b6"></i><?= count($profile['exp']) ?> Experience<?= count($profile['exp']) > 1 ? 's' : '' ?></span>
                    <?php endif; ?>
                    <?php if (count($profile['certs'])): ?>
                    <span class="p-stat" style="border-color:rgba(74,222,128,.3)"><i class="fa-solid fa-certificate" style="color:#4ade80"></i><?= count($profile['certs']) ?> Cert<?= count($profile['certs']) > 1 ? 's' : '' ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="profile-actions">
                <a href="student_details.php" class="profile-edit-btn"><i class="fa-solid fa-pen-to-square"></i> Edit Profile</a>
                <?php if ($profile['cv']): ?>
                <a href="download_cv.php" target="_blank" class="profile-cv-btn"><i class="fa-solid fa-file-pdf"></i> View CV</a>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="profile-no-card">
            <i class="fa-solid fa-circle-info" style="font-size:1.5rem;color:#818cf8;flex-shrink:0"></i>
            <p>Complete your <strong style="color:white">Student Profile</strong> to unlock the AI predictor with your personalised data — skills, experience, CGPA & CV.</p>
            <a href="student_details.php"><i class="fa-solid fa-arrow-right"></i> Complete Profile</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- STATS -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon">🎯</div>
            <div class="stat-value" style="color:#818cf8;">~74%</div>
            <div class="stat-label">Job Readiness Accuracy</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📊</div>
            <div class="stat-value" style="color:#a78bfa;">0.82</div>
            <div class="stat-label">ROC-AUC Score</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🗺️</div>
            <div class="stat-value" style="color:#f472b6;">6</div>
            <div class="stat-label">Career Tracks</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🔍</div>
            <div class="stat-value" style="color:#4ade80;">25+</div>
            <div class="stat-label">Features Analysed</div>
        </div>
    </div>

    <!-- FEATURES -->
    <h2 class="section-title">What the AI Predictor Does</h2>
    <div class="features-grid">
        <div class="feature-card">
            <div class="fc-icon indigo"><i class="fa-solid fa-bullseye"></i></div>
            <h3>Job Readiness Prediction</h3>
            <p>Get an AI-estimated probability of being ready for an entry-level Data / AI role based on your full profile.</p>
        </div>
        <div class="feature-card">
            <div class="fc-icon purple"><i class="fa-solid fa-map-signs"></i></div>
            <h3>Career Track Recommendation</h3>
            <p>The model recommends the best fit among Data Analyst, Data Scientist, ML Engineer, Deep Learning Engineer, GenAI Engineer, or Not Yet Ready.</p>
        </div>
        <div class="feature-card">
            <div class="fc-icon pink"><i class="fa-solid fa-trophy"></i></div>
            <h3>Hackathon Impact Analysis</h3>
            <p>See exactly how much your hackathon wins and attendance contribute to your readiness score versus internships, projects, and skills.</p>
        </div>
        <div class="feature-card">
            <div class="fc-icon green"><i class="fa-solid fa-brain"></i></div>
            <h3>Explainable AI Insights</h3>
            <p>SHAP-based explanations tell you which specific skills or gaps are driving the model's decision — not just a black-box score.</p>
        </div>
    </div>

    <!-- HOW IT WORKS -->
    <h2 class="section-title">How It Works</h2>
    <div class="steps-row">
        <div class="step">
            <div class="step-num">1</div>
            <h4>Enter Your Profile</h4>
            <p>Skills, CGPA, projects, hackathons, internships</p>
        </div>
        <div class="step">
            <div class="step-num">2</div>
            <h4>ML Model Predicts</h4>
            <p>Random Forest + Logistic Regression trained on 1,000+ records</p>
        </div>
        <div class="step">
            <div class="step-num">3</div>
            <h4>Get Your Score</h4>
            <p>Job-readiness % and recommended career track</p>
        </div>
        <div class="step">
            <div class="step-num">4</div>
            <h4>See Explanations</h4>
            <p>Understand which factors drive the result</p>
        </div>
    </div>

    <footer>
        <i class="fa-solid fa-trophy"></i>&nbsp;
        <strong>Hackathon Success & AI Career Readiness Prediction System</strong>
        &nbsp;|&nbsp; Built by Shilpi, ,Priyanshu,Dushyant & Vedika · B.Tech CSE Group Project
    </footer>

</body>
</html>
