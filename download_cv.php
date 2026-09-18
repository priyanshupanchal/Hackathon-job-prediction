<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: http://localhost/hackathon-employability-ml/index.html");
    exit();
}

$userId = (int) $_SESSION['user_id'];

$con = mysqli_connect("localhost", "root", "", "hackathon-employability-ml");
if (!$con) die("DB error");

$stmt = $con->prepare("SELECT cv_filename FROM student_details WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($cvFilename);
$stmt->fetch();
$stmt->close();
$con->close();

if (!$cvFilename) {
    die("No CV on file.");
}

$path = __DIR__ . "/uploads/cv/" . $cvFilename;
if (!file_exists($path)) {
    die("CV file not found.");
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($cvFilename) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit();
?>
