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


/* Get current data */

$stmt = $conn->prepare(
    "SELECT *
     FROM hospital_beds
     WHERE hospital_id = ?
     LIMIT 1"
);

$stmt->bind_param("i", $hospital_id);
$stmt->execute();

$beds = $stmt->get_result()->fetch_assoc();
$stmt->close();


if (!$beds) {

    $stmt = $conn->prepare(
        "INSERT INTO hospital_beds
        (hospital_id, general_total, general_available,
         icu_total, icu_available,
         emergency_total, emergency_available)
         VALUES (?,0,0,0,0,0,0)"
    );

    $stmt->bind_param("i", $hospital_id);
    $stmt->execute();
    $stmt->close();

    $beds = [
        "general_total" => 0,
        "general_available" => 0,
        "icu_total" => 0,
        "icu_available" => 0,
        "emergency_total" => 0,
        "emergency_available" => 0
    ];
}


/* Update */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $general_total = max(0, (int)($_POST["general_total"] ?? 0));
    $general_available = max(0, (int)($_POST["general_available"] ?? 0));

    $icu_total = max(0, (int)($_POST["icu_total"] ?? 0));
    $icu_available = max(0, (int)($_POST["icu_available"] ?? 0));

    $emergency_total = max(0, (int)($_POST["emergency_total"] ?? 0));
    $emergency_available = max(0, (int)($_POST["emergency_available"] ?? 0));

    if (
        $general_available > $general_total ||
        $icu_available > $icu_total ||
        $emergency_available > $emergency_total
    ) {

        $error = "Available beds cannot be greater than total beds.";

    } else {

        $stmt = $conn->prepare(
            "UPDATE hospital_beds
             SET general_total = ?,
                 general_available = ?,
                 icu_total = ?,
                 icu_available = ?,
                 emergency_total = ?,
                 emergency_available = ?
             WHERE hospital_id = ?"
        );

        $stmt->bind_param(
            "iiiiiii",
            $general_total,
            $general_available,
            $icu_total,
            $icu_available,
            $emergency_total,
            $emergency_available,
            $hospital_id
        );

        if ($stmt->execute()) {
            $message = "Bed availability updated successfully.";

            $beds = [
                "general_total" => $general_total,
                "general_available" => $general_available,
                "icu_total" => $icu_total,
                "icu_available" => $icu_available,
                "emergency_total" => $emergency_total,
                "emergency_available" => $emergency_available
            ];
        } else {
            $error = "Unable to update availability.";
        }

        $stmt->close();
    }
}


/* Emergency status */

if (isset($_POST["update_emergency"])) {

    $emergency_available =
        isset($_POST["emergency_available"]) ? 1 : 0;

    $stmt = $conn->prepare(
        "UPDATE hospitals
         SET emergency_available = ?
         WHERE id = ?"
    );

    $stmt->bind_param(
        "ii",
        $emergency_available,
        $hospital_id
    );

    $stmt->execute();
    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Bed Availability</title>

<link rel="stylesheet" href="style.css">

</head>

<body>

<header class="topbar">

    <div>
        <h2>🏥 Healthcare Hospital Portal</h2>
        <span>Bed & Emergency Management</span>
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

        <a href="update_availability.php" class="active">
            🛏️ Bed Availability
        </a>

        <a href="doctors.php">👨‍⚕️ Doctors</a>

        <a href="incoming_patients.php">
            🚑 Incoming Patients
        </a>

    </aside>


    <main class="main-content">

        <h1>Bed Availability</h1>

        <p class="page-description">
            Update the current availability of hospital beds.
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

            <form method="POST">

                <h2>🛏️ General Beds</h2>

                <div class="form-grid">

                    <div>
                        <label>Total General Beds</label>

                        <input
                            type="number"
                            name="general_total"
                            min="0"
                            value="<?= (int)$beds["general_total"] ?>"
                            required
                        >
                    </div>

                    <div>
                        <label>Available General Beds</label>

                        <input
                            type="number"
                            name="general_available"
                            min="0"
                            value="<?= (int)$beds["general_available"] ?>"
                            required
                        >
                    </div>

                </div>


                <h2>🏥 ICU Beds</h2>

                <div class="form-grid">

                    <div>
                        <label>Total ICU Beds</label>

                        <input
                            type="number"
                            name="icu_total"
                            min="0"
                            value="<?= (int)$beds["icu_total"] ?>"
                            required
                        >
                    </div>

                    <div>
                        <label>Available ICU Beds</label>

                        <input
                            type="number"
                            name="icu_available"
                            min="0"
                            value="<?= (int)$beds["icu_available"] ?>"
                            required
                        >
                    </div>

                </div>


                <h2>🚨 Emergency Beds</h2>

                <div class="form-grid">

                    <div>
                        <label>Total Emergency Beds</label>

                        <input
                            type="number"
                            name="emergency_total"
                            min="0"
                            value="<?= (int)$beds["emergency_total"] ?>"
                            required
                        >
                    </div>

                    <div>
                        <label>Available Emergency Beds</label>

                        <input
                            type="number"
                            name="emergency_available"
                            min="0"
                            value="<?= (int)$beds["emergency_available"] ?>"
                            required
                        >
                    </div>

                </div>


                <button type="submit" class="primary-button">
                    💾 Save Bed Availability
                </button>

            </form>

        </section>


        <section class="panel">

            <h2>🚨 Emergency Service</h2>

            <form method="POST">

                <label class="checkbox-label">

                    <input
                        type="checkbox"
                        name="emergency_available"
                        value="1"
                        <?= $beds ? "" : "" ?>
                    >

                    Emergency Department Available

                </label>

                <input
                    type="hidden"
                    name="update_emergency"
                    value="1"
                >

                <button
                    type="submit"
                    class="primary-button"
                >
                    Update Emergency Status
                </button>

            </form>

        </section>

    </main>

</div>

</body>
</html>