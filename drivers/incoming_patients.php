<?php

session_start();
require_once "db.php";

$hospital_id = intval(
    $_SESSION['hospital_id']
    ?? $_SESSION['user_id']
    ?? $_GET['hospital_id']
    ?? 0
);

if ($hospital_id <= 0) {
    die("Hospital ID not found.");
}


/* Hospital details */

$stmt = $conn->prepare("
    SELECT hospital_name, address, city, phone
    FROM hospitals
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $hospital_id);
$stmt->execute();

$hospital_result = $stmt->get_result();
$hospital = $hospital_result->fetch_assoc();

$stmt->close();

if (!$hospital) {
    die("Hospital not found.");
}


/* Incoming patients */

$stmt = $conn->prepare("
    SELECT
        id,
        patient_name,
        age,
        gender,
        emergency_type,
        required_specialization,
        ambulance_number,
        driver_name,
        driver_phone,
        eta_minutes,
        status,
        created_at
    FROM incoming_patients
    WHERE hospital_id = ?
    ORDER BY id DESC
");

$stmt->bind_param("i", $hospital_id);
$stmt->execute();

$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html>

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Incoming Patients</title>

<style>

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f7fb;
}

.header {
    background: #0b5ed7;
    color: white;
    padding: 25px 35px;
}

.header h1 {
    margin: 0;
}

.container {
    max-width: 1200px;
    margin: 30px auto;
    padding: 20px;
}

.hospital-info {
    background: white;
    padding: 20px;
    border-radius: 14px;
    margin-bottom: 25px;
    box-shadow: 0 4px 15px rgba(0,0,0,.08);
}

.patient {
    background: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,.08);
    border-left: 6px solid #0b5ed7;
}

.patient h2 {
    margin-top: 0;
}

.info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 12px;
}

.info div {
    background: #f4f7fb;
    padding: 12px;
    border-radius: 8px;
}

.status {
    display: inline-block;
    padding: 7px 14px;
    border-radius: 20px;
    background: #fff3cd;
    font-weight: bold;
}

button {
    border: 0;
    padding: 12px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
    margin-right: 10px;
    margin-top: 15px;
}

.arrived {
    background: #198754;
    color: white;
}

.cancel {
    background: #dc3545;
    color: white;
}

.empty {
    background: white;
    padding: 40px;
    text-align: center;
    border-radius: 15px;
}

</style>

</head>

<body>


<div class="header">

    <h1>🏥 Incoming Patients</h1>

    <p>
        <?= htmlspecialchars($hospital['hospital_name']) ?>
    </p>

</div>


<div class="container">


<div class="hospital-info">

    <h2>
        🏥 <?= htmlspecialchars($hospital['hospital_name']) ?>
    </h2>

    <p>
        📍 <?= htmlspecialchars($hospital['address']) ?>,
        <?= htmlspecialchars($hospital['city']) ?>
    </p>

    <p>
        📞 <?= htmlspecialchars($hospital['phone']) ?>
    </p>

</div>


<?php if ($result->num_rows == 0): ?>

    <div class="empty">

        <h2>🚑 No Incoming Patients</h2>

        <p>
            There are currently no patients coming to this hospital.
        </p>

    </div>

<?php else: ?>


<?php while ($patient = $result->fetch_assoc()): ?>

<div class="patient">

    <h2>
        👤 <?= htmlspecialchars($patient['patient_name']) ?>
    </h2>


    <span class="status">

        <?= htmlspecialchars($patient['status']) ?>

    </span>


    <div class="info">

        <div>
            <b>🚨 Emergency</b><br>
            <?= htmlspecialchars($patient['emergency_type']) ?>
        </div>


        <div>
            <b>👨‍⚕️ Required Specialization</b><br>
            <?= htmlspecialchars(
                $patient['required_specialization']
            ) ?>
        </div>


        <div>
            <b>🎂 Age</b><br>
            <?= htmlspecialchars(
                $patient['age']
            ) ?>
        </div>


        <div>
            <b>⚧ Gender</b><br>
            <?= htmlspecialchars(
                $patient['gender']
            ) ?>
        </div>


        <div>
            <b>🚑 Ambulance</b><br>
            <?= htmlspecialchars(
                $patient['ambulance_number']
            ) ?>
        </div>


        <div>
            <b>👨‍✈️ Driver</b><br>
            <?= htmlspecialchars(
                $patient['driver_name']
            ) ?>
        </div>


        <div>
            <b>📞 Driver Phone</b><br>
            <?= htmlspecialchars(
                $patient['driver_phone']
            ) ?>
        </div>


        <div>
            <b>⏱ ETA</b><br>
            <?= htmlspecialchars(
                $patient['eta_minutes']
            ) ?> minutes
        </div>

    </div>


    <?php if ($patient['status'] == 'Incoming'): ?>

        <form
            method="POST"
            action="update_patient_status.php"
            style="display:inline;"
        >

            <input
                type="hidden"
                name="id"
                value="<?= $patient['id'] ?>"
            >

            <input
                type="hidden"
                name="status"
                value="Arrived"
            >

            <input
                type="hidden"
                name="hospital_id"
                value="<?= $hospital_id ?>"
            >

            <button class="arrived">
                ✅ Patient Arrived
            </button>

        </form>


        <form
            method="POST"
            action="update_patient_status.php"
            style="display:inline;"
        >

            <input
                type="hidden"
                name="id"
                value="<?= $patient['id'] ?>"
            >

            <input
                type="hidden"
                name="status"
                value="Cancelled"
            >

            <input
                type="hidden"
                name="hospital_id"
                value="<?= $hospital_id ?>"
            >

            <button class="cancel">
                ❌ Cancel
            </button>

        </form>

    <?php endif; ?>


</div>

<?php endwhile; ?>


<?php endif; ?>


</div>

</body>

</html>