<?php

include "db.php";

if (!isset($_GET['booking_code']) || empty($_GET['booking_code'])) {
    die("Booking ID not found.");
}

$booking_code = $_GET['booking_code'];

/* --------------------------------------------------
   GET BOOKING
-------------------------------------------------- */

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

$pickupLat = $booking['pickup_latitude'];
$pickupLng = $booking['pickup_longitude'];
$emergencyType = $booking['emergency_type'];


/* --------------------------------------------------
   GET HOSPITALS + LIVE BED DATA
-------------------------------------------------- */

$sql = "
SELECT
    h.id,
    h.hospital_name,
    h.address,
    h.city,
    h.phone,
    h.latitude,
    h.longitude,
    h.emergency_available,
    h.opening_time,
    h.closing_time,

    COALESCE(hb.general_total,0) AS general_total,
    COALESCE(hb.general_available,0) AS general_available,

    COALESCE(hb.icu_total,0) AS icu_total,
    COALESCE(hb.icu_available,0) AS icu_available,

    COALESCE(hb.emergency_total,0) AS emergency_total,
    COALESCE(hb.emergency_available,0) AS emergency_available

FROM hospitals h

LEFT JOIN hospital_beds hb
ON h.id = hb.hospital_id

ORDER BY h.hospital_name ASC
";

$hospitals = $conn->query($sql);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title>Specialized Hospitals - HealthResQ</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f7fb;
}

header {
    background: #d90429;
    color: white;
    padding: 20px;
    text-align: center;
}

header h1 {
    margin: 0;
}

.container {
    width: 92%;
    max-width: 1200px;
    margin: 25px auto;
}

.booking-box {
    background: white;
    padding: 18px;
    border-radius: 12px;
    margin-bottom: 20px;
    box-shadow: 0 3px 12px rgba(0,0,0,.08);
}

.booking-box strong {
    color: #d90429;
}

.hospital-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 20px;
}

.card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,.1);
}

.card h2 {
    margin-top: 0;
    color: #222;
}

.address {
    color: #666;
    margin-bottom: 12px;
}

.info {
    margin: 8px 0;
}

.badge {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 13px;
    margin: 3px;
    background: #eef2f7;
}

.available {
    color: #087f23;
    font-weight: bold;
}

.unavailable {
    color: #d90429;
    font-weight: bold;
}

.select-btn {
    width: 100%;
    border: none;
    background: #d90429;
    color: white;
    padding: 13px;
    border-radius: 8px;
    font-size: 16px;
    cursor: pointer;
    margin-top: 15px;
}

.select-btn:hover {
    background: #b90322;
}

.distance {
    font-weight: bold;
    color: #075985;
}

</style>

</head>

<body>

<header>

<h1>🏥 Specialized Hospitals</h1>

<p>HealthResQ Emergency Hospital Selection</p>

</header>


<div class="container">

<div class="booking-box">

<div>
Booking ID:
<strong>
<?= htmlspecialchars($booking['booking_code']) ?>
</strong>
</div>

<div>
Emergency:
<strong>
<?= htmlspecialchars($booking['emergency_type']) ?>
</strong>
</div>

<div>
Pickup:
<?= htmlspecialchars($booking['pickup_address']) ?>
</div>

</div>


<div class="hospital-grid">

<?php while ($hospital = $hospitals->fetch_assoc()): ?>

<?php

/* -----------------------------------------
   DISTANCE CALCULATION
----------------------------------------- */

$distance = null;

if (
    $pickupLat !== null &&
    $pickupLng !== null &&
    $hospital['latitude'] !== null &&
    $hospital['longitude'] !== null
) {

    $earthRadius = 6371;

    $lat1 = deg2rad((float)$pickupLat);
    $lat2 = deg2rad((float)$hospital['latitude']);

    $latDiff = deg2rad(
        (float)$hospital['latitude'] - (float)$pickupLat
    );

    $lngDiff = deg2rad(
        (float)$hospital['longitude'] - (float)$pickupLng
    );

    $a =
        sin($latDiff / 2) * sin($latDiff / 2)
        +
        cos($lat1) *
        cos($lat2) *
        sin($lngDiff / 2) *
        sin($lngDiff / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    $distance = round($earthRadius * $c, 2);
}


/* -----------------------------------------
   OPEN / CLOSED
----------------------------------------- */

$currentTime = date("H:i:s");

$isOpen = true;

if (
    !empty($hospital['opening_time']) &&
    !empty($hospital['closing_time'])
) {

    $open = $hospital['opening_time'];
    $close = $hospital['closing_time'];

    if ($open <= $close) {

        if ($currentTime < $open || $currentTime > $close) {
            $isOpen = false;
        }

    } else {

        if ($currentTime < $open && $currentTime > $close) {
            $isOpen = false;
        }
    }
}

?>

<div class="card">

<h2>
🏥 <?= htmlspecialchars($hospital['hospital_name']) ?>
</h2>

<div class="address">
📍 <?= htmlspecialchars($hospital['address'] ?? '') ?>
</div>

<?php if ($distance !== null): ?>

<div class="info distance">
📏 <?= $distance ?> km away
</div>

<?php endif; ?>


<div class="info">

🕐 Timings:

<strong>
<?= htmlspecialchars($hospital['opening_time']) ?>
-
<?= htmlspecialchars($hospital['closing_time']) ?>
</strong>

</div>


<div class="info">

Emergency:

<?php if ($hospital['emergency_available'] == 1): ?>

<span class="available">
🟢 Available
</span>

<?php else: ?>

<span class="unavailable">
🔴 Not Available
</span>

<?php endif; ?>

</div>


<div class="info">

Hospital Status:

<?php if ($isOpen): ?>

<span class="available">
🟢 Open
</span>

<?php else: ?>

<span class="unavailable">
🔴 Closed
</span>

<?php endif; ?>

</div>


<hr>


<h3>🛏 Bed Availability</h3>

<span class="badge">
General:
<strong>
<?= (int)$hospital['general_available'] ?>
/
<?= (int)$hospital['general_total'] ?>
</strong>
</span>

<span class="badge">
ICU:
<strong>
<?= (int)$hospital['icu_available'] ?>
/
<?= (int)$hospital['icu_total'] ?>
</strong>
</span>

<span class="badge">
Emergency:
<strong>
<?= (int)$hospital['emergency_available'] ?>
/
<?= (int)$hospital['emergency_total'] ?>
</strong>
</span>


<h3>👨‍⚕️ Doctors</h3>

<?php

$doctorStmt = $conn->prepare("
    SELECT doctor_name, specialization, available
    FROM hospital_doctors
    WHERE hospital_id = ?
");

$doctorStmt->bind_param("i", $hospital['id']);

$doctorStmt->execute();

$doctorResult = $doctorStmt->get_result();

if ($doctorResult->num_rows === 0):

?>

<p>No doctor information available.</p>

<?php else: ?>

<?php while ($doctor = $doctorResult->fetch_assoc()): ?>

<div class="badge">

👨‍⚕️
<?= htmlspecialchars($doctor['doctor_name']) ?>

<br>

Specialization:
<?= htmlspecialchars($doctor['specialization']) ?>

<br>

<?php if ($doctor['available'] == 1): ?>

<span class="available">
🟢 Available
</span>

<?php else: ?>

<span class="unavailable">
🔴 Unavailable
</span>

<?php endif; ?>

</div>

<?php endwhile; ?>

<?php endif; ?>


<form method="POST"
      action="select_hospital.php">

<input type="hidden"
       name="booking_code"
       value="<?= htmlspecialchars($booking['booking_code']) ?>">

<input type="hidden"
       name="hospital_id"
       value="<?= (int)$hospital['id'] ?>">

<button class="select-btn"
        type="submit">

🚑 SELECT THIS HOSPITAL

</button>

</form>

</div>

<?php endwhile; ?>

</div>

</div>

</body>
</html>