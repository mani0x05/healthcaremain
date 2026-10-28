
<?php
session_start();
require_once "db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    $sql = "SELECT id, hospital_name, username, password
            FROM hospitals
            WHERE username = ? LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();
    $hospital = $result->fetch_assoc();

    if ($hospital && password_verify(
        $password,
        $hospital["password"]
    )) {
        session_regenerate_id(true);
        $_SESSION["hospital_id"] = $hospital["id"];
        $_SESSION["hospital_name"] = $hospital["hospital_name"];

        header("Location: hospital_dashboard.php");
        exit;
    } else {
        $error = "Invalid username or password";
    }

    $stmt->close();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Login</title>
    <link rel="stylesheet" href="style.css">
</head>

<body class="login-page">

<div class="login-container">

    <div class="login-icon">🏥</div>

    <h1>Hospital Portal</h1>
    <p class="login-subtitle">
        Emergency Healthcare Management System
    </p>

    <?php if ($error !== ""): ?>
        <div class="error-box">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <label>Hospital Username</label>
        <input
            type="text"
            name="username"
            placeholder="Enter username"
            required
            autocomplete="username"
        >

        <label>Password</label>
        <input
            type="password"
            name="password"
            placeholder="Enter password"
            required
            autocomplete="current-password"
        >

        <button type="submit" class="login-button">
            🔐 Login
        </button>

    </form>

</div>

</body>
</html>