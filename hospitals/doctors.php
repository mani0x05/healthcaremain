<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["hospital_id"])) {
    header("Location: hospitals_login.php");
    exit;
}

$hospital_id = (int)$_SESSION["hospital_id"];

$message = "";
$error = "";


/* Add doctor */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_doctor"])) {

    $doctor_name = trim($_POST["doctor_name"] ?? "");
    $specialization = trim($_POST["specialization"] ?? "");

    if ($doctor_name === "" || $specialization === "") {

        $error = "Please enter doctor name and specialization.";

    } else {

        $stmt = $conn->prepare(
            "INSERT INTO hospital_doctors
             (hospital_id, doctor_name, specialization, available)
             VALUES (?, ?, ?, 1)"
        );

        $stmt->bind_param(
            "iss",
            $hospital_id,
            $doctor_name,
            $specialization
        );

        if ($stmt->execute()) {
            $message = "Doctor added successfully.";
        } else {
            $error = "Unable to add doctor.";
        }

        $stmt->close();
    }
}


/* Change doctor availability */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["toggle_doctor"])) {

    $doctor_id = (int)($_POST["doctor_id"] ?? 0);

    $stmt = $conn->prepare(
        "UPDATE hospital_doctors
         SET available = IF(available = 1, 0, 1)
         WHERE id = ?
         AND hospital_id = ?"
    );

    $stmt->bind_param(
        "ii",
        $doctor_id,
        $hospital_id
    );

    $stmt->execute();
    $stmt->close();

    $message = "Doctor availability updated.";
}


/* Delete doctor */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_doctor"])) {

    $doctor_id = (int)($_POST["doctor_id"] ?? 0);

    $stmt = $conn->prepare(
        "DELETE FROM hospital_doctors
         WHERE id = ?
         AND hospital_id = ?"
    );

    $stmt->bind_param(
        "ii",
        $doctor_id,
        $hospital_id
    );

    $stmt->execute();
    $stmt->close();

    $message = "Doctor removed.";
}


/* Get doctors */

$stmt = $conn->prepare(
    "SELECT id, doctor_name, specialization, available
     FROM hospital_doctors
     WHERE hospital_id = ?
     ORDER BY doctor_name ASC"
);

$stmt->bind_param("i", $hospital_id);
$stmt->execute();

$doctors = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Doctors</title>

<link rel="stylesheet" href="style.css">

</head>

<body>

<header class="topbar">

    <div>
        <h2>🏥 Healthcare Hospital Portal</h2>
        <span>Doctor Management</span>
    </div>

    <div class="top-right">

        <a href="hospital_dashboard.php" class="back-btn">
            Dashboard
        </a>

        <a href="logout.php" class="logout-btn">
            Logout
        </a>

    </div>

</header>


<div class="layout">

<aside class="sidebar">

    <a href="hospital_dashboard.php">🏠 Dashboard</a>

    <a href="update_availability.php">
        🛏️ Bed Availability
    </a>

    <a href="doctors.php" class="active">
        👨‍⚕️ Doctors
    </a>

    <a href="incoming_patients.php">
        🚑 Incoming Patients
    </a>

</aside>


<main class="main-content">

    <h1>Doctor Management</h1>

    <p class="page-description">
        Add doctors and manage their current availability.
    </p>


    <?php if ($message): ?>

        <div class="success-box">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="error-box">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <section class="panel">

        <h2>➕ Add Doctor</h2>

        <form method="POST">

            <div class="form-grid">

                <div>

                    <label>Doctor Name</label>

                    <input
                        type="text"
                        name="doctor_name"
                        placeholder="Dr. Example"
                        required
                    >

                </div>


                <div>

                    <label>Specialization</label>

                    <input
                        type="text"
                        name="specialization"
                        placeholder="Cardiology"
                        required
                    >

                </div>

            </div>

            <button
                type="submit"
                name="add_doctor"
                class="primary-button"
            >
                Add Doctor
            </button>

        </form>

    </section>


    <section class="panel">

        <h2>👨‍⚕️ Doctors List</h2>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>
                        <th>Doctor</th>
                        <th>Specialization</th>
                        <th>Availability</th>
                        <th>Action</th>
                    </tr>

                </thead>

                <tbody>

                <?php if ($doctors->num_rows > 0): ?>

                    <?php while ($doctor = $doctors->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars($doctor["doctor_name"]) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($doctor["specialization"]) ?>
                            </td>

                            <td>

                                <?php if ($doctor["available"]): ?>

                                    <span class="status-green">
                                        Available
                                    </span>

                                <?php else: ?>

                                    <span class="status-red">
                                        Unavailable
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <form method="POST" class="inline-form">

                                    <input
                                        type="hidden"
                                        name="doctor_id"
                                        value="<?= (int)$doctor["id"] ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="toggle_doctor"
                                        class="small-button"
                                    >
                                        Toggle
                                    </button>

                                    <button
                                        type="submit"
                                        name="delete_doctor"
                                        class="delete-button"
                                        onclick="return confirm('Remove this doctor?');"
                                    >
                                        Delete
                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="4">
                            No doctors added yet.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</main>

</div>

</body>
</html>