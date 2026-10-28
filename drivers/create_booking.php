<?php

header("Content-Type: application/json");

require_once "db.php";


/* =========================
   ONLY POST REQUEST
========================= */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    echo json_encode([
        "success" => false,
        "message" => "POST method required."
    ]);

    exit;
}


/* =========================
   READ JSON
========================= */

$data = json_decode(
    file_get_contents("php://input"),
    true
);


if (!is_array($data)) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid request data."
    ]);

    exit;
}


/* =========================
   GET DATA
========================= */

$driverId = (int)($data["driver_id"] ?? 0);

$patientName =
    trim($data["patient_name"] ?? "");

$patientPhone =
    trim($data["patient_phone"] ?? "");

$emergencyType =
    trim($data["emergency_type"] ?? "");

$pickupAddress =
    trim($data["pickup_address"] ?? "");

$pickupLatitude =
    $data["pickup_latitude"] ?? null;

$pickupLongitude =
    $data["pickup_longitude"] ?? null;

$hospitalName =
    trim($data["hospital_name"] ?? "");

$hospitalLatitude =
    $data["hospital_latitude"] ?? null;

$hospitalLongitude =
    $data["hospital_longitude"] ?? null;


/* =========================
   VALIDATION
========================= */

if ($driverId <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid ambulance selected."
    ]);

    exit;
}


if ($patientName === "") {

    echo json_encode([
        "success" => false,
        "message" => "Patient name is required."
    ]);

    exit;
}


if (!preg_match("/^[6-9][0-9]{9}$/", $patientPhone)) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid Indian mobile number."
    ]);

    exit;
}


if ($emergencyType === "") {

    echo json_encode([
        "success" => false,
        "message" => "Emergency type is required."
    ]);

    exit;
}


if (
    !is_numeric($pickupLatitude) ||
    !is_numeric($pickupLongitude)
) {

    echo json_encode([
        "success" => false,
        "message" => "Valid pickup GPS location is required."
    ]);

    exit;
}


$pickupLatitude =
    (float)$pickupLatitude;

$pickupLongitude =
    (float)$pickupLongitude;


/* =========================
   CHECK COORDINATES
========================= */

if (
    $pickupLatitude < -90 ||
    $pickupLatitude > 90 ||
    $pickupLongitude < -180 ||
    $pickupLongitude > 180
) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid pickup coordinates."
    ]);

    exit;
}


/* =========================
   CHECK DRIVER
========================= */

$stmt = $conn->prepare(
    "SELECT
        id,
        full_name,
        phone,
        ambulance_number,
        ambulance_type,
        status

     FROM ambulance_drivers

     WHERE id = ?
     AND phone_verified = 1
     AND verification_status = 'verified'
     AND status = 'online'

     LIMIT 1"
);


if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" => "Database error."
    ]);

    exit;
}


$stmt->bind_param(
    "i",
    $driverId
);

$stmt->execute();

$result =
    $stmt->get_result();


if ($result->num_rows === 0) {

    $stmt->close();

    echo json_encode([
        "success" => false,
        "message" => "This ambulance is no longer available."
    ]);

    exit;
}


$driver =
    $result->fetch_assoc();

$stmt->close();


/* =========================
   GENERATE BOOKING CODE
========================= */

$bookingCode =
    "AMB" .
    date("YmdHis") .
    rand(100, 999);


/* =========================
   CREATE BOOKING
========================= */

$stmt = $conn->prepare(
    "INSERT INTO ambulance_bookings
    (
        booking_code,
        patient_name,
        patient_phone,
        emergency_type,
        pickup_address,
        pickup_latitude,
        pickup_longitude,
        hospital_name,
        hospital_latitude,
        hospital_longitude,
        driver_id,
        status
    )

    VALUES
    (
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        'searching'
    )"
);


if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" => "Unable to prepare booking."
    ]);

    exit;
}


/*
   Convert empty hospital values
   to NULL
*/

if ($hospitalName === "") {

    $hospitalName = null;
}


if (
    !is_numeric($hospitalLatitude) ||
    !is_numeric($hospitalLongitude)
) {

    $hospitalLatitude = null;
    $hospitalLongitude = null;

} else {

    $hospitalLatitude =
        (float)$hospitalLatitude;

    $hospitalLongitude =
        (float)$hospitalLongitude;
}


/*
   Bind values
*/

$stmt->bind_param(
    "sssssssssdi",
    $bookingCode,
    $patientName,
    $patientPhone,
    $emergencyType,
    $pickupAddress,
    $pickupLatitude,
    $pickupLongitude,
    $hospitalName,
    $hospitalLatitude,
    $hospitalLongitude,
    $driverId
);


/* =========================
   EXECUTE BOOKING
========================= */

if (!$stmt->execute()) {

    echo json_encode([
        "success" => false,
        "message" => "Unable to create ambulance booking."
    ]);

    $stmt->close();

    exit;
}


$bookingId =
    $stmt->insert_id;

$stmt->close();


/* =========================
   CREATE DRIVER REQUEST
========================= */

$stmt = $conn->prepare(
    "INSERT INTO ambulance_booking_requests
    (
        booking_id,
        driver_id,
        request_status
    )

    VALUES
    (
        ?,
        ?,
        'sent'
    )"
);


if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" => "Booking created but request preparation failed.",
        "booking_id" => (int)$bookingId,
        "booking_code" => $bookingCode
    ]);

    exit;
}


$stmt->bind_param(
    "ii",
    $bookingId,
    $driverId
);


if (!$stmt->execute()) {

    echo json_encode([
        "success" => false,
        "message" => "Booking created but driver request failed.",
        "booking_id" => (int)$bookingId,
        "booking_code" => $bookingCode
    ]);

    $stmt->close();

    exit;
}


$stmt->close();


/* =========================
   SUCCESS RESPONSE
========================= */

echo json_encode([

    "success" => true,

    "message" =>
        "Ambulance request sent successfully.",

    "booking_id" =>
        (int)$bookingId,

    "booking_code" =>
        $bookingCode,

    "status" =>
        "searching",

    "driver" => [

        "id" =>
            (int)$driver["id"],

        "name" =>
            $driver["full_name"],

        "phone" =>
            $driver["phone"],

        "ambulance_number" =>
            $driver["ambulance_number"],

        "ambulance_type" =>
            $driver["ambulance_type"]

    ]

]);

?>