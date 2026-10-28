<?php

header("Content-Type: application/json");

require_once "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        "success" => false,
        "message" => "POST method required."
    ]);
    exit;
}

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

$bookingId = (int)($data["booking_id"] ?? 0);

if ($bookingId <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Booking ID is required."
    ]);
    exit;
}


/* =========================================
   GET BOOKING + DRIVER
========================================= */

$sql = "
SELECT

    b.id AS booking_id,
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

    b.driver_id,
    b.status,

    b.requested_at,
    b.accepted_at,

    d.full_name AS driver_name,
    d.phone AS driver_phone,

    d.ambulance_number,
    d.ambulance_type,

    d.latitude AS driver_latitude,
    d.longitude AS driver_longitude,

    d.last_location_at,

    d.status AS driver_status

FROM ambulance_bookings b

LEFT JOIN ambulance_drivers d
    ON b.driver_id = d.id

WHERE b.id = ?

LIMIT 1
";


$stmt = $conn->prepare($sql);

if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" => "Database query failed."
    ]);

    exit;
}


$stmt->bind_param(
    "i",
    $bookingId
);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows === 0) {

    $stmt->close();

    echo json_encode([
        "success" => false,
        "message" => "Booking not found."
    ]);

    exit;
}


$row = $result->fetch_assoc();

$stmt->close();


/* =========================================
   RESPONSE
========================================= */

echo json_encode([

    "success" => true,

    "booking" => [

        "id" =>
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

        "status" =>
            $row["status"],

        "requested_at" =>
            $row["requested_at"],

        "accepted_at" =>
            $row["accepted_at"]

    ],

    "driver" => $row["driver_id"] !== null
        ? [

            "id" =>
                (int)$row["driver_id"],

            "name" =>
                $row["driver_name"],

            "phone" =>
                $row["driver_phone"],

            "ambulance_number" =>
                $row["ambulance_number"],

            "ambulance_type" =>
                $row["ambulance_type"],

            "latitude" =>
                $row["driver_latitude"] !== null
                    ? (float)$row["driver_latitude"]
                    : null,

            "longitude" =>
                $row["driver_longitude"] !== null
                    ? (float)$row["driver_longitude"]
                    : null,

            "last_location_at" =>
                $row["last_location_at"],

            "status" =>
                $row["driver_status"]

        ]
        : null

]);

?>