<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["hospital_id"])) {
    header("Location: hospitals_login.php");
    exit;
}

$hospital_id = (int)$_SESSION["hospital_id"];

$message = "";


/* Update status */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $patient_id = (int)($_POST["patient_id"] ?? 0);
    $status = $_POST["status"] ?? "";

    $allowed = ["Incoming", "Arrived", "Cancelled"];

    if ($patient_id > 0 && in_array($status, $allowed, true)) {

        $stmt = $conn->prepare(
            "UPDATE incoming_patients
             SET status = ?
             WHERE id = ?
             AND hospital_id = ?"
        );

        $stmt->bind_param(
            "sii",
            $status,
            $patient_id,
            $hospital_id
        );

        $stmt->execute();
        $stmt->close();

        $message = "Patient status updated.";
    }
}


/* Get incoming patients */

$stmt = $conn->prepare(
    "SELECT *
     FROM incoming_patients
     WHERE hospital_id = ?
     ORDER BY created_at DESC"
);

$stmt->bind_param("i", $hospital_id);
$stmt->execute();

$patients = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Incoming Patients</title>

<link rel="stylesheet" href="style.css">

</head>

<body>

<header class="topbar">

    <div>
        <h2>🏥 Healthcare Hospital Portal</h2>
        <span>Incoming Ambulance & Patient Requests</span>
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

    <a href="hospital_dashboard.php">
        🏠 Dashboard
    </a>

    <a href="update_availability.php">
        🛏️ Bed Availability
    </a>

    <a href="doctors.php">
        👨‍⚕️ Doctors
    </a>

    <a href="incoming_patients.php" class="active">
        🚑 Incoming Patients
    </a>

</aside>


<main class="main-content">

    <h1>Incoming Patients</h1>

    <p class="page-description">
        Patients and ambulances sent to this hospital will appear here.
    </p>


    <?php if ($message): ?>

        <div class="success-box">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>


    <section class="panel">

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>Patient</th>
                        <th>Emergency</th>
                        <th>Specialization</th>
                        <th>Ambulance</th>
                        <th>Driver</th>
                        <th>ETA</th>
                        <th>Status</th>
                        <th>Action</th>

                    </tr>

                </thead>


                <tbody>

                <?php if ($patients->num_rows > 0): ?>

                    <?php while ($patient = $patients->fetch_assoc()): ?>

                        <tr>

                            <td>

                                <strong>
                                    <?= htmlspecialchars($patient["patient_name"]) ?>
                                </strong>

                                <?php if ($patient["age"] !== null): ?>

                                    <br>
                                    Age:
                                    <?= (int)$patient["age"] ?>

                                <?php endif; ?>

                                <?php if ($patient["gender"]): ?>

                                    <br>
                                    <?= htmlspecialchars($patient["gender"]) ?>

                                <?php endif; ?>

                            </td>


                            <td>
                                <?= htmlspecialchars($patient["emergency_type"] ?? "-") ?>
                            </td>


                            <td>
                                <?= htmlspecialchars($patient["required_specialization"] ?? "-") ?>
                            </td>


                            <td>
                                <?= htmlspecialchars($patient["ambulance_number"] ?? "-") ?>
                            </td>


                            <td>

                                <?= htmlspecialchars($patient["driver_name"] ?? "-") ?>

                                <?php if ($patient["driver_phone"]): ?>

                                    <br>
                                    <?= htmlspecialchars($patient["driver_phone"]) ?>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?php if ($patient["eta_minutes"] !== null): ?>

                                    <?= (int)$patient["eta_minutes"] ?> min

                                <?php else: ?>

                                    -

                                <?php endif; ?>

                            </td>


                            <td>

                                <?php if ($patient["status"] === "Incoming"): ?>

                                    <span class="status-orange">
                                        Incoming
                                    </span>

                                <?php elseif ($patient["status"] === "Arrived"): ?>

                                    <span class="status-green">
                                        Arrived
                                    </span>

                                <?php else: ?>

                                    <span class="status-red">
                                        Cancelled
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?php if ($patient["status"] === "Incoming"): ?>

                                    <form method="POST" class="inline-form">

                                        <input
                                            type="hidden"
                                            name="patient_id"
                                            value="<?= (int)$patient["id"] ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="status"
                                            value="Arrived"
                                            class="small-button"
                                        >
                                            Arrived
                                        </button>

                                        <button
                                            type="submit"
                                            name="status"
                                            value="Cancelled"
                                            class="delete-button"
                                        >
                                            Cancel
                                        </button>

                                    </form>

                                <?php else: ?>

                                    Completed

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="8">

                            🚑 No incoming patients currently.

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