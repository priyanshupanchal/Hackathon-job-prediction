<?php
session_start();
session_unset();
session_destroy();
header("Location: http://localhost/hackathon-employability-ml/index.html");
exit();
?>
