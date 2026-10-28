<?php
session_start();

if (isset($_SESSION['hospital_id'])) {
    header("Location: hospital_dashboard.php");
    exit;
}

header("Location: hospitals_login.php");
exit;
?>