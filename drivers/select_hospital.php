<?php

include "db.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request.");
}

$booking_code = $_POST['booking_code'] ?? '';
$hospital_id = intval($_POST['hospital_id'] ?? 0);

if (empty($booking_code) || $hospital_id <= 0) {
    die("Booking or hospital information missing.");
}


/* =====================================================
   GET BOOKING
===================================================== */

$stmt = $conn->prepare("
    SELECT *
    FROM ambulance_bookings
    WHERE booking_code = ?
    LIMIT 1
");

$stmt->bind_param("s", $booking_code);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Booking not found.");
}

$booking = $result->fetch_assoc();


/* =====================================================
   GET HOSPITAL
===================================================== */

$stmt = $conn->prepare("
    SELECT *
    FROM hospitals
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $hospital_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Hospital not found.");
}

$hospital = $result->fetch_assoc();


/* =====================================================
   GET DRIVER
===================================================== */

$driver = null;

if (!empty($booking['driver_id'])) {

    $stmt = $conn->prepare("
        SELECT
            id,
            full_name,
            phone,
            ambulance_number,
            ambulance_type,
            latitude,
            longitude
        FROM ambulance_drivers
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $booking['driver_id']);
    $stmt->execute();

    $driverResult = $stmt->get_result();

    if ($driverResult->num_rows > 0) {
        $driver = $driverResult->fetch_assoc();
    }
}


/* =====================================================
   DRIVER DATA
===================================================== */

$driverName = $driver['full_name'] ?? 'Not available';
$driverPhone = $driver['phone'] ?? 'Not available';
$ambulanceNumber = $driver['ambulance_number'] ?? 'Not available';
$ambulanceType = $driver['ambulance_type'] ?? 'Not available';

$driverLat = $driver['latitude'] ?? null;
$driverLng = $driver['longitude'] ?? null;


/* =====================================================
   CHECK EXISTING INCOMING PATIENT
===================================================== */

$check = $conn->prepare("
    SELECT id
    FROM incoming_patients
    WHERE booking_code = ?
    LIMIT 1
");

$check->bind_param("s", $booking_code);
$check->execute();

$existingResult = $check->get_result();


/* =====================================================
   INSERT / UPDATE INCOMING PATIENT
===================================================== */

$status = "Incoming";

if ($existingResult->num_rows === 0) {

    $stmt = $conn->prepare("
        INSERT INTO incoming_patients
        (
            hospital_id,
            booking_code,
            patient_name,
            patient_phone,
            emergency_type,
            pickup_address,
            pickup_latitude,
            pickup_longitude,
            ambulance_number,
            ambulance_type,
            driver_id,
            driver_name,
            driver_phone,
            driver_latitude,
            driver_longitude,
            status
        )
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "isssssddssissdds",
        $hospital_id,
        $booking['booking_code'],
        $booking['patient_name'],
        $booking['patient_phone'],
        $booking['emergency_type'],
        $booking['pickup_address'],
        $booking['pickup_latitude'],
        $booking['pickup_longitude'],
        $ambulanceNumber,
        $ambulanceType,
        $booking['driver_id'],
        $driverName,
        $driverPhone,
        $driverLat,
        $driverLng,
        $status
    );

    if (!$stmt->execute()) {
        die("Failed to create incoming patient: " . $stmt->error);
    }

} else {

    $stmt = $conn->prepare("
        UPDATE incoming_patients
        SET
            hospital_id = ?,
            patient_name = ?,
            patient_phone = ?,
            emergency_type = ?,
            pickup_address = ?,
            pickup_latitude = ?,
            pickup_longitude = ?,
            ambulance_number = ?,
            ambulance_type = ?,
            driver_id = ?,
            driver_name = ?,
            driver_phone = ?,
            driver_latitude = ?,
            driver_longitude = ?,
            status = 'Incoming'
        WHERE booking_code = ?
    ");

    $stmt->bind_param(
        "issssddssissddss",
        $hospital_id,
        $booking['patient_name'],
        $booking['patient_phone'],
        $booking['emergency_type'],
        $booking['pickup_address'],
        $booking['pickup_latitude'],
        $booking['pickup_longitude'],
        $ambulanceNumber,
        $ambulanceType,
        $booking['driver_id'],
        $driverName,
        $driverPhone,
        $driverLat,
        $driverLng,
        $booking_code
    );

    if (!$stmt->execute()) {
        die("Failed to update incoming patient: " . $stmt->error);
    }
}


/* =====================================================
   UPDATE BOOKING WITH SELECTED HOSPITAL
===================================================== */

$stmt = $conn->prepare("
    UPDATE ambulance_bookings
    SET
        hospital_name = ?,
        hospital_latitude = ?,
        hospital_longitude = ?
    WHERE booking_code = ?
");

$stmt->bind_param(
    "sdds",
    $hospital['hospital_name'],
    $hospital['latitude'],
    $hospital['longitude'],
    $booking_code
);

$stmt->execute();


/* =====================================================
   GOOGLE MAPS
===================================================== */

if (
    $driverLat !== null &&
    $driverLng !== null
) {

    $origin = $driverLat . "," . $driverLng;

} elseif (
    $booking['pickup_latitude'] !== null &&
    $booking['pickup_longitude'] !== null
) {

    $origin =
        $booking['pickup_latitude'] .
        "," .
        $booking['pickup_longitude'];

} else {

    $origin = $booking['pickup_address'];
}


$destination =
    $hospital['latitude'] .
    "," .
    $hospital['longitude'];


$mapsUrl =
    "https://www.google.com/maps/dir/?api=1"
    . "&origin=" . urlencode($origin)
    . "&destination=" . urlencode($destination)
    . "&travelmode=driving";

?>

<!DOCTYPE html>
<html>

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Hospital Selected - HealthResQ</title>

<style>

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f7fb;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
}

.box {
    width: 90%;
    max-width: 550px;
    background: white;
    padding: 35px;
    border-radius: 18px;
    text-align: center;
    box-shadow: 0 5px 25px rgba(0,0,0,.12);
}

.success {
    font-size: 60px;
}

h1 {
    color: #087f23;
}

.info {
    margin: 12px 0;
    font-size: 16px;
}

.maps-btn {
    display: block;
    background: #d90429;
    color: white;
    padding: 15px;
    border-radius: 9px;
    text-decoration: none;
    margin-top: 25px;
    font-weight: bold;
}

.dashboard-btn {
    display: block;
    background: #0b7a75;
    color: white;
    padding: 13px;
    border-radius: 9px;
    text-decoration: none;
    margin-top: 12px;
    font-weight: bold;
}

</style>

</head>

<body>

<div class="box">

<div class="success">✅</div>

<h1>Hospital Selected</h1>

<div class="info">
🏥
<strong>
<?= htmlspecialchars($hospital['hospital_name']) ?>
</strong>
</div>

<div class="info">
👤 Patient:
<strong>
<?= htmlspecialchars($booking['patient_name']) ?>
</strong>
</div>

<div class="info">
🚑 Booking:
<strong>
<?= htmlspecialchars($booking['booking_code']) ?>
</strong>
</div>

<p>
Patient and ambulance details have been sent to the hospital.
</p>

<a class="maps-btn"
   href="<?= htmlspecialchars($mapsUrl) ?>"
   target="_blank">

🗺️ GET DIRECTIONS TO HOSPITAL

</a>

<a class="dashboard-btn"
   href="hospital_dashboard.php?hospital_id=<?= $hospital_id ?>">

🏥 OPEN HOSPITAL APP

</a>

</div>

</body>

</html>