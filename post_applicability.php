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
    header("Location: student_details.php?error=" . urlencode("Please complete your profile first to check post applicability."));
    exit();
}

// ── Fetch cached prediction ────────────────────────────────────────────────────
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

// ── Helper: check a single criterion ──────────────────────────────────────────
function chk($val, $min, $label, &$checklist, &$gaps, $weight = 1) {
    $pass = $val >= $min;
    $checklist[] = ['label' => $label, 'pass' => $pass, 'val' => $val, 'min' => $min];
    if (!$pass) $gaps[] = "$label (need $min, have $val)";
    return $pass ? $weight : 0;
}

// ── Job Posts Eligibility Engine ───────────────────────────────────────────────
$posts = [];

// 1. Junior ML Engineer
{
    $cl=[]; $gaps=[]; $w=0; $total=0;
    $total+=2; $w+=chk($cgpa,               7.0, 'CGPA >= 7.0',              $cl,$gaps,2);
    $total+=2; $w+=chk($python_score,        7.0, 'Python >= 7/10',           $cl,$gaps,2);
    $total+=2; $w+=chk($ml_score,            7.0, 'ML Score >= 7/10',         $cl,$gaps,2);
    $total+=1; $w+=chk($ml_projects,         2,   'ML Projects >= 2',         $cl,$gaps,1);
    $total+=1; $w+=chk($github_projects,     3,   'GitHub Repos >= 3',        $cl,$gaps,1);
    $total+=1; $w+=chk($statistics_score,    5.5, 'Stats >= 5.5/10',          $cl,$gaps,1);
    $total+=1; $w+=chk($ml_interview_score,  6.0, 'ML Interview >= 6/10',     $cl,$gaps,1);
    $pct = round($w/$total*100);
    $posts[] = ['id'=>'jr_ml','icon'=>'fa-solid fa-robot','color'=>['#818cf8','#6366f1'],
        'title'=>'Junior ML Engineer','company_type'=>'Product / AI Startup',
        'salary'=>'Rs.6-10 LPA','exp_needed'=>'0-1 yr',
        'desc'=>'Build and fine-tune ML models for core product features. Work with Python, scikit-learn, TensorFlow/PyTorch.',
        'pct'=>$pct,'status'=>$pct>=75?'applicable':($pct>=50?'conditional':'not_applicable'),
        'checklist'=>$cl,'gaps'=>$gaps,
        'skills'=>['Python','scikit-learn','TensorFlow','Pandas','NumPy','Git']];
}

// 2. Data Analyst
{
    $cl=[]; $gaps=[]; $w=0; $total=0;
    $total+=2; $w+=chk($cgpa,               6.5, 'CGPA >= 6.5',              $cl,$gaps,2);
    $total+=2; $w+=chk($sql_score,           6.5, 'SQL >= 6.5/10',            $cl,$gaps,2);
    $total+=2; $w+=chk($statistics_score,    6.0, 'Stats >= 6/10',            $cl,$gaps,2);
    $total+=1; $w+=chk($python_score,        5.5, 'Python >= 5.5/10',         $cl,$gaps,1);
    $total+=1; $w+=chk($communication_score, 6.0, 'Communication >= 6/10',    $cl,$gaps,1);
    $total+=1; $w+=chk($resume_score,        6.0, 'Resume Score >= 6/10',     $cl,$gaps,1);
    $total+=1; $w+=chk($certifications,      1,   'Certifications >= 1',      $cl,$gaps,1);
    $pct = round($w/$total*100);
    $posts[] = ['id'=>'data_analyst','icon'=>'fa-solid fa-chart-column','color'=>['#60a5fa','#3b82f6'],
        'title'=>'Data Analyst','company_type'=>'BFSI / E-commerce / Consulting',
        'salary'=>'Rs.4-8 LPA','exp_needed'=>'0-1 yr',
        'desc'=>'Analyse business datasets, build dashboards, and generate actionable insights using SQL, Excel and Python.',
        'pct'=>$pct,'status'=>$pct>=75?'applicable':($pct>=50?'conditional':'not_applicable'),
        'checklist'=>$cl,'gaps'=>$gaps,
        'skills'=>['SQL','Excel','Power BI / Tableau','Python','Statistics']];
}

// 3. GenAI / LLM Developer
{
    $cl=[]; $gaps=[]; $w=0; $total=0;
    $total+=2; $w+=chk($cgpa,               7.5, 'CGPA >= 7.5',              $cl,$gaps,2);
    $total+=2; $w+=chk($genai_score,         7.0, 'GenAI Score >= 7/10',      $cl,$gaps,2);
    $total+=2; $w+=chk($dl_score,            7.0, 'Deep Learning >= 7/10',    $cl,$gaps,2);
    $total+=2; $w+=chk($python_score,        7.5, 'Python >= 7.5/10',         $cl,$gaps,2);
    $total+=1; $w+=chk($deployed_projects,   1,   'Deployed Projects >= 1',   $cl,$gaps,1);
    $total+=1; $w+=chk($github_projects,     3,   'GitHub Repos >= 3',        $cl,$gaps,1);
    $total+=1; $w+=chk($certifications,      2,   'Certifications >= 2',      $cl,$gaps,1);
    $pct = round($w/$total*100);
    $posts[] = ['id'=>'genai_dev','icon'=>'fa-solid fa-wand-magic-sparkles','color'=>['#34d399','#10b981'],
        'title'=>'GenAI / LLM Developer','company_type'=>'AI Product Company / R&D Lab',
        'salary'=>'Rs.10-18 LPA','exp_needed'=>'0-2 yrs',
        'desc'=>'Develop LLM-based applications, RAG pipelines, prompt engineering, and AI-powered product features.',
        'pct'=>$pct,'status'=>$pct>=75?'applicable':($pct>=50?'conditional':'not_applicable'),
        'checklist'=>$cl,'gaps'=>$gaps,
        'skills'=>['LangChain','OpenAI API','Hugging Face','RAG','Python','Prompt Engineering']];
}

// 4. Junior Data Scientist
{
    $cl=[]; $gaps=[]; $w=0; $total=0;
    $total+=2; $w+=chk($cgpa,               7.0, 'CGPA >= 7.0',              $cl,$gaps,2);
    $total+=2; $w+=chk($statistics_score,    7.0, 'Stats >= 7/10',            $cl,$gaps,2);
    $total+=2; $w+=chk($python_score,        6.5, 'Python >= 6.5/10',         $cl,$gaps,2);
    $total+=2; $w+=chk($ml_score,            6.5, 'ML Score >= 6.5/10',       $cl,$gaps,2);
    $total+=1; $w+=chk($kaggle_competitions, 1,   'Kaggle Competitions >= 1', $cl,$gaps,1);
    $total+=1; $w+=chk($ml_projects,         2,   'ML Projects >= 2',         $cl,$gaps,1);
    $total+=1; $w+=chk($resume_score,        6.5, 'Resume Score >= 6.5/10',   $cl,$gaps,1);
    $pct = round($w/$total*100);
    $posts[] = ['id'=>'jr_ds','icon'=>'fa-solid fa-flask','color'=>['#a78bfa','#8b5cf6'],
        'title'=>'Junior Data Scientist','company_type'=>'Analytics / Research / SaaS',
        'salary'=>'Rs.7-12 LPA','exp_needed'=>'0-2 yrs',
        'desc'=>'Build predictive models and statistical analyses to guide product and business strategy decisions.',
        'pct'=>$pct,'status'=>$pct>=75?'applicable':($pct>=50?'conditional':'not_applicable'),
        'checklist'=>$cl,'gaps'=>$gaps,
        'skills'=>['Python','R','Statistics','ML Models','Pandas','Matplotlib']];
}

// 5. Software Developer SDE-1
{
    $cl=[]; $gaps=[]; $w=0; $total=0;
    $total+=2; $w+=chk($cgpa,               6.5, 'CGPA >= 6.5',              $cl,$gaps,2);
    $total+=2; $w+=chk($dsa_score,           7.0, 'DSA Score >= 7/10',        $cl,$gaps,2);
    $total+=2; $w+=chk($python_score,        6.0, 'Coding Score >= 6/10',     $cl,$gaps,2);
    $total+=1; $w+=chk($github_projects,     3,   'GitHub Repos >= 3',        $cl,$gaps,1);
    $total+=1; $w+=chk($end_to_end_projects, 1,   'End-to-End Projects >= 1', $cl,$gaps,1);
    $total+=1; $w+=chk($mock_interview_score,6.0, 'Mock Interview >= 6/10',   $cl,$gaps,1);
    $total+=1; $w+=chk($communication_score, 6.0, 'Communication >= 6/10',    $cl,$gaps,1);
    $pct = round($w/$total*100);
    $posts[] = ['id'=>'sde1','icon'=>'fa-solid fa-code','color'=>['#f472b6','#ec4899'],
        'title'=>'Software Developer (SDE-1)','company_type'=>'IT / Product / Service Company',
        'salary'=>'Rs.5-10 LPA','exp_needed'=>'0-1 yr',
        'desc'=>'Design and ship production-grade software, APIs, and web systems using modern tech stacks.',
        'pct'=>$pct,'status'=>$pct>=75?'applicable':($pct>=50?'conditional':'not_applicable'),
        'checklist'=>$cl,'gaps'=>$gaps,
        'skills'=>['DSA','System Design','REST APIs','Git','Python / Java / JS']];
}

// 6. BI Analyst
{
    $cl=[]; $gaps=[]; $w=0; $total=0;
    $total+=2; $w+=chk($sql_score,           7.0, 'SQL >= 7/10',              $cl,$gaps,2);
    $total+=2; $w+=chk($statistics_score,    6.0, 'Stats >= 6/10',            $cl,$gaps,2);
    $total+=1; $w+=chk($cgpa,               6.5, 'CGPA >= 6.5',              $cl,$gaps,1);
    $total+=1; $w+=chk($python_score,        5.5, 'Python >= 5.5/10',         $cl,$gaps,1);
    $total+=1; $w+=chk($communication_score, 7.0, 'Communication >= 7/10',    $cl,$gaps,1);
    $total+=1; $w+=chk($resume_score,        6.0, 'Resume Score >= 6/10',     $cl,$gaps,1);
    $total+=1; $w+=chk($internship_months,   1,   'Internship >= 1 month',    $cl,$gaps,1);
    $pct = round($w/$total*100);
    $posts[] = ['id'=>'bi_analyst','icon'=>'fa-solid fa-chart-pie','color'=>['#38bdf8','#0ea5e9'],
        'title'=>'BI Analyst','company_type'=>'BFSI / Retail / MNC',
        'salary'=>'Rs.5-9 LPA','exp_needed'=>'0-2 yrs',
        'desc'=>'Create interactive dashboards, KPI reports and pipelines to support business decision-making.',
        'pct'=>$pct,'status'=>$pct>=75?'applicable':($pct>=50?'conditional':'not_applicable'),
        'checklist'=>$cl,'gaps'=>$gaps,
        'skills'=>['Power BI','Tableau','SQL','Excel','Python','DAX']];
}

// 7. MLOps / Cloud ML Engineer
{
    $cl=[]; $gaps=[]; $w=0; $total=0;
    $total+=2; $w+=chk($cgpa,               7.0, 'CGPA >= 7.0',              $cl,$gaps,2);
    $total+=2; $w+=chk($python_score,        6.5, 'Python >= 6.5/10',         $cl,$gaps,2);
    $total+=2; $w+=chk($ml_score,            6.0, 'ML Knowledge >= 6/10',     $cl,$gaps,2);
    $total+=1; $w+=chk($deployed_projects,   2,   'Deployed Projects >= 2',   $cl,$gaps,1);
    $total+=1; $w+=chk($github_projects,     4,   'GitHub Repos >= 4',        $cl,$gaps,1);
    $total+=1; $w+=chk($certifications,      2,   'Certifications >= 2',      $cl,$gaps,1);
    $total+=1; $w+=chk($end_to_end_projects, 2,   'End-to-End Projects >= 2', $cl,$gaps,1);
    $pct = round($w/$total*100);
    $posts[] = ['id'=>'mlops','icon'=>'fa-solid fa-cloud-arrow-up','color'=>['#fb923c','#f97316'],
        'title'=>'MLOps Engineer (Junior)','company_type'=>'Cloud / Platform / Big Tech',
        'salary'=>'Rs.8-15 LPA','exp_needed'=>'0-2 yrs',
        'desc'=>'Deploy, monitor and automate ML pipelines using Docker, Kubernetes, and cloud platforms.',
        'pct'=>$pct,'status'=>$pct>=75?'applicable':($pct>=50?'conditional':'not_applicable'),
        'checklist'=>$cl,'gaps'=>$gaps,
        'skills'=>['Docker','Kubernetes','CI/CD','MLflow','AWS/GCP','Python']];
}

// 8. AI Research Intern
{
    $cl=[]; $gaps=[]; $w=0; $total=0;
    $total+=2; $w+=chk($cgpa,               8.0, 'CGPA >= 8.0 (Research)',   $cl,$gaps,2);
    $total+=2; $w+=chk($ml_score,            7.5, 'ML Score >= 7.5/10',       $cl,$gaps,2);
    $total+=2; $w+=chk($dl_score,            7.0, 'DL Score >= 7/10',         $cl,$gaps,2);
    $total+=1; $w+=chk($python_score,        7.0, 'Python >= 7/10',           $cl,$gaps,1);
    $total+=1; $w+=chk($hackathons_attended, 2,   'Hackathons >= 2',          $cl,$gaps,1);
    $total+=1; $w+=chk($github_projects,     3,   'GitHub Repos >= 3',        $cl,$gaps,1);
    $total+=1; $w+=chk($ml_projects,         2,   'ML Projects >= 2',         $cl,$gaps,1);
    $pct = round($w/$total*100);
    $posts[] = ['id'=>'ai_intern','icon'=>'fa-solid fa-microscope','color'=>['#e879f9','#d946ef'],
        'title'=>'AI Research Intern','company_type'=>'R&D Lab / University / FAANG',
        'salary'=>'Rs.25-60K/month','exp_needed'=>'Fresher',
        'desc'=>'Assist researchers in developing novel ML/DL models, writing papers, and running experiments.',
        'pct'=>$pct,'status'=>$pct>=75?'applicable':($pct>=50?'conditional':'not_applicable'),
        'checklist'=>$cl,'gaps'=>$gaps,
        'skills'=>['PyTorch','Research Writing','Math/Stats','Python','Literature Review']];
}

// 9. Full-Stack Developer
{
    $cl=[]; $gaps=[]; $w=0; $total=0;
    $total+=2; $w+=chk($dsa_score,           6.5, 'DSA Score >= 6.5/10',     $cl,$gaps,2);
    $total+=2; $w+=chk($python_score,        6.0, 'Coding >= 6/10',          $cl,$gaps,2);
    $total+=1; $w+=chk($cgpa,               6.5, 'CGPA >= 6.5',             $cl,$gaps,1);
    $total+=1; $w+=chk($github_projects,     3,   'GitHub Repos >= 3',       $cl,$gaps,1);
    $total+=1; $w+=chk($end_to_end_projects, 2,   'End-to-End Projects >= 2',$cl,$gaps,1);
    $total+=1; $w+=chk($internship_months,   1,   'Internship >= 1 month',   $cl,$gaps,1);
    $total+=1; $w+=chk($mock_interview_score,6.0, 'Mock Interview >= 6/10',  $cl,$gaps,1);
    $pct = round($w/$total*100);
    $posts[] = ['id'=>'fullstack','icon'=>'fa-solid fa-layer-group','color'=>['#4ade80','#22c55e'],
        'title'=>'Full-Stack Developer','company_type'=>'Startup / SaaS / Agency',
        'salary'=>'Rs.5-12 LPA','exp_needed'=>'0-2 yrs',
        'desc'=>'Build end-to-end web applications: React/Vue frontend + Node/Django/FastAPI backend.',
        'pct'=>$pct,'status'=>$pct>=75?'applicable':($pct>=50?'conditional':'not_applicable'),
        'checklist'=>$cl,'gaps'=>$gaps,
        'skills'=>['React / Vue','Node.js / Django','REST APIs','SQL','Git','CSS']];
}

// 10. Quantitative Analyst Trainee
{
    $cl=[]; $gaps=[]; $w=0; $total=0;
    $total+=2; $w+=chk($cgpa,               7.5, 'CGPA >= 7.5',              $cl,$gaps,2);
    $total+=2; $w+=chk($statistics_score,    7.5, 'Stats >= 7.5/10',          $cl,$gaps,2);
    $total+=2; $w+=chk($ml_score,            6.5, 'ML Score >= 6.5/10',       $cl,$gaps,2);
    $total+=1; $w+=chk($python_score,        6.5, 'Python >= 6.5/10',         $cl,$gaps,1);
    $total+=1; $w+=chk($sql_score,           6.0, 'SQL >= 6/10',              $cl,$gaps,1);
    $total+=1; $w+=chk($kaggle_competitions, 1,   'Kaggle Competitions >= 1', $cl,$gaps,1);
    $total+=1; $w+=chk($resume_score,        7.0, 'Resume Score >= 7/10',     $cl,$gaps,1);
    $pct = round($w/$total*100);
    $posts[] = ['id'=>'quant','icon'=>'fa-solid fa-square-root-variable','color'=>['#fbbf24','#f59e0b'],
        'title'=>'Quantitative Analyst Trainee','company_type'=>'Hedge Fund / BFSI / Fintech',
        'salary'=>'Rs.8-16 LPA','exp_needed'=>'0-1 yr',
        'desc'=>'Apply statistical and ML models to financial datasets for risk analysis and market predictions.',
        'pct'=>$pct,'status'=>$pct>=75?'applicable':($pct>=50?'conditional':'not_applicable'),
        'checklist'=>$cl,'gaps'=>$gaps,
        'skills'=>['Statistics','Python','R','Time Series','Financial Math','SQL']];
}

// 11. Deep Learning Engineer
{
    $cl=[]; $gaps=[]; $w=0; $total=0;
    $total+=2; $w+=chk($cgpa,               7.5, 'CGPA >= 7.5',               $cl,$gaps,2);
    $total+=2; $w+=chk($dl_score,            7.5, 'DL Score >= 7.5/10',        $cl,$gaps,2);
    $total+=2; $w+=chk($python_score,        7.5, 'Python >= 7.5/10',          $cl,$gaps,2);
    $total+=1; $w+=chk($ml_score,            7.0, 'ML Score >= 7/10',          $cl,$gaps,1);
    $total+=1; $w+=chk($github_projects,     3,   'GitHub Repos >= 3',         $cl,$gaps,1);
    $total+=1; $w+=chk($deployed_projects,   1,   'Deployed Models >= 1',      $cl,$gaps,1);
    $total+=1; $w+=chk($kaggle_competitions, 1,   'Kaggle >= 1 Competition',   $cl,$gaps,1);
    $pct = round($w/$total*100);
    $posts[] = ['id'=>'dl_eng','icon'=>'fa-solid fa-network-wired','color'=>['#f43f5e','#e11d48'],
        'title'=>'Deep Learning Engineer','company_type'=>'AI Lab / Vision / NLP Company',
        'salary'=>'Rs.9-16 LPA','exp_needed'=>'0-2 yrs',
        'desc'=>'Design and train deep neural networks (CNNs, Transformers, GANs) for vision, NLP, or speech tasks.',
        'pct'=>$pct,'status'=>$pct>=75?'applicable':($pct>=50?'conditional':'not_applicable'),
        'checklist'=>$cl,'gaps'=>$gaps,
        'skills'=>['PyTorch','TensorFlow','CNNs','Transformers','CUDA','Python']];
}

// 12. Prompt Engineer
{
    $cl=[]; $gaps=[]; $w=0; $total=0;
    $total+=2; $w+=chk($genai_score,         6.5, 'GenAI Score >= 6.5/10',    $cl,$gaps,2);
    $total+=2; $w+=chk($communication_score, 7.0, 'Communication >= 7/10',    $cl,$gaps,2);
    $total+=1; $w+=chk($python_score,        5.5, 'Python >= 5.5/10',         $cl,$gaps,1);
    $total+=1; $w+=chk($ml_score,            5.5, 'ML Basics >= 5.5/10',      $cl,$gaps,1);
    $total+=1; $w+=chk($cgpa,               6.0, 'CGPA >= 6.0',              $cl,$gaps,1);
    $total+=1; $w+=chk($github_projects,     2,   'GitHub Repos >= 2',        $cl,$gaps,1);
    $total+=1; $w+=chk($resume_score,        6.5, 'Resume Score >= 6.5/10',   $cl,$gaps,1);
    $pct = round($w/$total*100);
    $posts[] = ['id'=>'prompt_eng','icon'=>'fa-solid fa-comment-dots','color'=>['#c084fc','#a855f7'],
        'title'=>'Prompt Engineer','company_type'=>'AI SaaS / LLM Product Company',
        'salary'=>'Rs.5-12 LPA','exp_needed'=>'Fresher',
        'desc'=>'Design, test and optimise prompts for LLMs to maximise output quality for various use cases.',
        'pct'=>$pct,'status'=>$pct>=75?'applicable':($pct>=50?'conditional':'not_applicable'),
        'checklist'=>$cl,'gaps'=>$gaps,
        'skills'=>['GPT-4','Prompt Patterns','LangChain','Evaluation Metrics','Python']];
}

// 13. Data Engineer
{
    $cl=[]; $gaps=[]; $w=0; $total=0;
    $total+=2; $w+=chk($sql_score,           7.0, 'SQL >= 7/10',               $cl,$gaps,2);
    $total+=2; $w+=chk($python_score,        6.5, 'Python >= 6.5/10',          $cl,$gaps,2);
    $total+=1; $w+=chk($cgpa,               7.0, 'CGPA >= 7.0',               $cl,$gaps,1);
    $total+=1; $w+=chk($github_projects,     3,   'GitHub Repos >= 3',         $cl,$gaps,1);
    $total+=1; $w+=chk($end_to_end_projects, 1,   'End-to-End Projects >= 1',  $cl,$gaps,1);
    $total+=1; $w+=chk($certifications,      1,   'Certifications >= 1',       $cl,$gaps,1);
    $total+=1; $w+=chk($internship_months,   1,   'Internship >= 1 month',     $cl,$gaps,1);
    $pct = round($w/$total*100);
    $posts[] = ['id'=>'data_eng','icon'=>'fa-solid fa-database','color'=>['#2dd4bf','#14b8a6'],
        'title'=>'Data Engineer','company_type'=>'Big Data / Cloud / Analytics Platform',
        'salary'=>'Rs.6-12 LPA','exp_needed'=>'0-2 yrs',
        'desc'=>'Design and maintain ETL pipelines, data warehouses, and streaming architectures.',
        'pct'=>$pct,'status'=>$pct>=75?'applicable':($pct>=50?'conditional':'not_applicable'),
        'checklist'=>$cl,'gaps'=>$gaps,
        'skills'=>['Apache Spark','Kafka','Airflow','SQL','Python','AWS/GCP']];
}

// 14. Product Analyst
{
    $cl=[]; $gaps=[]; $w=0; $total=0;
    $total+=2; $w+=chk($communication_score,7.0, 'Communication >= 7/10',    $cl,$gaps,2);
    $total+=2; $w+=chk($sql_score,           6.0, 'SQL >= 6/10',              $cl,$gaps,2);
    $total+=1; $w+=chk($statistics_score,    6.0, 'Stats >= 6/10',            $cl,$gaps,1);
    $total+=1; $w+=chk($cgpa,               6.5, 'CGPA >= 6.5',              $cl,$gaps,1);
    $total+=1; $w+=chk($resume_score,        6.5, 'Resume >= 6.5/10',         $cl,$gaps,1);
    $total+=1; $w+=chk($internship_months,   1,   'Internship >= 1 month',    $cl,$gaps,1);
    $total+=1; $w+=chk($mock_interview_score,6.5, 'Mock Interview >= 6.5/10', $cl,$gaps,1);
    $pct = round($w/$total*100);
    $posts[] = ['id'=>'prod_analyst','icon'=>'fa-solid fa-magnifying-glass-chart','color'=>['#f97316','#ea580c'],
        'title'=>'Product Analyst','company_type'=>'SaaS / Consumer Tech / Startup',
        'salary'=>'Rs.5-10 LPA','exp_needed'=>'0-2 yrs',
        'desc'=>'Use data to drive product decisions: A/B testing, funnel analysis, and user behaviour studies.',
        'pct'=>$pct,'status'=>$pct>=75?'applicable':($pct>=50?'conditional':'not_applicable'),
        'checklist'=>$cl,'gaps'=>$gaps,
        'skills'=>['SQL','A/B Testing','Product Metrics','Mixpanel / GA4','Excel']];
}

// 15. ML Competition Specialist
{
    $cl=[]; $gaps=[]; $w=0; $total=0;
    $total+=2; $w+=chk($kaggle_competitions, 2,   'Kaggle Competitions >= 2',  $cl,$gaps,2);
    $total+=2; $w+=chk($ml_score,            8.0, 'ML Score >= 8/10',          $cl,$gaps,2);
    $total+=2; $w+=chk($python_score,        8.0, 'Python >= 8/10',            $cl,$gaps,2);
    $total+=1; $w+=chk($hackathons_attended, 2,   'Hackathons >= 2',           $cl,$gaps,1);
    $total+=1; $w+=chk($github_projects,     4,   'GitHub Repos >= 4',         $cl,$gaps,1);
    $total+=1; $w+=chk($statistics_score,    7.5, 'Stats >= 7.5/10',           $cl,$gaps,1);
    $bcr = ($best_competition_rank !== null) ? (int)$best_competition_rank : 99999;
    $total+=1; $w+=chk($bcr < 9999 ? 1 : 0, 1,   'Comp. Rank submitted',      $cl,$gaps,1);
    $pct = round($w/$total*100);
    $posts[] = ['id'=>'kaggle_ml','icon'=>'fa-solid fa-medal','color'=>['#facc15','#eab308'],
        'title'=>'ML Competition Specialist','company_type'=>'AI Research / Top Tech / Trading',
        'salary'=>'Rs.12-25 LPA','exp_needed'=>'Portfolio-based',
        'desc'=>'Showcase advanced ML skills via Kaggle rankings and hackathon wins to land high-impact ML roles.',
        'pct'=>$pct,'status'=>$pct>=75?'applicable':($pct>=50?'conditional':'not_applicable'),
        'checklist'=>$cl,'gaps'=>$gaps,
        'skills'=>['Feature Engineering','Ensembles','XGBoost','AutoML','Kaggle','Python']];
}

// ── Sort by match % descending ────────────────────────────────────────────────
usort($posts, fn($a,$b) => $b['pct'] - $a['pct']);

// ── Summary stats ─────────────────────────────────────────────────────────────
$appCount  = count(array_filter($posts, fn($p) => $p['status'] === 'applicable'));
$condCount = count(array_filter($posts, fn($p) => $p['status'] === 'conditional'));
$notCount  = count(array_filter($posts, fn($p) => $p['status'] === 'not_applicable'));
$topPost   = $posts[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Post Applicability | Hackathon Career Readiness</title>
    <meta name="description" content="Check which specific job posts you are eligible for based on your AI prediction and profile scores.">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg:     #07061a;
            --card:   rgba(255,255,255,0.04);
            --border: rgba(255,255,255,0.08);
            --indigo: #6366f1;
            --text:   #f1f5f9;
            --muted:  rgba(255,255,255,0.5);
        }
        body {
            font-family: 'Inter', sans-serif;
            background:
                radial-gradient(ellipse at 15% 0%,  #1e0a5e 0%, transparent 55%),
                radial-gradient(ellipse at 85% 100%, #0a2e1e 0%, transparent 55%),
                radial-gradient(ellipse at 50% 50%,  #0d0a2e 0%, #07061a 100%);
            min-height: 100vh;
            color: var(--text);
        }
        /* NAV */
        nav {
            display:flex; align-items:center; justify-content:space-between;
            padding:1rem 2.5rem;
            background:rgba(255,255,255,0.03);
            backdrop-filter:blur(24px);
            border-bottom:1px solid var(--border);
            position:sticky; top:0; z-index:100;
        }
        .nav-brand { display:flex; align-items:center; gap:.75rem; }
        .nav-brand img { height:36px; border-radius:6px; }
        .nav-brand span { font-weight:700; font-size:.95rem; }
        .nav-right { display:flex; align-items:center; gap:.65rem; flex-wrap:wrap; }
        .nav-pill {
            display:flex; align-items:center; gap:.4rem;
            border-radius:999px; padding:.4rem 1rem;
            font-size:.82rem; font-weight:500;
            text-decoration:none; transition:background .2s; white-space:nowrap;
        }
        .np-indigo  { background:rgba(99,102,241,.12); border:1px solid rgba(99,102,241,.25); color:#a5b4fc; }
        .np-indigo:hover  { background:rgba(99,102,241,.25); }
        .np-emerald { background:rgba(52,211,153,.12); border:1px solid rgba(52,211,153,.25); color:#6ee7b7; }
        .np-emerald:hover { background:rgba(52,211,153,.25); }
        .np-amber   { background:rgba(251,191,36,.12); border:1px solid rgba(251,191,36,.25); color:#fcd34d; }
        .np-amber:hover   { background:rgba(251,191,36,.25); }
        .np-red     { background:rgba(248,113,113,.12); border:1px solid rgba(248,113,113,.25); color:#f87171; }
        .np-red:hover     { background:rgba(248,113,113,.22); }
        .user-pill {
            display:flex; align-items:center; gap:.4rem;
            background:rgba(99,102,241,.15); border:1px solid rgba(99,102,241,.3);
            border-radius:999px; padding:.4rem 1rem;
            font-size:.82rem; font-weight:500;
        }
        /* PAGE */
        .page { max-width:1140px; margin:0 auto; padding:2rem 1.5rem 5rem; }
        /* BREADCRUMB */
        .breadcrumb { display:flex; align-items:center; gap:.5rem; margin-bottom:1.75rem; flex-wrap:wrap; }
        .bc-link {
            display:inline-flex; align-items:center; gap:.4rem;
            background:rgba(99,102,241,.1); border:1px solid rgba(99,102,241,.25);
            border-radius:999px; padding:.32rem .95rem;
            font-size:.8rem; font-weight:600; color:#a5b4fc;
            text-decoration:none; transition:background .2s;
        }
        .bc-link:hover { background:rgba(99,102,241,.22); }
        .bc-sep     { color:rgba(255,255,255,.2); font-size:.8rem; }
        .bc-current { font-size:.8rem; color:var(--muted); font-weight:600; }
        /* PAGE HEADER */
        .page-header { text-align:center; padding:2rem 1rem 2.25rem; }
        .page-badge {
            display:inline-flex; align-items:center; gap:.5rem;
            background:rgba(99,102,241,.15); border:1px solid rgba(99,102,241,.3);
            border-radius:999px; padding:.35rem 1.1rem;
            font-size:.78rem; color:#a5b4fc; margin-bottom:1.2rem;
            text-transform:uppercase; letter-spacing:.07em; font-weight:600;
        }
        .page-header h1 {
            font-size:clamp(1.7rem,4vw,2.8rem); font-weight:900; line-height:1.2; margin-bottom:.75rem;
        }
        .page-header h1 span {
            background:linear-gradient(135deg,#6366f1,#8b5cf6,#ec4899);
            -webkit-background-clip:text; -webkit-text-fill-color:transparent;
        }
        .page-header p { color:var(--muted); max-width:580px; margin:0 auto; font-size:.95rem; line-height:1.65; }
        /* AI BANNER */
        .ai-banner {
            display:flex; align-items:center; gap:1rem; flex-wrap:wrap;
            background:rgba(99,102,241,.08); border:1px solid rgba(99,102,241,.2);
            border-radius:1rem; padding:1rem 1.5rem; margin-bottom:2rem;
        }
        .ai-banner-icon { font-size:1.5rem; color:#818cf8; flex-shrink:0; }
        .ai-banner-text h4 { font-size:.95rem; font-weight:700; margin-bottom:.2rem; }
        .ai-banner-text p  { font-size:.82rem; color:var(--muted); }
        .ai-status-badge {
            margin-left:auto; flex-shrink:0;
            display:flex; align-items:center; gap:.4rem;
            border-radius:999px; padding:.35rem .9rem; font-size:.8rem; font-weight:700;
        }
        .asb-green { background:rgba(74,222,128,.15); border:1px solid rgba(74,222,128,.3); color:#4ade80; }
        .asb-red   { background:rgba(248,113,113,.15); border:1px solid rgba(248,113,113,.3); color:#f87171; }
        .asb-muted { background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.15); color:var(--muted); }
        /* SUMMARY GRID */
        .summary-grid {
            display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr));
            gap:1rem; margin-bottom:2rem;
        }
        .sum-card {
            background:var(--card); border:1px solid var(--border);
            border-radius:1.2rem; padding:1.3rem 1.1rem;
            text-align:center; transition:transform .2s;
            position:relative; overflow:hidden;
        }
        .sum-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; }
        .sum-card:hover { transform:translateY(-3px); }
        .sc-green::before  { background:linear-gradient(90deg,#4ade80,#10b981); }
        .sc-amber::before  { background:linear-gradient(90deg,#fbbf24,#f59e0b); }
        .sc-rose::before   { background:linear-gradient(90deg,#f87171,#ef4444); }
        .sc-indigo::before { background:linear-gradient(90deg,#818cf8,#6366f1); }
        .sum-emoji { font-size:1.6rem; margin-bottom:.5rem; }
        .sum-num   { font-size:2rem; font-weight:900; line-height:1; margin-bottom:.2rem; }
        .sc-green  .sum-num { color:#4ade80; }
        .sc-amber  .sum-num { color:#fbbf24; }
        .sc-rose   .sum-num { color:#f87171; }
        .sc-indigo .sum-num { color:#818cf8; }
        .sum-label { font-size:.76rem; color:var(--muted); font-weight:600; text-transform:uppercase; letter-spacing:.05em; }
        .sum-sub   { font-size:.78rem; color:var(--muted); margin-top:.2rem; }
        /* CONTROLS BAR */
        .controls-bar { display:flex; gap:.75rem; flex-wrap:wrap; align-items:center; margin-bottom:1.75rem; }
        .search-wrap { position:relative; flex:1; min-width:220px; }
        .search-wrap i { position:absolute; left:.9rem; top:50%; transform:translateY(-50%); color:var(--muted); font-size:.85rem; pointer-events:none; }
        #postSearch {
            width:100%; background:var(--card); border:1px solid var(--border);
            border-radius:999px; padding:.5rem .9rem .5rem 2.4rem;
            color:var(--text); font-size:.85rem; font-family:inherit; outline:none; transition:border .2s;
        }
        #postSearch:focus { border-color:rgba(99,102,241,.5); }
        #postSearch::placeholder { color:var(--muted); }
        .filter-btn {
            display:flex; align-items:center; gap:.4rem;
            border-radius:999px; padding:.45rem 1.1rem;
            font-size:.82rem; font-weight:600; cursor:pointer;
            border:1px solid var(--border); background:var(--card);
            color:var(--muted); transition:all .2s;
        }
        .filter-btn:hover, .filter-btn.active {
            background:rgba(99,102,241,.15); border-color:rgba(99,102,241,.4); color:#a5b4fc;
        }
        .dot       { width:8px; height:8px; border-radius:50%; }
        .dot-all   { background:#818cf8; }
        .dot-green { background:#4ade80; }
        .dot-amber { background:#fbbf24; }
        .dot-red   { background:#f87171; }
        /* POSTS GRID */
        .posts-grid {
            display:grid;
            grid-template-columns:repeat(auto-fill,minmax(500px,1fr));
            gap:1.5rem;
        }
        @media(max-width:560px){ .posts-grid { grid-template-columns:1fr; } }
        /* POST CARD */
        .post-card {
            background:var(--card); border:1px solid var(--border);
            border-radius:1.4rem; overflow:hidden;
            transition:transform .25s, box-shadow .25s;
            display:flex; flex-direction:column;
        }
        .post-card:hover { transform:translateY(-5px); box-shadow:0 20px 50px rgba(0,0,0,.4); }
        .post-card.hidden { display:none; }
        .post-card-accent { height:3px; width:100%; }
        .post-card-top {
            padding:1.3rem 1.4rem 1rem;
            display:flex; align-items:flex-start; gap:1rem;
            border-bottom:1px solid var(--border);
        }
        .post-icon {
            width:50px; height:50px; border-radius:.9rem;
            display:flex; align-items:center; justify-content:center;
            font-size:1.3rem; flex-shrink:0;
        }
        .post-meta { flex:1; min-width:0; }
        .post-title   { font-size:.98rem; font-weight:700; margin-bottom:.15rem; }
        .post-company { font-size:.78rem; color:var(--muted); }
        .post-tags    { display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.4rem; }
        .tag { display:inline-flex; align-items:center; gap:.25rem; border-radius:999px; padding:.18rem .65rem; font-size:.72rem; font-weight:600; }
        .tag-salary { background:rgba(74,222,128,.1);  color:#4ade80;  border:1px solid rgba(74,222,128,.25); }
        .tag-exp    { background:rgba(251,191,36,.1);  color:#fbbf24;  border:1px solid rgba(251,191,36,.25); }
        .post-badge {
            flex-shrink:0; display:flex; align-items:center; gap:.3rem;
            border-radius:999px; padding:.32rem .85rem;
            font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em;
        }
        .badge-app  { background:rgba(74,222,128,.15); border:1px solid rgba(74,222,128,.3); color:#4ade80; }
        .badge-cond { background:rgba(251,191,36,.15);  border:1px solid rgba(251,191,36,.3);  color:#fbbf24; }
        .badge-not  { background:rgba(248,113,113,.12); border:1px solid rgba(248,113,113,.3); color:#f87171; }
        /* CARD BODY */
        .post-card-body { padding:1.1rem 1.4rem; flex:1; }
        .post-desc { font-size:.82rem; color:var(--muted); line-height:1.55; margin-bottom:1rem; }
        .match-row { display:flex; align-items:center; justify-content:space-between; margin-bottom:.4rem; }
        .match-label { font-size:.76rem; color:var(--muted); font-weight:600; }
        .match-pct   { font-size:.88rem; font-weight:800; }
        .bar-bg   { height:7px; border-radius:4px; background:rgba(255,255,255,.07); overflow:hidden; margin-bottom:1rem; }
        .bar-fill { height:100%; border-radius:4px; width:0; transition:width 1.4s cubic-bezier(.4,0,.2,1); }
        .cl-title {
            display:flex; align-items:center; gap:.4rem;
            font-size:.76rem; font-weight:700; text-transform:uppercase;
            letter-spacing:.06em; color:var(--muted); margin-bottom:.55rem;
        }
        .cl-grid { display:grid; grid-template-columns:1fr 1fr; gap:.3rem .75rem; margin-bottom:.9rem; }
        .cl-item { display:flex; align-items:center; gap:.4rem; }
        .cl-dot {
            width:16px; height:16px; border-radius:50%;
            display:flex; align-items:center; justify-content:center;
            font-size:.6rem; flex-shrink:0;
        }
        .cl-pass { background:rgba(74,222,128,.2);  color:#4ade80; }
        .cl-fail { background:rgba(248,113,113,.2); color:#f87171; }
        .cl-text { font-size:.76rem; color:var(--muted); }
        .cl-text.fail-text { color:#f87171; }
        .skills-title { font-size:.76rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); margin-bottom:.45rem; }
        .skills-list  { display:flex; gap:.35rem; flex-wrap:wrap; margin-bottom:.9rem; }
        .skill-chip {
            background:rgba(255,255,255,.05); border:1px solid var(--border);
            border-radius:999px; padding:.2rem .65rem;
            font-size:.73rem; color:var(--muted); font-weight:500;
        }
        .gaps-section { margin-bottom:.75rem; }
        .gaps-title {
            display:flex; align-items:center; gap:.35rem;
            font-size:.76rem; font-weight:700; text-transform:uppercase;
            letter-spacing:.06em; color:#fbbf24; margin-bottom:.4rem;
        }
        .gap-item {
            display:flex; align-items:flex-start; gap:.4rem;
            padding:.3rem 0; font-size:.76rem; color:rgba(255,255,255,.55);
            border-bottom:1px solid rgba(255,255,255,.04);
        }
        .gap-item i { color:#fb923c; margin-top:.1rem; flex-shrink:0; }
        /* CARD FOOTER */
        .post-card-footer {
            padding:.9rem 1.4rem 1.1rem;
            border-top:1px solid var(--border);
            display:flex; gap:.6rem; flex-wrap:wrap;
        }
        .apply-btn {
            display:inline-flex; align-items:center; gap:.4rem;
            border-radius:.7rem; padding:.55rem 1.2rem;
            font-size:.82rem; font-weight:700; cursor:pointer;
            border:none; font-family:inherit; text-decoration:none; transition:all .2s;
        }
        .ab-green   { background:linear-gradient(135deg,#10b981,#4ade80); color:#022c22; }
        .ab-green:hover { transform:translateY(-2px); box-shadow:0 8px 22px rgba(52,211,153,.35); }
        .ab-amber   { background:linear-gradient(135deg,#f59e0b,#fbbf24); color:#1c1000; }
        .ab-amber:hover { transform:translateY(-2px); box-shadow:0 8px 22px rgba(251,191,36,.3); }
        .ab-outline { background:rgba(255,255,255,.06); border:1px solid var(--border); color:var(--muted); }
        .ab-outline:hover { background:rgba(255,255,255,.12); }
        .ab-disabled { color:var(--muted); font-size:.82rem; display:flex; align-items:center; gap:.4rem; padding:.55rem 0; }
        /* ACTIONS ROW */
        .actions-row {
            display:flex; gap:1rem; flex-wrap:wrap; justify-content:center; margin-top:2.5rem;
        }
        .btn-action {
            display:inline-flex; align-items:center; gap:.5rem;
            border-radius:.85rem; padding:.8rem 1.75rem;
            font-size:.9rem; font-weight:700; cursor:pointer;
            border:none; font-family:inherit; text-decoration:none; transition:all .2s;
        }
        .ba-primary { background:linear-gradient(135deg,#6366f1,#8b5cf6); color:white; box-shadow:0 0 24px rgba(99,102,241,.4); }
        .ba-primary:hover { transform:translateY(-2px); box-shadow:0 0 36px rgba(99,102,241,.6); }
        .ba-emerald { background:linear-gradient(135deg,#10b981,#4ade80); color:#022c22; box-shadow:0 0 20px rgba(52,211,153,.3); }
        .ba-emerald:hover { transform:translateY(-2px); box-shadow:0 0 32px rgba(52,211,153,.5); }
        .ba-amber   { background:linear-gradient(135deg,#f59e0b,#d97706); color:#0a0818; }
        .ba-amber:hover { transform:translateY(-2px); }
        .ba-outline { background:rgba(255,255,255,.06); border:1px solid var(--border); color:var(--text); }
        .ba-outline:hover { background:rgba(255,255,255,.12); }
        /* MODAL */
        .modal-overlay {
            position:fixed; inset:0; background:rgba(0,0,0,.7);
            backdrop-filter:blur(8px); display:flex;
            align-items:center; justify-content:center;
            z-index:999; opacity:0; pointer-events:none; transition:opacity .3s;
        }
        .modal-overlay.open { opacity:1; pointer-events:all; }
        .modal {
            background:#12102b; border:1px solid rgba(99,102,241,.3);
            border-radius:1.5rem; padding:2rem; max-width:480px; width:90%;
            transform:scale(.95); transition:transform .3s;
        }
        .modal-overlay.open .modal { transform:scale(1); }
        .modal-title { font-size:1.1rem; font-weight:800; margin-bottom:.4rem; }
        .modal-sub   { font-size:.85rem; color:var(--muted); margin-bottom:1.2rem; }
        .modal-body-content { margin-bottom:1.5rem; }
        .modal-btns  { display:flex; gap:.75rem; }
        .modal-confirm {
            flex:1; display:flex; align-items:center; justify-content:center; gap:.4rem;
            background:linear-gradient(135deg,#6366f1,#8b5cf6); color:white;
            border:none; border-radius:.75rem; padding:.75rem 1.5rem;
            font-size:.9rem; font-weight:700; cursor:pointer; font-family:inherit; transition:all .2s;
        }
        .modal-confirm:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(99,102,241,.4); }
        .modal-cancel {
            background:rgba(255,255,255,.07); border:1px solid var(--border); color:var(--muted);
            border-radius:.75rem; padding:.75rem 1.25rem;
            font-size:.88rem; font-weight:600; cursor:pointer; font-family:inherit; transition:background .2s;
        }
        .modal-cancel:hover { background:rgba(255,255,255,.12); }
        .modal-success { text-align:center; padding:.5rem 0; }
        .modal-success i { font-size:2.5rem; color:#4ade80; margin-bottom:.75rem; }
        .modal-success h3 { font-size:1.1rem; margin-bottom:.5rem; }
        .modal-success p  { font-size:.85rem; color:var(--muted); line-height:1.6; }
        /* FOOTER */
        footer {
            text-align:center; padding:2rem;
            border-top:1px solid var(--border); color:var(--muted); font-size:.85rem;
        }
        footer strong { color:var(--text); }
        .no-results { grid-column:1/-1; text-align:center; padding:3rem; color:var(--muted); }
        .no-results i { font-size:2.5rem; margin-bottom:.75rem; display:block; color:rgba(255,255,255,.2); }
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
        <a href="predict.php"           class="nav-pill np-indigo"  id="aiPredictorNav">
            <i class="fa-solid fa-brain"></i> AI Predictor
        </a>
        <a href="job_applicability.php"  class="nav-pill np-emerald" id="deptNav">
            <i class="fa-solid fa-building"></i> Dept. Check
        </a>
        <a href="student_details.php"   class="nav-pill np-amber"   id="myProfileNav">
            <i class="fa-solid fa-id-card"></i> My Profile
        </a>
        <a href="dashboard.php"         class="nav-pill np-indigo"  id="dashboardNav">
            <i class="fa-solid fa-gauge"></i> Dashboard
        </a>
        <div class="user-pill"><i class="fa-solid fa-circle-user"></i> <?= $userName ?></div>
        <a href="logout.php"            class="nav-pill np-red"     id="logoutNav">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </div>
</nav>

<!-- PAGE -->
<div class="page">

    <!-- BREADCRUMB -->
    <div class="breadcrumb">
        <a href="predict.php" class="bc-link" id="backToPredictBc">
            <i class="fa-solid fa-arrow-left"></i> My AI Prediction
        </a>
        <span class="bc-sep">/</span>
        <a href="job_applicability.php" class="bc-link" id="backToDeptBc">
            <i class="fa-solid fa-building"></i> Dept. Applicability
        </a>
        <span class="bc-sep">/</span>
        <span class="bc-current"><i class="fa-solid fa-file-contract" style="margin-right:.3rem"></i>Job Post Applicability</span>
    </div>

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div class="page-badge">
            <i class="fa-solid fa-file-contract"></i> Post Eligibility Engine
        </div>
        <h1>Are You Eligible for <span>This Post?</span></h1>
        <p>
            We cross-match your profile scores against <strong><?= count($posts) ?> specific job posts</strong>
            across Data, AI, Engineering and Analytics — giving you an exact match score, eligibility checklist,
            required skills, and gaps to close for each role.
        </p>
    </div>

    <!-- AI READINESS BANNER -->
    <div class="ai-banner" id="aiReadinessBanner">
        <div class="ai-banner-icon"><i class="fa-solid fa-microchip"></i></div>
        <div class="ai-banner-text">
            <h4>Based on Your AI Prediction</h4>
            <p>
                Career Track: <strong style="color:#c7d2fe"><?= htmlspecialchars($careerTrackLabel) ?></strong>
                &nbsp;&middot;&nbsp;
                <?php if ($jobReady !== null): ?>
                    Job Readiness: <strong style="color:#c7d2fe"><?= number_format($jobReadyPct,1) ?>%</strong>
                <?php else: ?>
                    <em>No prediction yet &mdash; <a href="predict.php" style="color:#818cf8">run the AI predictor first</a>.</em>
                <?php endif; ?>
            </p>
        </div>
        <?php if ($jobReady === true): ?>
            <span class="ai-status-badge asb-green"><i class="fa-solid fa-circle-check"></i> Job Ready</span>
        <?php elseif ($jobReady === false): ?>
            <span class="ai-status-badge asb-red"><i class="fa-solid fa-circle-xmark"></i> Not Yet Ready</span>
        <?php else: ?>
            <span class="ai-status-badge asb-muted"><i class="fa-solid fa-circle-question"></i> Not Predicted</span>
        <?php endif; ?>
    </div>

    <!-- SUMMARY CARDS -->
    <div class="summary-grid" id="summaryCards">
        <div class="sum-card sc-green">
            <div class="sum-emoji">&#9989;</div>
            <div class="sum-num"><?= $appCount ?></div>
            <div class="sum-label">Applicable</div>
            <div class="sum-sub">Posts you fully qualify for</div>
        </div>
        <div class="sum-card sc-amber">
            <div class="sum-emoji">&#9889;</div>
            <div class="sum-num"><?= $condCount ?></div>
            <div class="sum-label">Conditional</div>
            <div class="sum-sub">Close &mdash; a few gaps to close</div>
        </div>
        <div class="sum-card sc-rose">
            <div class="sum-emoji">&#127919;</div>
            <div class="sum-num"><?= $notCount ?></div>
            <div class="sum-label">Not Eligible Yet</div>
            <div class="sum-sub">Needs upskilling</div>
        </div>
        <div class="sum-card sc-indigo">
            <div class="sum-emoji">&#127942;</div>
            <div class="sum-num" style="font-size:1.05rem;margin-bottom:.4rem"><?= htmlspecialchars($topPost['title']) ?></div>
            <div class="sum-label">Best Matching Post</div>
            <div class="sum-sub"><?= $topPost['pct'] ?>% match score</div>
        </div>
    </div>

    <!-- SEARCH + FILTER BAR -->
    <div class="controls-bar" id="controlsBar">
        <div class="search-wrap">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="postSearch" placeholder="Search job posts e.g. ML Engineer, Analyst..." oninput="applyFilters()">
        </div>
        <button class="filter-btn active" id="filterAll"         onclick="setFilter('all')">          <span class="dot dot-all"></span>   All Posts</button>
        <button class="filter-btn"        id="filterApplicable"  onclick="setFilter('applicable')">   <span class="dot dot-green"></span> Applicable</button>
        <button class="filter-btn"        id="filterConditional" onclick="setFilter('conditional')">  <span class="dot dot-amber"></span> Conditional</button>
        <button class="filter-btn"        id="filterNot"         onclick="setFilter('not_applicable')"><span class="dot dot-red"></span>  Not Eligible</button>
    </div>

    <!-- POSTS GRID -->
    <div class="posts-grid" id="postsGrid">

    <?php foreach ($posts as $post):
        $statusClass = $post['status'] === 'applicable' ? 'badge-app'
                     : ($post['status'] === 'conditional' ? 'badge-cond' : 'badge-not');
        $statusLabel = $post['status'] === 'applicable' ? '&#10003; Applicable'
                     : ($post['status'] === 'conditional' ? '&#9889; Conditional' : '&#10007; Not Eligible');
        $matchColor  = $post['status'] === 'applicable' ? '#4ade80'
                     : ($post['status'] === 'conditional' ? '#fbbf24' : '#f87171');
        $gradA = $post['color'][0]; $gradB = $post['color'][1];
        $rgb   = sscanf($gradB, '#%02x%02x%02x');
        $iconBg= "rgba({$rgb[0]},{$rgb[1]},{$rgb[2]},0.18)";
    ?>
    <div class="post-card" data-status="<?= $post['status'] ?>" data-title="<?= strtolower(htmlspecialchars($post['title'])) ?>" id="card-<?= $post['id'] ?>">

        <!-- accent strip -->
        <div class="post-card-accent" style="background:linear-gradient(90deg,<?= $gradA ?>,<?= $gradB ?>)"></div>

        <!-- TOP -->
        <div class="post-card-top">
            <div class="post-icon" style="background:<?= $iconBg ?>;color:<?= $gradA ?>">
                <i class="<?= $post['icon'] ?>"></i>
            </div>
            <div class="post-meta">
                <div class="post-title"><?= htmlspecialchars($post['title']) ?></div>
                <div class="post-company">
                    <i class="fa-solid fa-building" style="margin-right:.3rem;font-size:.7rem"></i>
                    <?= htmlspecialchars($post['company_type']) ?>
                </div>
                <div class="post-tags">
                    <span class="tag tag-salary"><i class="fa-solid fa-coins"></i> <?= htmlspecialchars($post['salary']) ?></span>
                    <span class="tag tag-exp"><i class="fa-solid fa-clock"></i> <?= htmlspecialchars($post['exp_needed']) ?></span>
                </div>
            </div>
            <div class="post-badge <?= $statusClass ?>"><?= $statusLabel ?></div>
        </div>

        <!-- BODY -->
        <div class="post-card-body">
            <div class="post-desc"><?= htmlspecialchars($post['desc']) ?></div>

            <!-- match bar -->
            <div class="match-row">
                <span class="match-label">Profile Match</span>
                <span class="match-pct" style="color:<?= $matchColor ?>"><?= $post['pct'] ?>%</span>
            </div>
            <div class="bar-bg">
                <div class="bar-fill" data-pct="<?= $post['pct'] ?>"
                     style="background:linear-gradient(90deg,<?= $gradB ?>,<?= $gradA ?>)"></div>
            </div>

            <!-- eligibility checklist -->
            <div class="cl-title"><i class="fa-solid fa-list-check"></i> Eligibility Criteria</div>
            <div class="cl-grid">
                <?php foreach ($post['checklist'] as $item): ?>
                <div class="cl-item">
                    <div class="cl-dot <?= $item['pass'] ? 'cl-pass' : 'cl-fail' ?>">
                        <i class="fa-solid <?= $item['pass'] ? 'fa-check' : 'fa-xmark' ?>"></i>
                    </div>
                    <span class="cl-text <?= $item['pass'] ? '' : 'fail-text' ?>"><?= htmlspecialchars($item['label']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- required skills -->
            <div class="skills-title"><i class="fa-solid fa-toolbox" style="margin-right:.3rem"></i>Skills Required</div>
            <div class="skills-list">
                <?php foreach ($post['skills'] as $s): ?>
                <span class="skill-chip"><?= htmlspecialchars($s) ?></span>
                <?php endforeach; ?>
            </div>

            <!-- gaps to improve -->
            <?php if (!empty($post['gaps'])): ?>
            <div class="gaps-section">
                <div class="gaps-title"><i class="fa-solid fa-triangle-exclamation"></i> What to Improve</div>
                <?php foreach ($post['gaps'] as $g): ?>
                <div class="gap-item">
                    <i class="fa-solid fa-arrow-trend-up"></i>
                    <span><?= htmlspecialchars($g) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- FOOTER BUTTONS -->
        <div class="post-card-footer">
            <?php if ($post['status'] === 'applicable'): ?>
                <button class="apply-btn ab-green" id="apply-<?= $post['id'] ?>"
                        onclick="openModal('<?= htmlspecialchars(addslashes($post['title'])) ?>', '<?= htmlspecialchars(addslashes($post['company_type'])) ?>')">
                    <i class="fa-solid fa-paper-plane"></i> Express Interest
                </button>
            <?php elseif ($post['status'] === 'conditional'): ?>
                <button class="apply-btn ab-amber" id="apply-<?= $post['id'] ?>"
                        onclick="openModal('<?= htmlspecialchars(addslashes($post['title'])) ?>', '<?= htmlspecialchars(addslashes($post['company_type'])) ?>')">
                    <i class="fa-solid fa-paper-plane"></i> Apply (Conditional)
                </button>
            <?php else: ?>
                <span class="ab-disabled"><i class="fa-solid fa-lock"></i> Not Yet Eligible</span>
            <?php endif; ?>
            <a href="student_details.php" class="apply-btn ab-outline" id="improve-<?= $post['id'] ?>">
                <i class="fa-solid fa-pen-to-square"></i> Improve Profile
            </a>
        </div>

    </div><!-- /post-card -->
    <?php endforeach; ?>

        <div class="no-results" id="noResults" style="display:none">
            <i class="fa-solid fa-magnifying-glass"></i>
            <p>No posts match your search or filter.</p>
        </div>
    </div><!-- /posts-grid -->

    <!-- ACTION BUTTONS -->
    <div class="actions-row">
        <a href="predict.php"           class="btn-action ba-primary"  id="viewPredictionBtn">
            <i class="fa-solid fa-brain"></i> View My AI Prediction
        </a>
        <a href="job_applicability.php"  class="btn-action ba-emerald"  id="deptApplyBtn">
            <i class="fa-solid fa-building"></i> Department Applicability
        </a>
        <a href="student_details.php"   class="btn-action ba-amber"    id="updateProfileBtn">
            <i class="fa-solid fa-pen-to-square"></i> Update My Profile
        </a>
        <a href="dashboard.php"         class="btn-action ba-outline"  id="backDashBtn">
            <i class="fa-solid fa-gauge"></i> Dashboard
        </a>
    </div>

</div><!-- /page -->

<!-- APPLY MODAL -->
<div class="modal-overlay" id="applyModal">
    <div class="modal">
        <div id="modalContent">
            <div class="modal-title" id="modalTitle">Express Interest</div>
            <div class="modal-sub"   id="modalSub">You are about to express interest in:</div>
            <div class="modal-body-content" id="modalBodyContent"></div>
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
    <i class="fa-solid fa-file-contract"></i>&nbsp;
    <strong>Hackathon Success &amp; AI Career Readiness Prediction System</strong>
    &nbsp;|&nbsp; Built by Shilpi, Priyanshu, Dushyant &amp; Vedika &middot; B.Tech CSE Group Project
</footer>

<script>
// ── Filter & Search ──────────────────────────────────────────────────────────
let currentFilter = 'all';

function setFilter(f) {
    currentFilter = f;
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    const map = { all:'filterAll', applicable:'filterApplicable', conditional:'filterConditional', not_applicable:'filterNot' };
    document.getElementById(map[f]).classList.add('active');
    applyFilters();
}

function applyFilters() {
    const q = document.getElementById('postSearch').value.toLowerCase().trim();
    let anyVisible = false;
    document.querySelectorAll('.post-card').forEach(card => {
        const statusOk = currentFilter === 'all' || card.dataset.status === currentFilter;
        const titleOk  = !q || card.dataset.title.includes(q);
        const show = statusOk && titleOk;
        card.classList.toggle('hidden', !show);
        if (show) anyVisible = true;
    });
    document.getElementById('noResults').style.display = anyVisible ? 'none' : 'block';
}

// ── Animate bars on load ─────────────────────────────────────────────────────
window.addEventListener('load', () => {
    document.querySelectorAll('.bar-fill').forEach(bar => {
        const pct = parseFloat(bar.dataset.pct) || 0;
        setTimeout(() => { bar.style.width = pct + '%'; }, 150);
    });
});

// ── Apply Modal ──────────────────────────────────────────────────────────────
let _post = '', _company = '';

function openModal(post, company) {
    _post = post; _company = company;
    document.getElementById('modalTitle').textContent = 'Express Interest';
    document.getElementById('modalSub').textContent   = 'You are about to express interest in:';
    document.getElementById('modalBodyContent').innerHTML = `
        <div style="background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.3);border-radius:.85rem;padding:1rem 1.25rem;margin-bottom:1rem">
            <div style="font-size:.95rem;font-weight:700;margin-bottom:.25rem;color:#c7d2fe">${post}</div>
            <div style="font-size:.8rem;color:var(--muted)"><i class="fa-solid fa-building" style="margin-right:.3rem"></i>${company}</div>
        </div>`;
    document.getElementById('modalConfirmBtn').style.display = 'flex';
    document.getElementById('applyModal').classList.add('open');
}

function closeModal() {
    document.getElementById('applyModal').classList.remove('open');
}

function confirmApply() {
    document.getElementById('modalContent').innerHTML = `
        <div class="modal-success">
            <i class="fa-solid fa-circle-check"></i>
            <h3>Interest Registered! &#127881;</h3>
            <p>You have expressed interest in <strong style="color:#c7d2fe">${_post}</strong>.</p>
            <p style="margin-top:.75rem">Keep improving your profile to strengthen your application!</p>
            <button class="modal-confirm" style="margin-top:1.25rem;width:100%;max-width:220px;" onclick="closeModal()">
                <i class="fa-solid fa-thumbs-up"></i> Awesome!
            </button>
        </div>`;
}

// Close on backdrop click
document.getElementById('applyModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>
</body>
</html>
