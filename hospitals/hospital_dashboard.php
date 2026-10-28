<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["hospital_id"])) {
    header("Location: hospitals_login.php");
    exit;
}

$hospital_id = (int) $_SESSION["hospital_id"];
$hospital_name = $_SESSION["hospital_name"] ?? "Hospital";

$stmt = $conn->prepare(
    "SELECT hospital_name, address, city, phone,
            emergency_available, opening_time, closing_time
     FROM hospitals
     WHERE id = ?
     LIMIT 1"
);

$stmt->bind_param("i", $hospital_id);
$stmt->execute();

$hospital = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$hospital) {
    session_destroy();
    header("Location: hospitals_login.php");
    exit;
}


/* Beds */

$stmt = $conn->prepare(
    "SELECT general_total, general_available,
            icu_total, icu_available,
            emergency_total, emergency_available
     FROM hospital_beds
     WHERE hospital_id = ?
     LIMIT 1"
);

$stmt->bind_param("i", $hospital_id);
$stmt->execute();

$beds = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$beds) {
    $beds = [
        "general_total" => 0,
        "general_available" => 0,
        "icu_total" => 0,
        "icu_available" => 0,
        "emergency_total" => 0,
        "emergency_available" => 0
    ];
}


/* Doctors */

$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM hospital_doctors
     WHERE hospital_id = ?"
);

$stmt->bind_param("i", $hospital_id);
$stmt->execute();

$doctor_total = $stmt->get_result()->fetch_assoc()["total"];
$stmt->close();


$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM hospital_doctors
     WHERE hospital_id = ?
     AND available = 1"
);

$stmt->bind_param("i", $hospital_id);
$stmt->execute();

$doctor_available = $stmt->get_result()->fetch_assoc()["total"];
$stmt->close();


/* Incoming Patients */

$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM incoming_patients
     WHERE hospital_id = ?
     AND status = 'Incoming'"
);

$stmt->bind_param("i", $hospital_id);
$stmt->execute();

$incoming_count = $stmt->get_result()->fetch_assoc()["total"];
$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Hospital Dashboard</title>

<link rel="stylesheet" href="style.css">

</head>

<body>

<header class="topbar">

    <div>
        <h2>🏥 Healthcare Hospital Portal</h2>
        <span>Emergency Healthcare Management System</span>
    </div>

    <div class="top-right">
        <span>Welcome, <?= htmlspecialchars($hospital["hospital_name"]) ?></span>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

</header>


<div class="layout">

    <aside class="sidebar">

        <a href="hospital_dashboard.php" class="active">
            🏠 Dashboard
        </a>

        <a href="update_availability.php">
            🛏️ Bed Availability
        </a>

        <a href="doctors.php">
            👨‍⚕️ Doctors
        </a>

        <a href="incoming_patients.php">
            🚑 Incoming Patients
        </a>

        <a href="logout.php">
            🚪 Logout
        </a>

    </aside>


    <main class="main-content">

        <div class="page-heading">

            <div>
                <h1>Hospital Dashboard</h1>

                <p>
                    Manage your hospital's emergency resources and incoming patients.
                </p>
            </div>

            <div class="emergency-status">

                <?php if ($hospital["emergency_available"]): ?>

                    <span class="status-green">
                        ● Emergency Available
                    </span>

                <?php else: ?>

                    <span class="status-red">
                        ● Emergency Unavailable
                    </span>

                <?php endif; ?>

            </div>

        </div>


        <div class="cards">

            <div class="dashboard-card">

                <div class="card-icon">🛏️</div>

                <h3>General Beds</h3>

                <strong>
                    <?= (int)$beds["general_available"] ?>
                </strong>

                <p>
                    Available / <?= (int)$beds["general_total"] ?> Total
                </p>

            </div>


            <div class="dashboard-card">

                <div class="card-icon">🏥</div>

                <h3>ICU Beds</h3>

                <strong>
                    <?= (int)$beds["icu_available"] ?>
                </strong>

                <p>
                    Available / <?= (int)$beds["icu_total"] ?> Total
                </p>

            </div>


            <div class="dashboard-card">

                <div class="card-icon">🚨</div>

                <h3>Emergency Beds</h3>

                <strong>
                    <?= (int)$beds["emergency_available"] ?>
                </strong>

                <p>
                    Available / <?= (int)$beds["emergency_total"] ?> Total
                </p>

            </div>


            <div class="dashboard-card">

                <div class="card-icon">👨‍⚕️</div>

                <h3>Doctors Available</h3>

                <strong>
                    <?= (int)$doctor_available ?>
                </strong>

                <p>
                    Available / <?= (int)$doctor_total ?> Total
                </p>

            </div>


            <div class="dashboard-card">

                <div class="card-icon">🚑</div>

                <h3>Incoming Patients</h3>

                <strong>
                    <?= (int)$incoming_count ?>
                </strong>

                <p>Currently Incoming</p>

            </div>

        </div>


        <div class="dashboard-grid">

            <section class="panel">

                <h2>🏥 Hospital Information</h2>

                <div class="info-row">
                    <span>Hospital Name</span>
                    <strong><?= htmlspecialchars($hospital["hospital_name"]) ?></strong>
                </div>

                <div class="info-row">
                    <span>Address</span>
                    <strong><?= htmlspecialchars($hospital["address"] ?? "Not available") ?></strong>
                </div>

                <div class="info-row">
                    <span>City</span>
                    <strong><?= htmlspecialchars($hospital["city"] ?? "Not available") ?></strong>
                </div>

                <div class="info-row">
                    <span>Phone</span>
                    <strong><?= htmlspecialchars($hospital["phone"] ?? "Not available") ?></strong>
                </div>

                <div class="info-row">
                    <span>Emergency</span>

                    <strong>
                        <?= $hospital["emergency_available"] ? "Available" : "Unavailable" ?>
                    </strong>

                </div>

            </section>


            <section class="panel">

                <h2>⚡ Quick Actions</h2>

                <a href="update_availability.php" class="action-button">
                    🛏️ Update Bed Availability
                </a>

                <a href="doctors.php" class="action-button">
                    👨‍⚕️ Manage Doctors
                </a>

                <a href="incoming_patients.php" class="action-button">
                    🚑 View Incoming Patients
                </a>

            </section>

        </div>

    </main>

</div>

</body>
</html>