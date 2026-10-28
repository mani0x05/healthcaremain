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
        "message" => "Invalid request."
    ]);

    exit;
}


$driverId =
    (int)($data["driver_id"] ?? 0);


if ($driverId <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "Driver ID is required."
    ]);

    exit;
}


/* =========================
   VERIFY DRIVER
========================= */

$stmt = $conn->prepare(
    "SELECT id
     FROM ambulance_drivers
     WHERE id = ?
     AND phone_verified = 1
     AND verification_status = 'verified'
     LIMIT 1"
);


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
        "message" => "Driver not found or not verified."
    ]);

    exit;
}


$stmt->close();


/* =========================
   GET ACTIVE REQUESTS
========================= */

$sql = "

SELECT

    r.id AS request_id,
    r.booking_id,
    r.driver_id,
    r.request_status,
    r.sent_at,

    b.booking_code,

    b.patient_name,
    b.patient_phone,

    b.emergency_type,

    b.pickup_address,
    b.pickup_latitude,
    b.pickup_longitude,

    b.hospital_name,
    b.hospital_latitude,
    b.hospital_longitude,

    b.status AS booking_status,

    b.requested_at

FROM ambulance_booking_requests r

INNER JOIN ambulance_bookings b
    ON r.booking_id = b.id

WHERE r.driver_id = ?

AND r.request_status = 'sent'

AND b.status = 'searching'

ORDER BY r.sent_at DESC

LIMIT 10

";


$stmt = $conn->prepare($sql);


if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" => "Unable to prepare request query."
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


$requests = [];


while ($row = $result->fetch_assoc()) {

    $requests[] = [

        "request_id" =>
            (int)$row["request_id"],

        "booking_id" =>
            (int)$row["booking_id"],

        "booking_code" =>
            $row["booking_code"],

        "patient_name" =>
            $row["patient_name"],

        "patient_phone" =>
            $row["patient_phone"],

        "emergency_type" =>
            $row["emergency_type"],

        "pickup_address" =>
            $row["pickup_address"],

        "pickup_latitude" =>
            $row["pickup_latitude"] !== null
                ? (float)$row["pickup_latitude"]
                : null,

        "pickup_longitude" =>
            $row["pickup_longitude"] !== null
                ? (float)$row["pickup_longitude"]
                : null,

        "hospital_name" =>
            $row["hospital_name"],

        "hospital_latitude" =>
            $row["hospital_latitude"] !== null
                ? (float)$row["hospital_latitude"]
                : null,

        "hospital_longitude" =>
            $row["hospital_longitude"] !== null
                ? (float)$row["hospital_longitude"]
                : null,

        "booking_status" =>
            $row["booking_status"],

        "request_status" =>
            $row["request_status"],

        "sent_at" =>
            $row["sent_at"],

        "requested_at" =>
            $row["requested_at"]

    ];
}


$stmt->close();


/* =========================
   SUCCESS
========================= */

echo json_encode([

    "success" => true,

    "count" =>
        count($requests),

    "requests" =>
        $requests

]);

?>
