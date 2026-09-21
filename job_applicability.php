<?php
session_start();

// ── Session guard ──────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header("Location: http://localhost/hackathon-employability-ml/index.html");
    exit();
}

$userId   = (int) $_SESSION['user_id'];
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Student');

// ── DB connection ──────────────────────────────────────────────────────────────
$con = mysqli_connect("localhost", "root", "", "hackathon-employability-ml");
if (!$con) die("DB connection failed: " . mysqli_connect_error());
mysqli_report(MYSQLI_REPORT_OFF);

// ── Fetch Student Profile ──────────────────────────────────────────────────────
$stmt = $con->prepare("SELECT degree, college, cgpa, graduation_year,
    age, python_score, sql_score, statistics_score, ml_score, dl_score, genai_score,
    ml_projects, end_to_end_projects, deployed_projects, kaggle_competitions, best_competition_rank,
    hackathons_attended, hackathons_won, finalist_status, github_projects, internship_months, certifications,
    ml_interview_score, communication_score, dsa_score, resume_score, mock_interview_score
    FROM student_details WHERE user_id = ?");

if ($stmt === false) {
    $con->close();
    header("Location: student_details.php?error=" . urlencode("Please complete your profile first."));
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
    header("Location: student_details.php?error=" . urlencode("Please complete your profile first to check department applicability."));
    exit();
}

// ── Fetch cached prediction result ────────────────────────────────────────────
$jobReady         = null;
$jobReadyPct      = 0.0;
$careerTrackLabel = 'N/A';

$pr = $con->prepare("SELECT job_ready, job_ready_probability, career_track_label FROM prediction_results WHERE user_id = ?");
if ($pr) {
    $pr->bind_param("i", $userId);
    $pr->execute();
    $pr->bind_result($jr, $jrp, $ctl);
    if ($pr->fetch()) {
        $jobReady         = (bool)$jr;
        $jobReadyPct      = (float)$jrp;
        $careerTrackLabel = $ctl;
    }
    $pr->close();
}
$con->close();

// ── Department Eligibility Engine ─────────────────────────────────────────────
// Returns: [score_pct, status, checklist, gaps, roles]
// status: 'applicable' | 'conditional' | 'not_applicable'

function check($val, $min, $label, &$checklist, &$gaps, $weight = 1) {
    $pass = $val >= $min;
    $checklist[] = [
        'label'  => $label,
        'pass'   => $pass,
        'val'    => $val,
        'min'    => $min,
    ];
    if (!$pass) $gaps[] = "$label (need $min, have $val)";
    return $pass ? $weight : 0;
}

$departments = [];

// ─────────────────────────────────────────────────────────────────────────────
// 1. AI / ML Engineering Department
// ─────────────────────────────────────────────────────────────────────────────
{
    $cl = []; $gaps = []; $w = 0; $total = 0;
    $total += 2; $w += check($cgpa,            7.5, 'CGPA ≥ 7.5',              $cl, $gaps, 2);
    $total += 2; $w += check($python_score,    7.0, 'Python Score ≥ 7/10',     $cl, $gaps, 2);
    $total += 2; $w += check($ml_score,        7.0, 'ML Score ≥ 7/10',         $cl, $gaps, 2);
    $total += 1; $w += check($statistics_score,6.0, 'Stats Score ≥ 6/10',      $cl, $gaps, 1);
    $total += 1; $w += check($ml_projects,     2,   'ML Projects ≥ 2',         $cl, $gaps, 1);
    $total += 1; $w += check($deployed_projects,1,  'Deployed Projects ≥ 1',   $cl, $gaps, 1);
    $total += 1; $w += check($github_projects, 3,   'GitHub Repos ≥ 3',        $cl, $gaps, 1);
    $total += 1; $w += check($dsa_score,       6.0, 'DSA Score ≥ 6/10',        $cl, $gaps, 1);
    $total += 1; $w += check($ml_interview_score,6.0,'ML Interview ≥ 6/10',    $cl, $gaps, 1);
    $total += 1; $w += check($hackathons_attended,1,'Hackathons Attended ≥ 1', $cl, $gaps, 1);
    $pct = round($w / $total * 100);
    $departments[] = [
        'id'    => 'ai_ml',
        'icon'  => 'fa-solid fa-robot',
        'color' => ['#818cf8','#6366f1'],
        'title' => 'AI / ML Engineering',
        'desc'  => 'Build, train and deploy production ML models for core company products.',
        'pct'   => $pct,
        'status'=> $pct >= 75 ? 'applicable' : ($pct >= 50 ? 'conditional' : 'not_applicable'),
        'checklist' => $cl,
        'gaps'  => $gaps,
        'roles' => ['Junior ML Engineer', 'ML Researcher (Entry)', 'AI Developer', 'Applied Scientist Intern'],
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. Data Science & Advanced Analytics Department
// ─────────────────────────────────────────────────────────────────────────────
{
    $cl = []; $gaps = []; $w = 0; $total = 0;
    $total += 2; $w += check($cgpa,            7.0, 'CGPA ≥ 7.0',               $cl, $gaps, 2);
    $total += 2; $w += check($python_score,    6.5, 'Python Score ≥ 6.5/10',    $cl, $gaps, 2);
    $total += 2; $w += check($statistics_score,7.0, 'Statistics Score ≥ 7/10',  $cl, $gaps, 2);
    $total += 2; $w += check($ml_score,        6.0, 'ML Score ≥ 6/10',          $cl, $gaps, 2);
    $total += 1; $w += check($sql_score,       6.0, 'SQL Score ≥ 6/10',         $cl, $gaps, 1);
    $total += 1; $w += check($ml_projects,     2,   'ML/Data Projects ≥ 2',     $cl, $gaps, 1);
    $total += 1; $w += check($kaggle_competitions,1,'Kaggle Competitions ≥ 1',  $cl, $gaps, 1);
    $total += 1; $w += check($communication_score,6,'Communication ≥ 6/10',     $cl, $gaps, 1);
    $total += 1; $w += check($resume_score,    6.0, 'Resume Score ≥ 6/10',      $cl, $gaps, 1);
    $total += 1; $w += check($certifications,  1,   'Certifications ≥ 1',       $cl, $gaps, 1);
    $pct = round($w / $total * 100);
    $departments[] = [
        'id'    => 'data_science',
        'icon'  => 'fa-solid fa-chart-line',
        'color' => ['#a78bfa','#8b5cf6'],
        'title' => 'Data Science & Advanced Analytics',
        'desc'  => 'Derive insights from complex datasets and build statistical/predictive models.',
        'pct'   => $pct,
        'status'=> $pct >= 75 ? 'applicable' : ($pct >= 50 ? 'conditional' : 'not_applicable'),
        'checklist' => $cl,
        'gaps'  => $gaps,
        'roles' => ['Data Scientist (Junior)', 'Analytics Engineer', 'Research Analyst', 'Quant Analyst Trainee'],
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. Business Intelligence & Data Analytics
// ─────────────────────────────────────────────────────────────────────────────
{
    $cl = []; $gaps = []; $w = 0; $total = 0;
    $total += 2; $w += check($cgpa,            6.5, 'CGPA ≥ 6.5',               $cl, $gaps, 2);
    $total += 2; $w += check($sql_score,       7.0, 'SQL Score ≥ 7/10',         $cl, $gaps, 2);
    $total += 2; $w += check($statistics_score,6.0, 'Statistics Score ≥ 6/10',  $cl, $gaps, 2);
    $total += 1; $w += check($python_score,    5.5, 'Python Score ≥ 5.5/10',    $cl, $gaps, 1);
    $total += 1; $w += check($communication_score,7,'Communication ≥ 7/10',     $cl, $gaps, 1);
    $total += 1; $w += check($resume_score,    6.0, 'Resume Score ≥ 6/10',      $cl, $gaps, 1);
    $total += 1; $w += check($github_projects, 1,   'GitHub Repos ≥ 1',         $cl, $gaps, 1);
    $total += 1; $w += check($certifications,  1,   'Certifications ≥ 1',       $cl, $gaps, 1);
    $total += 1; $w += check($internship_months,1,  'Internship Months ≥ 1',    $cl, $gaps, 1);
    $pct = round($w / $total * 100);
    $departments[] = [
        'id'    => 'bi_analytics',
        'icon'  => 'fa-solid fa-chart-bar',
        'color' => ['#60a5fa','#3b82f6'],
        'title' => 'Business Intelligence & Data Analytics',
        'desc'  => 'Transform raw business data into actionable dashboards and strategic reports.',
        'pct'   => $pct,
        'status'=> $pct >= 75 ? 'applicable' : ($pct >= 50 ? 'conditional' : 'not_applicable'),
        'checklist' => $cl,
        'gaps'  => $gaps,
        'roles' => ['BI Analyst', 'Data Analyst', 'Reporting Analyst', 'Dashboard Developer'],
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. Generative AI & Deep Learning Research
// ─────────────────────────────────────────────────────────────────────────────
{
    $cl = []; $gaps = []; $w = 0; $total = 0;
    $total += 2; $w += check($cgpa,            7.5, 'CGPA ≥ 7.5',               $cl, $gaps, 2);
    $total += 2; $w += check($dl_score,        7.0, 'Deep Learning Score ≥ 7/10',$cl,$gaps, 2);
    $total += 2; $w += check($genai_score,     7.0, 'GenAI Score ≥ 7/10',       $cl, $gaps, 2);
    $total += 2; $w += check($python_score,    7.5, 'Python Score ≥ 7.5/10',    $cl, $gaps, 2);
    $total += 1; $w += check($ml_score,        6.0, 'ML Score ≥ 6/10',          $cl, $gaps, 1);
    $total += 1; $w += check($deployed_projects,1,  'Deployed Projects ≥ 1',    $cl, $gaps, 1);
    $total += 1; $w += check($github_projects, 3,   'GitHub Repos ≥ 3',         $cl, $gaps, 1);
    $total += 1; $w += check($hackathons_attended,2,'Hackathons Attended ≥ 2',  $cl, $gaps, 1);
    $total += 1; $w += check($certifications,  2,   'Certifications ≥ 2',       $cl, $gaps, 1);
    $pct = round($w / $total * 100);
    $departments[] = [
        'id'    => 'genai',
        'icon'  => 'fa-solid fa-wand-magic-sparkles',
        'color' => ['#34d399','#10b981'],
        'title' => 'Generative AI & Deep Learning Research',
        'desc'  => 'Research and develop LLMs, diffusion models, transformers, and AI-powered products.',
        'pct'   => $pct,
        'status'=> $pct >= 75 ? 'applicable' : ($pct >= 50 ? 'conditional' : 'not_applicable'),
        'checklist' => $cl,
        'gaps'  => $gaps,
        'roles' => ['GenAI Developer', 'LLM Engineer (Junior)', 'AI Research Intern', 'Prompt Engineer'],
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// 5. Software Engineering & Full-Stack Development
// ─────────────────────────────────────────────────────────────────────────────
{
    $cl = []; $gaps = []; $w = 0; $total = 0;
    $total += 2; $w += check($cgpa,            6.5, 'CGPA ≥ 6.5',               $cl, $gaps, 2);
    $total += 2; $w += check($python_score,    6.0, 'Python / Coding ≥ 6/10',   $cl, $gaps, 2);
    $total += 2; $w += check($dsa_score,       7.0, 'DSA Score ≥ 7/10',         $cl, $gaps, 2);
    $total += 1; $w += check($github_projects, 3,   'GitHub Repos ≥ 3',         $cl, $gaps, 1);
    $total += 1; $w += check($end_to_end_projects,1,'End-to-End Projects ≥ 1',  $cl, $gaps, 1);
    $total += 1; $w += check($internship_months,1,  'Internship Months ≥ 1',    $cl, $gaps, 1);
    $total += 1; $w += check($mock_interview_score,6,'Mock Interview ≥ 6/10',   $cl, $gaps, 1);
    $total += 1; $w += check($communication_score,6,'Communication ≥ 6/10',     $cl, $gaps, 1);
    $total += 1; $w += check($resume_score,    6.0, 'Resume Score ≥ 6/10',      $cl, $gaps, 1);
    $pct = round($w / $total * 100);
    $departments[] = [
        'id'    => 'swe',
        'icon'  => 'fa-solid fa-code',
        'color' => ['#f472b6','#ec4899'],
        'title' => 'Software Engineering & Full-Stack Dev',
        'desc'  => 'Design and ship production-grade software systems, APIs, and web applications.',
        'pct'   => $pct,
        'status'=> $pct >= 75 ? 'applicable' : ($pct >= 50 ? 'conditional' : 'not_applicable'),
        'checklist' => $cl,
        'gaps'  => $gaps,
        'roles' => ['Junior Software Engineer', 'Backend Developer', 'Full-Stack Developer Trainee', 'SDE-1'],
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// 6. Cloud & MLOps Infrastructure
// ─────────────────────────────────────────────────────────────────────────────
{
    $cl = []; $gaps = []; $w = 0; $total = 0;
    $total += 2; $w += check($cgpa,            7.0, 'CGPA ≥ 7.0',               $cl, $gaps, 2);
    $total += 2; $w += check($python_score,    6.5, 'Python Score ≥ 6.5/10',    $cl, $gaps, 2);
    $total += 2; $w += check($ml_score,        6.0, 'ML Knowledge ≥ 6/10',      $cl, $gaps, 2);
    $total += 1; $w += check($deployed_projects,2,  'Deployed Projects ≥ 2',    $cl, $gaps, 1);
    $total += 1; $w += check($github_projects, 4,   'GitHub Repos ≥ 4',         $cl, $gaps, 1);
    $total += 1; $w += check($end_to_end_projects,2,'End-to-End Projects ≥ 2',  $cl, $gaps, 1);
    $total += 1; $w += check($certifications,  2,   'Certifications ≥ 2',       $cl, $gaps, 1);
    $total += 1; $w += check($dsa_score,       5.5, 'DSA Score ≥ 5.5/10',       $cl, $gaps, 1);
    $total += 1; $w += check($internship_months,1,  'Internship Months ≥ 1',    $cl, $gaps, 1);
    $pct = round($w / $total * 100);
    $departments[] = [
        'id'    => 'mlops',
        'icon'  => 'fa-solid fa-cloud',
        'color' => ['#fb923c','#f97316'],
        'title' => 'Cloud & MLOps Infrastructure',
        'desc'  => 'Deploy, monitor and scale ML pipelines on cloud infrastructure (AWS/GCP/Azure).',
        'pct'   => $pct,
        'status'=> $pct >= 75 ? 'applicable' : ($pct >= 50 ? 'conditional' : 'not_applicable'),
        'checklist' => $cl,
        'gaps'  => $gaps,
        'roles' => ['MLOps Engineer (Junior)', 'Cloud Data Engineer', 'DevOps-ML Trainee', 'Platform Engineer Intern'],
    ];
}

// ── Summary stats ─────────────────────────────────────────────────────────────
$applicableCount   = count(array_filter($departments, fn($d) => $d['status'] === 'applicable'));
$conditionalCount  = count(array_filter($departments, fn($d) => $d['status'] === 'conditional'));
$notApplicable     = count(array_filter($departments, fn($d) => $d['status'] === 'not_applicable'));

usort($departments, fn($a,$b) => $b['pct'] - $a['pct']);
$topDept = $departments[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Job Applicability | Hackathon Career Readiness</title>
    <meta name="description" content="Check your eligibility for job openings across 6 major technology departments based on your AI prediction and student profile.">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:     #080618;
            --bg2:    #0f0c29;
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
            background: radial-gradient(ellipse at 20% 0%, #1e0a5e 0%, #080618 50%),
                        radial-gradient(ellipse at 80% 100%, #0a2e1e 0%, #080618 50%);
            background-blend-mode: screen;
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
        .nav-right { display: flex; align-items: center; gap: .65rem; flex-wrap: wrap; }
        .nav-pill {
            display: flex; align-items: center; gap: .4rem;
            border-radius: 999px; padding: .4rem 1rem;
            font-size: .82rem; font-weight: 500;
            text-decoration: none; transition: background .2s;
            white-space: nowrap;
        }
        .np-indigo { background:rgba(99,102,241,.12); border:1px solid rgba(99,102,241,.25); color:#a5b4fc; }
        .np-indigo:hover { background:rgba(99,102,241,.25); }
        .np-emerald { background:rgba(52,211,153,.12); border:1px solid rgba(52,211,153,.25); color:#6ee7b7; }
        .np-emerald:hover { background:rgba(52,211,153,.25); }
        .np-amber  { background:rgba(251,191,36,.12); border:1px solid rgba(251,191,36,.25); color:#fcd34d; }
        .np-amber:hover  { background:rgba(251,191,36,.25); }
        .np-red    { background:rgba(248,113,113,.12); border:1px solid rgba(248,113,113,.25); color:#f87171; }
        .np-red:hover    { background:rgba(248,113,113,.22); }
        .user-pill {
            display: flex; align-items: center; gap: .4rem;
            background: rgba(99,102,241,.15); border: 1px solid rgba(99,102,241,.3);
            border-radius: 999px; padding: .4rem 1rem;
            font-size: .82rem; font-weight: 500;
        }
        .user-pill i { color: var(--indigo); }

        /* ── PAGE WRAPPER ── */
        .page { max-width: 1120px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }

        /* ── PAGE HEADER ── */
        .page-header {
            text-align: center;
            padding: 2.5rem 1rem 2rem;
        }
        .page-badge {
            display: inline-flex; align-items: center; gap: .5rem;
            background: rgba(99,102,241,.15); border: 1px solid rgba(99,102,241,.3);
            border-radius: 999px; padding: .35rem 1.1rem;
            font-size: .78rem; color: #a5b4fc; margin-bottom: 1.2rem;
            text-transform: uppercase; letter-spacing: .07em; font-weight: 600;
        }
        .page-header h1 {
            font-size: clamp(1.6rem, 4vw, 2.6rem); font-weight: 800; line-height: 1.2;
            margin-bottom: .75rem;
        }
        .page-header h1 span {
            background: linear-gradient(135deg,#6366f1,#8b5cf6,#ec4899);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .page-header p { color: var(--muted); max-width: 560px; margin: 0 auto; font-size: .95rem; line-height: 1.6; }

        /* ── SUMMARY BANNER ── */
        .summary-banner {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr));
            gap: 1rem; margin-bottom: 2rem;
        }
        .sum-card {
            background: var(--card); border: 1px solid var(--border);
            border-radius: 1.2rem; padding: 1.4rem 1.2rem;
            text-align: center; transition: transform .2s;
            position: relative; overflow: hidden;
        }
        .sum-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
        }
        .sum-card:hover { transform: translateY(-3px); }
        .sum-card.sc-green::before { background: linear-gradient(90deg,#4ade80,#10b981); }
        .sum-card.sc-amber::before { background: linear-gradient(90deg,#fbbf24,#f59e0b); }
        .sum-card.sc-rose::before  { background: linear-gradient(90deg,#f87171,#ef4444); }
        .sum-card.sc-indigo::before{ background: linear-gradient(90deg,#818cf8,#6366f1); }
        .sum-icon { font-size: 1.8rem; margin-bottom: .6rem; }
        .sum-num {
            font-size: 2.2rem; font-weight: 900; line-height: 1;
            margin-bottom: .25rem;
        }
        .sum-card.sc-green  .sum-num { color: #4ade80; }
        .sum-card.sc-amber  .sum-num { color: #fbbf24; }
        .sum-card.sc-rose   .sum-num { color: #f87171; }
        .sum-card.sc-indigo .sum-num { color: #818cf8; }
        .sum-label { font-size: .78rem; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: .06em; }
        .sum-sub { font-size: .8rem; color: var(--muted); margin-top: .25rem; }

        /* ── AI READINESS NOTICE ── */
        .readiness-banner {
            display: flex; align-items: center; gap: 1rem;
            background: rgba(99,102,241,.08); border: 1px solid rgba(99,102,241,.2);
            border-radius: 1rem; padding: 1rem 1.5rem;
            margin-bottom: 2rem;
        }
        .readiness-icon { font-size: 1.5rem; color: #818cf8; flex-shrink: 0; }
        .readiness-text h4 { font-size: .95rem; font-weight: 700; margin-bottom: .2rem; }
        .readiness-text p { font-size: .82rem; color: var(--muted); }
        .readiness-badge {
            margin-left: auto; flex-shrink: 0;
            display: flex; align-items: center; gap: .4rem;
            border-radius: 999px; padding: .35rem .9rem;
            font-size: .8rem; font-weight: 700;
        }
        .rb-green { background: rgba(74,222,128,.15); border:1px solid rgba(74,222,128,.3); color:#4ade80; }
        .rb-red   { background: rgba(248,113,113,.15); border:1px solid rgba(248,113,113,.3); color:#f87171; }
        .rb-muted { background: rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.15); color:var(--muted); }

        /* ── FILTER BAR ── */
        .filter-bar {
            display: flex; gap: .75rem; flex-wrap: wrap;
            margin-bottom: 2rem; align-items: center;
        }
        .filter-btn {
            display: flex; align-items: center; gap: .4rem;
            border-radius: 999px; padding: .45rem 1.1rem;
            font-size: .82rem; font-weight: 600; cursor: pointer;
            border: 1px solid var(--border); background: var(--card);
            color: var(--muted); transition: all .2s;
        }
        .filter-btn:hover, .filter-btn.active {
            background: rgba(99,102,241,.15);
            border-color: rgba(99,102,241,.4); color: #a5b4fc;
        }
        .filter-btn .dot { width: 8px; height: 8px; border-radius: 50%; }
        .dot-all    { background: #818cf8; }
        .dot-green  { background: #4ade80; }
        .dot-amber  { background: #fbbf24; }
        .dot-red    { background: #f87171; }

        /* ── DEPT CARDS GRID ── */
        .dept-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(480px,1fr));
            gap: 1.5rem;
        }

        /* ── DEPARTMENT CARD ── */
        .dept-card {
            background: var(--card); border: 1px solid var(--border);
            border-radius: 1.4rem; overflow: hidden;
            transition: transform .25s, box-shadow .25s;
        }
        .dept-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 50px rgba(0,0,0,0.35);
        }
        .dept-card-top {
            padding: 1.4rem 1.5rem 1.1rem;
            display: flex; align-items: flex-start; gap: 1rem;
            position: relative;
        }
        .dept-card-top::after {
            content: ''; position: absolute; bottom: 0; left: 1.5rem; right: 1.5rem;
            height: 1px; background: var(--border);
        }
        .dept-icon {
            width: 52px; height: 52px; border-radius: 1rem;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; flex-shrink: 0;
        }
        .dept-meta { flex: 1; min-width: 0; }
        .dept-name { font-size: 1rem; font-weight: 700; margin-bottom: .2rem; }
        .dept-desc { font-size: .8rem; color: var(--muted); line-height: 1.5; }
        .dept-badge {
            flex-shrink: 0; display: flex; align-items: center; gap: .35rem;
            border-radius: 999px; padding: .35rem .9rem;
            font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em;
        }
        .badge-applicable   { background:rgba(74,222,128,.15); border:1px solid rgba(74,222,128,.3); color:#4ade80; }
        .badge-conditional  { background:rgba(251,191,36,.15); border:1px solid rgba(251,191,36,.3); color:#fbbf24; }
        .badge-not          { background:rgba(248,113,113,.12); border:1px solid rgba(248,113,113,.3); color:#f87171; }

        .dept-body { padding: 1.2rem 1.5rem; }

        /* ── MATCH SCORE BAR ── */
        .match-row {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: .5rem;
        }
        .match-label { font-size: .78rem; color: var(--muted); font-weight: 600; }
        .match-pct   { font-size: .9rem; font-weight: 800; }
        .match-bar-bg {
            height: 8px; border-radius: 4px;
            background: rgba(255,255,255,.07);
            overflow: hidden; margin-bottom: 1.1rem;
        }
        .match-bar-fill {
            height: 100%; border-radius: 4px; width: 0;
            transition: width 1.2s cubic-bezier(.4,0,.2,1);
        }

        /* ── CHECKLIST ── */
        .checklist-title {
            font-size: .75rem; font-weight: 700; color: var(--muted);
            text-transform: uppercase; letter-spacing: .07em;
            margin-bottom: .6rem;
        }
        .checklist { display: flex; flex-direction: column; gap: .35rem; margin-bottom: 1.1rem; }
        .cl-item {
            display: flex; align-items: center; gap: .55rem;
            font-size: .82rem;
        }
        .cl-icon {
            width: 18px; height: 18px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: .6rem; flex-shrink: 0;
        }
        .cl-pass { background:rgba(74,222,128,.2); color:#4ade80; }
        .cl-fail { background:rgba(248,113,113,.15); color:#f87171; }
        .cl-text { flex: 1; }
        .cl-text.fail-text { color: rgba(255,255,255,.5); }
        .cl-val {
            font-size: .75rem; font-weight: 700;
            padding: .1rem .45rem; border-radius: .35rem;
        }
        .cv-pass { background:rgba(74,222,128,.12); color:#86efac; }
        .cv-fail { background:rgba(248,113,113,.1); color:#fca5a5; }

        /* ── ROLES ── */
        .roles-title {
            font-size: .75rem; font-weight: 700; color: var(--muted);
            text-transform: uppercase; letter-spacing: .07em;
            margin-bottom: .55rem;
        }
        .roles-list { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: 1.1rem; }
        .role-chip {
            display: flex; align-items: center; gap: .3rem;
            background: rgba(99,102,241,.1); border: 1px solid rgba(99,102,241,.2);
            border-radius: 999px; padding: .25rem .75rem;
            font-size: .75rem; color: #c7d2fe; font-weight: 500;
        }
        .role-chip i { font-size: .6rem; }

        /* ── APPLY BUTTON ── */
        .dept-footer {
            padding: 0 1.5rem 1.4rem;
            display: flex; gap: .65rem; flex-wrap: wrap;
        }
        .apply-btn {
            flex: 1; display: flex; align-items: center; justify-content: center; gap: .5rem;
            border-radius: .75rem; padding: .65rem 1.25rem;
            font-size: .85rem; font-weight: 700; cursor: pointer;
            border: none; font-family: 'Inter', sans-serif;
            text-decoration: none; transition: all .2s;
        }
        .apply-green {
            background: linear-gradient(135deg,#10b981,#4ade80);
            color: #022c22;
        }
        .apply-green:hover { transform: translateY(-2px); box-shadow:0 8px 24px rgba(74,222,128,.3); }
        .apply-amber {
            background: linear-gradient(135deg,#d97706,#fbbf24);
            color: #1c1003;
        }
        .apply-amber:hover { transform: translateY(-2px); box-shadow:0 8px 24px rgba(251,191,36,.3); }
        .apply-outline {
            background: transparent; border: 1px solid var(--border); color: var(--muted);
            flex: 0 1 auto;
        }
        .apply-outline:hover { background: rgba(255,255,255,.06); color: var(--text); }
        .apply-disabled {
            background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08);
            color: rgba(255,255,255,.25); cursor: not-allowed; flex: 1;
            display: flex; align-items: center; justify-content: center; gap: .5rem;
            border-radius: .75rem; padding: .65rem 1.25rem; font-size: .85rem; font-weight: 700;
        }

        /* ── GAP TIPS ── */
        .gap-section { margin-bottom: 1.1rem; }
        .gap-title {
            font-size: .75rem; font-weight: 700; color: #fb923c;
            text-transform: uppercase; letter-spacing: .07em; margin-bottom: .5rem;
        }
        .gap-item {
            display: flex; align-items: flex-start; gap: .5rem;
            font-size: .78rem; color: rgba(255,255,255,.55); margin-bottom: .3rem;
        }
        .gap-item i { color: #fb923c; margin-top: .1rem; flex-shrink: 0; }

        /* ── ACTION ROW ── */
        .actions-row {
            display: flex; justify-content: center; gap: 1rem;
            flex-wrap: wrap; margin-top: 3rem;
        }
        .btn-action {
            display: flex; align-items: center; gap: .5rem;
            border-radius: .85rem; padding: .8rem 1.75rem;
            font-size: .9rem; font-weight: 700; cursor: pointer;
            text-decoration: none; font-family: 'Inter', sans-serif;
            border: none; transition: all .2s;
        }
        .btn-primary { background: linear-gradient(135deg,#6366f1,#8b5cf6); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow:0 12px 30px rgba(99,102,241,.4); }
        .btn-amber { background: linear-gradient(135deg,#d97706,#fbbf24); color: #1c1003; }
        .btn-amber:hover { transform: translateY(-2px); box-shadow:0 12px 30px rgba(251,191,36,.3); }
        .btn-outline-nav {
            background: transparent; border: 1px solid var(--border); color: var(--muted);
        }
        .btn-outline-nav:hover { background: rgba(255,255,255,.06); color: var(--text); }

        /* ── APPLY MODAL ── */
        .modal-overlay {
            display: none; position: fixed; inset: 0; z-index: 500;
            background: rgba(0,0,0,.7); backdrop-filter: blur(8px);
            align-items: center; justify-content: center;
        }
        .modal-overlay.open { display: flex; }
        .modal {
            background: #110f2a; border: 1px solid rgba(99,102,241,.3);
            border-radius: 1.5rem; padding: 2rem; max-width: 460px; width: 90%;
            animation: fadeInScale .25s ease;
        }
        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(.92); }
            to   { opacity: 1; transform: scale(1); }
        }
        .modal-title { font-size: 1.15rem; font-weight: 800; margin-bottom: .35rem; }
        .modal-sub { font-size: .85rem; color: var(--muted); margin-bottom: 1.25rem; }
        .modal-role-list { display: flex; flex-direction: column; gap: .5rem; margin-bottom: 1.5rem; }
        .modal-role-item {
            display: flex; align-items: center; gap: .75rem;
            background: rgba(255,255,255,.04); border: 1px solid var(--border);
            border-radius: .75rem; padding: .75rem 1rem; cursor: pointer;
            transition: background .2s;
        }
        .modal-role-item:hover, .modal-role-item.selected {
            background: rgba(99,102,241,.12); border-color: rgba(99,102,241,.35);
        }
        .modal-role-item input[type=radio] { accent-color: var(--indigo); }
        .modal-role-name { font-size: .88rem; font-weight: 600; }
        .modal-btns { display: flex; gap: .75rem; }
        .modal-confirm {
            flex: 1; background: linear-gradient(135deg,#6366f1,#8b5cf6); color:white;
            border: none; border-radius: .75rem; padding: .7rem;
            font-size: .88rem; font-weight: 700; cursor: pointer;
            font-family: 'Inter', sans-serif; transition: opacity .2s;
        }
        .modal-confirm:hover { opacity: .88; }
        .modal-cancel {
            background: transparent; border: 1px solid var(--border); color: var(--muted);
            border-radius: .75rem; padding: .7rem 1.25rem;
            font-size: .88rem; font-weight: 600; cursor: pointer;
            font-family: 'Inter', sans-serif; transition: background .2s;
        }
        .modal-cancel:hover { background: rgba(255,255,255,.06); }
        .modal-success {
            text-align: center; padding: 1.5rem 0 .5rem;
            animation: fadeInScale .3s ease;
        }
        .modal-success i { font-size: 3rem; color: #4ade80; margin-bottom: .75rem; display: block; }
        .modal-success h3 { font-size: 1.15rem; font-weight: 800; margin-bottom: .35rem; }
        .modal-success p { font-size: .85rem; color: var(--muted); }

        /* ── HIDDEN card helper ── */
        .dept-card.hidden { display: none; }

        /* ── FOOTER ── */
        footer {
            text-align: center; padding: 2rem;
            border-top: 1px solid var(--border);
            color: var(--muted); font-size: .85rem;
        }
        footer strong { color: var(--text); }

        /* ── RESPONSIVE ── */
        @media (max-width: 640px) {
            nav { padding: .75rem 1rem; }
            .dept-grid { grid-template-columns: 1fr; }
            .page { padding: 1.5rem 1rem 3rem; }
            .nav-right { gap: .4rem; }
        }
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
        <a href="predict.php" class="nav-pill np-indigo" id="runPredictNav">
            <i class="fa-solid fa-brain"></i> AI Predictor
        </a>
        <a href="post_applicability.php" class="nav-pill np-amber" id="jobPostsNav">
            <i class="fa-solid fa-file-contract"></i> Job Posts
        </a>
        <a href="student_details.php" class="nav-pill np-indigo" id="myProfileNav">
            <i class="fa-solid fa-id-card"></i> My Profile
        </a>
        <a href="dashboard.php" class="nav-pill np-indigo" id="dashboardNav">
            <i class="fa-solid fa-gauge"></i> Dashboard
        </a>
        <div class="user-pill">
            <i class="fa-solid fa-circle-user"></i>
            <?= $userName ?>
        </div>
        <a href="logout.php" class="nav-pill np-red" id="logoutNav">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </div>
</nav>

<!-- ── PAGE ── -->
<div class="page">

    <!-- BREADCRUMB -->
    <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:1.5rem;flex-wrap:wrap">
        <a href="predict.php" id="backToPredictBreadcrumb" style="
            display:inline-flex;align-items:center;gap:.45rem;
            background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.25);
            border-radius:999px;padding:.35rem 1rem;
            font-size:.8rem;font-weight:600;color:#a5b4fc;
            text-decoration:none;transition:background .2s;
        "
        onmouseover="this.style.background='rgba(99,102,241,.22)'"
        onmouseout="this.style.background='rgba(99,102,241,.1)'">
            <i class="fa-solid fa-arrow-left"></i> My AI Prediction Results
        </a>
        <span style="color:rgba(255,255,255,.25);font-size:.8rem">/</span>
        <span style="font-size:.8rem;color:rgba(255,255,255,.5);font-weight:600">
            <i class="fa-solid fa-briefcase" style="margin-right:.3rem"></i>Department Applicability
        </span>
    </div>

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div class="page-badge">
            <i class="fa-solid fa-briefcase"></i>
            Department Eligibility Engine
        </div>
        <h1>Are You <span>Applicable?</span></h1>
        <p>Our engine cross-checks your profile against real hiring benchmarks across 6 major technology departments — see exactly where you stand and how to close the gap.</p>
    </div>

    <!-- AI READINESS NOTICE -->
    <div class="readiness-banner">
        <div class="readiness-icon"><i class="fa-solid fa-microchip"></i></div>
        <div class="readiness-text">
            <h4>Based on Your AI Prediction Result</h4>
            <p>
                Career Track: <strong style="color:#c7d2fe"><?= htmlspecialchars($careerTrackLabel) ?></strong>
                &nbsp;·&nbsp;
                <?php if ($jobReady !== null): ?>
                    Job Readiness Score: <strong style="color:#c7d2fe"><?= number_format($jobReadyPct,1) ?>%</strong>
                <?php else: ?>
                    <em>No AI prediction found yet — <a href="predict.php" style="color:#818cf8">run the predictor</a> for richer insights.</em>
                <?php endif; ?>
            </p>
        </div>
        <?php if ($jobReady === true): ?>
            <span class="readiness-badge rb-green"><i class="fa-solid fa-circle-check"></i> Job Ready</span>
        <?php elseif ($jobReady === false): ?>
            <span class="readiness-badge rb-red"><i class="fa-solid fa-circle-xmark"></i> Not Yet Ready</span>
        <?php else: ?>
            <span class="readiness-badge rb-muted"><i class="fa-solid fa-circle-question"></i> Not Predicted</span>
        <?php endif; ?>
    </div>

    <!-- SUMMARY BANNER -->
    <div class="summary-banner" id="summaryBanner">
        <div class="sum-card sc-green">
            <div class="sum-icon">✅</div>
            <div class="sum-num"><?= $applicableCount ?></div>
            <div class="sum-label">Applicable</div>
            <div class="sum-sub">Departments where you meet full criteria</div>
        </div>
        <div class="sum-card sc-amber">
            <div class="sum-icon">⚡</div>
            <div class="sum-num"><?= $conditionalCount ?></div>
            <div class="sum-label">Conditionally Applicable</div>
            <div class="sum-sub">Close — a few gaps to bridge</div>
        </div>
        <div class="sum-card sc-rose">
            <div class="sum-icon">🎯</div>
            <div class="sum-num"><?= $notApplicable ?></div>
            <div class="sum-label">Not Yet Eligible</div>
            <div class="sum-sub">Needs significant upskilling</div>
        </div>
        <div class="sum-card sc-indigo">
            <div class="sum-icon">🏆</div>
            <div class="sum-num" style="font-size:1.1rem; margin-bottom:.4rem;"><?= htmlspecialchars($topDept['title']) ?></div>
            <div class="sum-label">Best Match Department</div>
            <div class="sum-sub"><?= $topDept['pct'] ?>% compatibility</div>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="filter-bar" id="filterBar">
        <button class="filter-btn active" id="filterAll" onclick="filterCards('all')">
            <span class="dot dot-all"></span> All Departments
        </button>
        <button class="filter-btn" id="filterApplicable" onclick="filterCards('applicable')">
            <span class="dot dot-green"></span> Applicable
        </button>
        <button class="filter-btn" id="filterConditional" onclick="filterCards('conditional')">
            <span class="dot dot-amber"></span> Conditionally Applicable
        </button>
        <button class="filter-btn" id="filterNot" onclick="filterCards('not_applicable')">
            <span class="dot dot-red"></span> Not Yet Eligible
        </button>
    </div>

    <!-- DEPARTMENT CARDS -->
    <div class="dept-grid" id="deptGrid">

    <?php foreach ($departments as $dept): ?>
        <?php
            $statusClass   = $dept['status'] === 'applicable' ? 'badge-applicable'
                           : ($dept['status'] === 'conditional' ? 'badge-conditional' : 'badge-not');
            $statusLabel   = $dept['status'] === 'applicable' ? '✓ Applicable'
                           : ($dept['status'] === 'conditional' ? '⚡ Conditional' : '✗ Not Eligible');
            $gradFrom      = $dept['color'][0];
            $gradTo        = $dept['color'][1];
            $matchColor    = $dept['status'] === 'applicable' ? '#4ade80'
                           : ($dept['status'] === 'conditional' ? '#fbbf24' : '#f87171');
            $iconBg        = "rgba(" . implode(',', sscanf($gradTo, '#%02x%02x%02x')) . ",0.18)";
        ?>
        <div class="dept-card" data-status="<?= $dept['status'] ?>" id="card-<?= $dept['id'] ?>">

            <!-- TOP HEADER -->
            <div class="dept-card-top">
                <div class="dept-icon" style="background:<?= $iconBg ?>; color:<?= $gradFrom ?>;">
                    <i class="<?= $dept['icon'] ?>"></i>
                </div>
                <div class="dept-meta">
                    <div class="dept-name"><?= htmlspecialchars($dept['title']) ?></div>
                    <div class="dept-desc"><?= htmlspecialchars($dept['desc']) ?></div>
                </div>
                <div class="dept-badge <?= $statusClass ?>"><?= $statusLabel ?></div>
            </div>

            <!-- BODY -->
            <div class="dept-body">

                <!-- Match Score Bar -->
                <div class="match-row">
                    <span class="match-label">Department Match</span>
                    <span class="match-pct" style="color:<?= $matchColor ?>"><?= $dept['pct'] ?>%</span>
                </div>
                <div class="match-bar-bg">
                    <div class="match-bar-fill"
                         data-pct="<?= $dept['pct'] ?>"
                         style="background:linear-gradient(90deg,<?= $gradTo ?>,<?= $gradFrom ?>);">
                    </div>
                </div>

                <!-- Eligibility Checklist -->
                <div class="checklist-title"><i class="fa-solid fa-list-check"></i> Eligibility Criteria</div>
                <div class="checklist">
                <?php foreach ($dept['checklist'] as $item): ?>
                    <div class="cl-item">
                        <div class="cl-icon <?= $item['pass'] ? 'cl-pass' : 'cl-fail' ?>">
                            <i class="fa-solid <?= $item['pass'] ? 'fa-check' : 'fa-xmark' ?>"></i>
                        </div>
                        <span class="cl-text <?= $item['pass'] ? '' : 'fail-text' ?>"><?= htmlspecialchars($item['label']) ?></span>
                        <span class="cl-val <?= $item['pass'] ? 'cv-pass' : 'cv-fail' ?>"><?= is_numeric($item['val']) ? number_format((float)$item['val'],1) : htmlspecialchars($item['val']) ?></span>
                    </div>
                <?php endforeach; ?>
                </div>

                <!-- Gap / Improvement Tips -->
                <?php if (!empty($dept['gaps'])): ?>
                <div class="gap-section">
                    <div class="gap-title"><i class="fa-solid fa-triangle-exclamation"></i> Areas to Improve</div>
                    <?php foreach ($dept['gaps'] as $gap): ?>
                    <div class="gap-item">
                        <i class="fa-solid fa-arrow-trend-up"></i>
                        <span><?= htmlspecialchars($gap) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Available Roles -->
                <div class="roles-title"><i class="fa-solid fa-user-tie"></i> Open Job Roles</div>
                <div class="roles-list">
                <?php foreach ($dept['roles'] as $role): ?>
                    <span class="role-chip">
                        <i class="fa-solid fa-circle" style="font-size:.4rem"></i>
                        <?= htmlspecialchars($role) ?>
                    </span>
                <?php endforeach; ?>
                </div>
            </div>

            <!-- FOOTER BUTTONS -->
            <div class="dept-footer">
                <?php if ($dept['status'] === 'applicable'): ?>
                    <button class="apply-btn apply-green"
                            onclick="openApplyModal('<?= htmlspecialchars(addslashes($dept['title'])) ?>',
                                                    <?= json_encode($dept['roles']) ?>)"
                            id="apply-<?= $dept['id'] ?>">
                        <i class="fa-solid fa-paper-plane"></i> Express Interest / Apply
                    </button>
                <?php elseif ($dept['status'] === 'conditional'): ?>
                    <button class="apply-btn apply-amber"
                            onclick="openApplyModal('<?= htmlspecialchars(addslashes($dept['title'])) ?>',
                                                    <?= json_encode($dept['roles']) ?>)"
                            id="apply-<?= $dept['id'] ?>">
                        <i class="fa-solid fa-paper-plane"></i> Express Interest (Conditional)
                    </button>
                <?php else: ?>
                    <span class="apply-disabled"><i class="fa-solid fa-lock"></i> Not Yet Eligible to Apply</span>
                <?php endif; ?>
                <a href="student_details.php" class="apply-btn apply-outline" id="improve-<?= $dept['id'] ?>">
                    <i class="fa-solid fa-pen-to-square"></i> Update Profile
                </a>
            </div>
        </div>
    <?php endforeach; ?>

    </div><!-- /dept-grid -->

    <!-- ═══ NEXT STEP: SPECIFIC JOB POSTS CTA ═══ -->
    <div style="
        background: linear-gradient(135deg, rgba(99,102,241,.13) 0%, rgba(139,92,246,.10) 100%);
        border: 1.5px solid rgba(99,102,241,.35);
        border-radius: 1.5rem;
        padding: 1.75rem 2rem;
        margin-top: 2.5rem;
        margin-bottom: 1rem;
        position: relative;
        overflow: hidden;
    " id="jobPostNextStep">
        <!-- Glow orb -->
        <div style="position:absolute;top:-50px;right:-50px;width:180px;height:180px;
                    background:radial-gradient(circle,rgba(99,102,241,.22),transparent 70%);
                    border-radius:50%;pointer-events:none;"></div>
        <!-- Sparkle orb bottom-left -->
        <div style="position:absolute;bottom:-30px;left:-30px;width:120px;height:120px;
                    background:radial-gradient(circle,rgba(139,92,246,.18),transparent 70%);
                    border-radius:50%;pointer-events:none;"></div>
        <div style="display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap;position:relative;">
            <div style="
                width:58px;height:58px;border-radius:1.1rem;flex-shrink:0;
                background:linear-gradient(135deg,#6366f1,#8b5cf6);
                display:flex;align-items:center;justify-content:center;
                font-size:1.5rem;color:white;
                box-shadow:0 0 28px rgba(99,102,241,.4);
                animation:postCtaPulse 2.8s ease-in-out infinite;
            "><i class="fa-solid fa-file-contract"></i></div>
            <div style="flex:1;min-width:200px">
                <div style="font-size:1.05rem;font-weight:800;color:#c7d2fe;margin-bottom:.35rem">
                    📋 Next Step — Browse Specific Job Posts
                </div>
                <div style="font-size:.86rem;color:rgba(255,255,255,.6);line-height:1.6">
                    Department check done! Now explore <strong style="color:#a5b4fc">15 real job post profiles</strong> —
                    see your exact eligibility, required skills, salary ranges and improvement gaps for each role.
                </div>
            </div>
            <a href="post_applicability.php" id="goToJobPostsBtn"
               style="
                flex-shrink:0;
                display:inline-flex;align-items:center;gap:.65rem;
                background:linear-gradient(135deg,#6366f1,#8b5cf6);
                color:white;border-radius:1rem;
                padding:.9rem 1.85rem;
                font-size:.95rem;font-weight:800;
                text-decoration:none;
                box-shadow:0 0 28px rgba(99,102,241,.45);
                transition:all .25s;
                white-space:nowrap;
               "
               onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='0 0 44px rgba(99,102,241,.65)';"
               onmouseout="this.style.transform='';this.style.boxShadow='0 0 28px rgba(99,102,241,.45)';">
                <i class="fa-solid fa-arrow-right-long"></i> View Job Posts
            </a>
        </div>
        <!-- Mini role hints -->
        <div style="display:flex;gap:1.25rem;flex-wrap:wrap;margin-top:1.2rem;padding-top:1rem;
                    border-top:1px solid rgba(99,102,241,.18);position:relative;">
            <span style="font-size:.78rem;color:rgba(255,255,255,.45);display:flex;align-items:center;gap:.4rem">
                <i class="fa-solid fa-circle-check" style="color:#818cf8"></i> Junior ML Engineer
            </span>
            <span style="font-size:.78rem;color:rgba(255,255,255,.45);display:flex;align-items:center;gap:.4rem">
                <i class="fa-solid fa-circle-check" style="color:#a78bfa"></i> Data Analyst
            </span>
            <span style="font-size:.78rem;color:rgba(255,255,255,.45);display:flex;align-items:center;gap:.4rem">
                <i class="fa-solid fa-circle-check" style="color:#34d399"></i> GenAI Developer
            </span>
            <span style="font-size:.78rem;color:rgba(255,255,255,.45);display:flex;align-items:center;gap:.4rem">
                <i class="fa-solid fa-circle-check" style="color:#f472b6"></i> SDE-1
            </span>
            <span style="font-size:.78rem;color:rgba(255,255,255,.45);display:flex;align-items:center;gap:.4rem">
                <i class="fa-solid fa-circle-check" style="color:#60a5fa"></i> BI Analyst
            </span>
            <span style="font-size:.78rem;color:rgba(255,255,255,.45);display:flex;align-items:center;gap:.4rem">
                <i class="fa-solid fa-circle-check" style="color:#fb923c"></i> MLOps Engineer
            </span>
            <span style="font-size:.78rem;color:rgba(255,255,255,.45);display:flex;align-items:center;gap:.4rem">
                <i class="fa-solid fa-circle-check" style="color:#e879f9"></i> + 9 more roles
            </span>
        </div>
    </div>
    <style>
        @keyframes postCtaPulse {
            0%,100% { box-shadow: 0 0 28px rgba(99,102,241,.4); }
            50%      { box-shadow: 0 0 48px rgba(99,102,241,.7); }
        }
    </style>

    <!-- ACTION BUTTONS -->
    <div class="actions-row">
        <a href="post_applicability.php" class="btn-action btn-primary" id="viewJobPostsBtn">
            <i class="fa-solid fa-file-contract"></i> Browse Job Posts
        </a>
        <a href="predict.php" class="btn-action btn-outline-nav" id="viewPredictionBtn" style="background:rgba(99,102,241,.1);border-color:rgba(99,102,241,.3);color:#a5b4fc">
            <i class="fa-solid fa-brain"></i> My AI Prediction
        </a>
        <a href="student_details.php" class="btn-action btn-amber" id="updateProfileBtn">
            <i class="fa-solid fa-pen-to-square"></i> Update My Profile
        </a>
        <a href="dashboard.php" class="btn-action btn-outline-nav" id="backDashBtn">
            <i class="fa-solid fa-gauge"></i> Back to Dashboard
        </a>
    </div>

</div><!-- /page -->

<!-- ── APPLY MODAL ── -->
<div class="modal-overlay" id="applyModalOverlay">
    <div class="modal">
        <div id="modalContent">
            <div class="modal-title" id="modalTitle">Express Interest</div>
            <div class="modal-sub" id="modalSub">Select the role you'd like to express interest in:</div>
            <div class="modal-role-list" id="modalRoleList"></div>
            <div class="modal-btns">
                <button class="modal-confirm" id="modalConfirmBtn" onclick="confirmApply()">
                    <i class="fa-solid fa-paper-plane"></i> Confirm Interest
                </button>
                <button class="modal-cancel" onclick="closeModal()">Cancel</button>
            </div>
        </div>
    </div>
</div>

<footer>
    <i class="fa-solid fa-briefcase"></i>&nbsp;
    <strong>Hackathon Success &amp; AI Career Readiness Prediction System</strong>
    &nbsp;|&nbsp; Built by Shilpi, Priyanshu, Dushyant &amp; Vedika · B.Tech CSE Group Project
</footer>

<script>
// ── Filter department cards ─────────────────────────────────────────────────
let currentFilter = 'all';
function filterCards(filter) {
    currentFilter = filter;
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('filter' + filter.charAt(0).toUpperCase() + filter.slice(1).replace('_applicable','Not').replace('not','Not')).classList.add('active');
    const map = { all: null, applicable: 'applicable', conditional: 'conditional', not_applicable: 'not_applicable' };
    document.querySelectorAll('.dept-card').forEach(card => {
        const s = card.dataset.status;
        card.classList.toggle('hidden', map[filter] !== null && s !== map[filter]);
    });
}
// Fix filter IDs mapping
const filterMap = {
    'all':           'filterAll',
    'applicable':    'filterApplicable',
    'conditional':   'filterConditional',
    'not_applicable':'filterNot',
};
function filterCards(filter) {
    currentFilter = filter;
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(filterMap[filter]).classList.add('active');
    document.querySelectorAll('.dept-card').forEach(card => {
        const s = card.dataset.status;
        card.classList.toggle('hidden', filter !== 'all' && s !== filter);
    });
}

// ── Animate match bars on load ──────────────────────────────────────────────
window.addEventListener('load', () => {
    document.querySelectorAll('.match-bar-fill').forEach(bar => {
        const pct = parseFloat(bar.dataset.pct) || 0;
        setTimeout(() => { bar.style.width = pct + '%'; }, 200);
    });
});

// ── Apply Modal ─────────────────────────────────────────────────────────────
let _currentDept  = '';
let _currentRoles = [];
let _selectedRole = '';

function openApplyModal(dept, roles) {
    _currentDept  = dept;
    _currentRoles = roles;
    _selectedRole = roles[0] || '';

    document.getElementById('modalTitle').textContent = 'Express Interest — ' + dept;
    document.getElementById('modalSub').textContent   = 'Select the role you want to express interest in:';

    const list = document.getElementById('modalRoleList');
    list.innerHTML = '';
    roles.forEach((role, i) => {
        const item = document.createElement('label');
        item.className = 'modal-role-item' + (i === 0 ? ' selected' : '');
        item.innerHTML = `<input type="radio" name="modalRole" value="${role}" ${i===0?'checked':''}> <span class="modal-role-name">${role}</span>`;
        item.addEventListener('change', () => {
            document.querySelectorAll('.modal-role-item').forEach(l => l.classList.remove('selected'));
            item.classList.add('selected');
            _selectedRole = role;
        });
        item.addEventListener('click', () => {
            item.querySelector('input').checked = true;
            _selectedRole = role;
            document.querySelectorAll('.modal-role-item').forEach(l => l.classList.remove('selected'));
            item.classList.add('selected');
        });
        list.appendChild(item);
    });

    document.getElementById('applyModalOverlay').classList.add('open');
}

function closeModal() {
    document.getElementById('applyModalOverlay').classList.remove('open');
    setTimeout(() => {
        document.getElementById('modalContent').innerHTML = `
            <div class="modal-title" id="modalTitle">Express Interest</div>
            <div class="modal-sub" id="modalSub">Select the role you'd like to express interest in:</div>
            <div class="modal-role-list" id="modalRoleList"></div>
            <div class="modal-btns">
                <button class="modal-confirm" id="modalConfirmBtn" onclick="confirmApply()">
                    <i class="fa-solid fa-paper-plane"></i> Confirm Interest
                </button>
                <button class="modal-cancel" onclick="closeModal()">Cancel</button>
            </div>`;
    }, 300);
}

function confirmApply() {
    document.getElementById('modalContent').innerHTML = `
        <div class="modal-success">
            <i class="fa-solid fa-circle-check"></i>
            <h3>Interest Registered! 🎉</h3>
            <p>You've expressed interest in <strong style="color:#c7d2fe">${_selectedRole}</strong> at the <strong style="color:#c7d2fe">${_currentDept}</strong> department.</p>
            <p style="margin-top:.75rem">This has been noted in your profile. Keep improving your scores to strengthen your application!</p>
            <button class="modal-confirm" style="margin-top:1.25rem;width:100%;max-width:240px;" onclick="closeModal()">
                <i class="fa-solid fa-thumbs-up"></i> Awesome, Thanks!
            </button>
        </div>`;
}

// Close modal on backdrop click
document.getElementById('applyModalOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>
</body>
</html>
