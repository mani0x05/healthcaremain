<?php

include "db.php";

$hospital_id = intval($_GET['hospital_id'] ?? 0);

if ($hospital_id <= 0) {
    die("Hospital ID missing.");
}


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
   GET LIVE BEDS
===================================================== */

$stmt = $conn->prepare("
    SELECT *
    FROM hospital_beds
    WHERE hospital_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $hospital_id);
$stmt->execute();

$bedResult = $stmt->get_result();

$beds = $bedResult->fetch_assoc();


/* =====================================================
   GET INCOMING PATIENTS
===================================================== */

$stmt = $conn->prepare("
    SELECT *
    FROM incoming_patients
    WHERE hospital_id = ?
    AND status = 'Incoming'
    ORDER BY created_at DESC
");

$stmt->bind_param("i", $hospital_id);
$stmt->execute();

$patients = $stmt->get_result();

?>

<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
<?= htmlspecialchars($hospital['hospital_name']) ?>
- HealthResQ
</title>


<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f7fb;
    color: #222;
}


/* ================= HEADER ================= */

header {
    background: #0b7a75;
    color: white;
    padding: 22px 5%;
}

header h1 {
    margin: 0;
    font-size: 28px;
}

header p {
    margin-bottom: 0;
}


/* ================= CONTAINER ================= */

.container {
    width: 94%;
    max-width: 1250px;
    margin: 25px auto;
}


/* ================= STATS ================= */

.stats {
    display: grid;
    grid-template-columns:
        repeat(auto-fit, minmax(220px, 1fr));

    gap: 18px;
}

.stat {
    background: white;
    padding: 22px;
    border-radius: 15px;

    box-shadow:
        0 4px 15px rgba(0,0,0,.08);
}

.stat h3 {
    margin-top: 0;
}

.number {
    font-size: 32px;
    font-weight: bold;
    color: #0b7a75;
}


/* ================= SECTION ================= */

.section {
    background: white;
    margin-top: 25px;
    padding: 25px;
    border-radius: 15px;

    box-shadow:
        0 4px 15px rgba(0,0,0,.08);
}

.section-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.section-title h2 {
    margin: 0;
}


/* ================= PATIENT CARD ================= */

.patient-card {

    border: 1px solid #ddd;

    border-left:
        6px solid #d90429;

    border-radius: 12px;

    padding: 20px;

    margin-top: 18px;

    background: #fff;
}


/* ================= PATIENT HEADER ================= */

.patient-header {

    display: flex;

    justify-content:
        space-between;

    align-items: center;

    gap: 10px;

    flex-wrap: wrap;
}

.patient-header h3 {
    margin: 0;

    font-size: 22px;
}


/* ================= STATUS ================= */

.status {

    background: #fff3cd;

    color: #856404;

    padding: 7px 13px;

    border-radius: 20px;

    font-weight: bold;
}


/* ================= DETAILS GRID ================= */

.details-grid {

    display: grid;

    grid-template-columns:
        repeat(auto-fit, minmax(250px, 1fr));

    gap: 12px;

    margin-top: 18px;
}


.detail {

    background: #f7f9fc;

    padding: 13px;

    border-radius: 9px;
}

.detail strong {
    display: block;

    margin-bottom: 5px;

    color: #555;

    font-size: 13px;
}


/* ================= DRIVER BOX ================= */

.driver-box {

    margin-top: 20px;

    padding: 18px;

    border-radius: 12px;

    background: #eef8f7;

    border: 1px solid #cce8e5;
}

.driver-box h3 {
    margin-top: 0;

    color: #0b7a75;
}


/* ================= MAP BUTTON ================= */

.map-btn {

    display: inline-block;

    margin-top: 15px;

    background: #d90429;

    color: white;

    text-decoration: none;

    padding: 11px 18px;

    border-radius: 8px;

    font-weight: bold;
}


/* ================= EMPTY ================= */

.empty {

    text-align: center;

    padding: 35px;

    color: #666;

    font-size: 17px;
}


/* ================= REFRESH ================= */

.refresh {

    font-size: 13px;

    color: #777;
}

</style>

</head>


<body>


<!-- =================================================
     HEADER
================================================= -->

<header>

<h1>
🏥 <?= htmlspecialchars($hospital['hospital_name']) ?>
</h1>

<p>
HealthResQ Hospital Dashboard
</p>

</header>


<div class="container">


<!-- =================================================
     LIVE BED STATS
================================================= -->

<div class="stats">


<div class="stat">

<h3>
🛏️ General Beds
</h3>

<div class="number">

<?= (int)($beds['general_available'] ?? 0) ?>

/

<?= (int)($beds['general_total'] ?? 0) ?>

</div>

<p>
Available
</p>

</div>



<div class="stat">

<h3>
🚑 ICU Beds
</h3>

<div class="number">

<?= (int)($beds['icu_available'] ?? 0) ?>

/

<?= (int)($beds['icu_total'] ?? 0) ?>

</div>

<p>
Available
</p>

</div>



<div class="stat">

<h3>
🚨 Emergency Beds
</h3>

<div class="number">

<?= (int)($beds['emergency_available'] ?? 0) ?>

/

<?= (int)($beds['emergency_total'] ?? 0) ?>

</div>

<p>
Available
</p>

</div>


</div>



<!-- =================================================
     INCOMING PATIENTS
================================================= -->

<div class="section">


<div class="section-title">

<h2>
🚑 Incoming Patients
</h2>

<div class="refresh">
🔄 Page refreshes automatically
</div>

</div>


<?php if ($patients->num_rows === 0): ?>


<div class="empty">

🚑

<h3>
No Incoming Patients
</h3>

<p>
When an ambulance selects this hospital,
the patient details will appear here.
</p>

</div>


<?php else: ?>


<?php while ($patient = $patients->fetch_assoc()): ?>


<div class="patient-card">


<!-- PATIENT HEADER -->

<div class="patient-header">

<h3>

👤
<?= htmlspecialchars($patient['patient_name']) ?>

</h3>


<span class="status">

🚑
<?= htmlspecialchars($patient['status']) ?>

</span>

</div>



<!-- =================================================
     PATIENT DETAILS
================================================= -->

<div class="details-grid">


<div class="detail">

<strong>
🎫 BOOKING ID
</strong>

<?= htmlspecialchars($patient['booking_code']) ?>

</div>


<div class="detail">

<strong>
📞 PATIENT PHONE
</strong>

<?= htmlspecialchars($patient['patient_phone'] ?? 'Not available') ?>

</div>


<div class="detail">

<strong>
🚨 EMERGENCY TYPE
</strong>

<?= htmlspecialchars($patient['emergency_type'] ?? 'Not specified') ?>

</div>


<div class="detail">

<strong>
🏥 REQUIRED SPECIALIZATION
</strong>

<?= htmlspecialchars(
    $patient['required_specialization']
    ?? 'Not specified'
) ?>

</div>


<div class="detail">

<strong>
⏱️ ETA
</strong>

<?php

if (
    isset($patient['eta_minutes']) &&
    $patient['eta_minutes'] !== null
) {

    echo htmlspecialchars(
        $patient['eta_minutes']
    ) . " minutes";

} else {

    echo "Calculating...";

}

?>

</div>


<div class="detail">

<strong>
🕐 RECEIVED AT
</strong>

<?= htmlspecialchars(
    $patient['created_at']
) ?>

</div>


</div>



<!-- =================================================
     PICKUP DETAILS
================================================= -->

<h3>
📍 Patient Pickup Details
</h3>


<div class="detail">

<strong>
PICKUP ADDRESS
</strong>

<?= htmlspecialchars(
    $patient['pickup_address']
    ?? 'Location unavailable'
) ?>

</div>



<?php

if (
    $patient['pickup_latitude'] !== null &&
    $patient['pickup_longitude'] !== null
) {

    $patientMap =
        "https://www.google.com/maps/dir/?api=1"
        . "&destination="
        . urlencode(
            $patient['pickup_latitude']
            . ","
            . $patient['pickup_longitude']
        )
        . "&travelmode=driving";

?>

<a
    class="map-btn"
    href="<?= htmlspecialchars($patientMap) ?>"
    target="_blank">

📍 VIEW PATIENT LOCATION

</a>

<?php } ?>



<!-- =================================================
     DRIVER DETAILS
================================================= -->

<div class="driver-box">

<h3>
👨‍✈️ Ambulance Driver Details
</h3>


<div class="details-grid">


<div class="detail">

<strong>
DRIVER NAME
</strong>

<?= htmlspecialchars(
    $patient['driver_name']
    ?? 'Not available'
) ?>

</div>


<div class="detail">

<strong>
📞 DRIVER PHONE
</strong>

<?= htmlspecialchars(
    $patient['driver_phone']
    ?? 'Not available'
) ?>

</div>


<div class="detail">

<strong>
🚑 AMBULANCE NUMBER
</strong>

<?= htmlspecialchars(
    $patient['ambulance_number']
    ?? 'Not available'
) ?>

</div>


<div class="detail">

<strong>
🚑 AMBULANCE TYPE
</strong>

<?= htmlspecialchars(
    $patient['ambulance_type']
    ?? 'Not available'
) ?>

</div>


<div class="detail">

<strong>
🆔 DRIVER ID
</strong>

<?= htmlspecialchars(
    $patient['driver_id']
    ?? 'Not available'
) ?>

</div>


</div>


<!-- CALL DRIVER -->

<?php if (!empty($patient['driver_phone'])): ?>

<a
    class="map-btn"
    href="tel:<?= htmlspecialchars($patient['driver_phone']) ?>"
    style="background:#0b7a75;">

📞 CALL DRIVER

</a>

<?php endif; ?>


</div>


</div>


<?php endwhile; ?>


<?php endif; ?>


</div>


</div>


<script>

/*
   Refresh hospital dashboard every 5 seconds.
   This allows incoming patients and bed data
   to update without manually pressing refresh.
*/

setTimeout(function () {

    window.location.reload();

}, 5000);

</script>


</body>

</html>