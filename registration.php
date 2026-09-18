<?php
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: http://localhost/hackathon-employability-ml/registration.html");
    exit();
}

$con = mysqli_connect("localhost", "root", "", "hackathon-employability-ml");

if (!$con) {
    die("Connection failed: " . mysqli_connect_error());
}

$FirstName   = trim($_POST['FirstName']   ?? '');
$LastName    = trim($_POST['LastName']    ?? '');
$Email       = trim($_POST['Email']       ?? '');
$Mobile      = trim($_POST['Mobile']      ?? '');
$Gender      = trim($_POST['Gender']      ?? '');
$DateOfBirth = trim($_POST['DateOfBirth'] ?? '');
$Address     = trim($_POST['Address']     ?? '');
$City        = trim($_POST['City']        ?? '');
$Areapin     = trim($_POST['Areapin']     ?? '');
$RawPassword = $_POST['Password']         ?? '';

// Basic server-side validation
$errors = [];
if (empty($FirstName))   $errors[] = "First name is required.";
if (empty($LastName))    $errors[] = "Last name is required.";
if (!filter_var($Email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email address.";
if (!preg_match('/^[6-9]\d{9}$/', $Mobile))    $errors[] = "Invalid mobile number.";
if (empty($Gender))      $errors[] = "Gender is required.";
if (empty($DateOfBirth)) $errors[] = "Date of birth is required.";
if (empty($Address))     $errors[] = "Address is required.";
if (empty($City))        $errors[] = "City is required.";
if (!preg_match('/^\d{6}$/', $Areapin)) $errors[] = "Invalid PIN code.";
if (strlen($RawPassword) < 8) $errors[] = "Password must be at least 8 characters.";

if (!empty($errors)) {
    $msg = urlencode(implode(' | ', $errors));
    header("Location: http://localhost/hackathon-employability-ml/registration.html?error=" . $msg);
    exit();
}

// Hash password securely
$Password = password_hash($RawPassword, PASSWORD_DEFAULT);

// Check if email already registered
$check = $con->prepare("SELECT id FROM registration WHERE Email = ?");
$check->bind_param("s", $Email);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    $check->close();
    $con->close();
    header("Location: http://localhost/hackathon-employability-ml/registration.html?error=" . urlencode("This email is already registered. Please login."));
    exit();
}
$check->close();

// Insert new record
$sql = "INSERT INTO `registration`
        (`FirstName`, `LastName`, `Email`, `Mobile`, `Gender`, `DateOfBirth`, `Address`, `City`, `Areapin`, `Password`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $con->prepare($sql);
$stmt->bind_param(
    "ssssssssss",
    $FirstName, $LastName, $Email, $Mobile, $Gender,
    $DateOfBirth, $Address, $City, $Areapin, $Password
);

if ($stmt->execute()) {
    $stmt->close();
    $con->close();
    header('Location: http://localhost/hackathon-employability-ml/index.html?registered=1');
    exit();
} else {
    $error_message = urlencode("Registration failed: " . $stmt->error);
    $stmt->close();
    $con->close();
    header("Location: http://localhost/hackathon-employability-ml/registration.html?error=" . $error_message);
    exit();
}
?>