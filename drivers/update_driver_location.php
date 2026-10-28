<?php

header("Content-Type: application/json");

require_once "db.php";


/* =========================================
   ONLY POST REQUEST
========================================= */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    echo json_encode([
        "success" => false,
        "message" => "POST method required."
    ]);

    exit;
}


/* =========================================
   READ JSON
========================================= */

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


/* =========================================
   GET DATA
========================================= */

$driver_id = (int)($data["driver_id"] ?? 0);

$latitude = $data["latitude"] ?? null;

$longitude = $data["longitude"] ?? null;


/* =========================================
   VALIDATE DRIVER ID
========================================= */

if ($driver_id <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid driver ID."
    ]);

    exit;
}


/* =========================================
   VALIDATE LOCATION
========================================= */

if (
    !is_numeric($latitude) ||
    !is_numeric($longitude)
) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid GPS coordinates."
    ]);

    exit;
}


$latitude = (float)$latitude;

$longitude = (float)$longitude;


/* Latitude range */

if (
    $latitude < -90 ||
    $latitude > 90
) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid latitude."
    ]);

    exit;
}


/* Longitude range */

if (
    $longitude < -180 ||
    $longitude > 180
) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid longitude."
    ]);

    exit;
}


/* =========================================
   CHECK DRIVER
========================================= */

$stmt = $conn->prepare(
    "SELECT id, status
     FROM ambulance_drivers
     WHERE id = ?
     LIMIT 1"
);

$stmt->bind_param(
    "i",
    $driver_id
);

$stmt->execute();

$result = $stmt->get_result();

$driver = $result->fetch_assoc();

$stmt->close();


/* =========================================
   DRIVER NOT FOUND
========================================= */

if (!$driver) {

    echo json_encode([
        "success" => false,
        "message" => "Driver not found."
    ]);

    exit;
}


/* =========================================
   DRIVER MUST BE ONLINE
========================================= */

if ($driver["status"] !== "online") {

    echo json_encode([
        "success" => false,
        "message" =>
        "Driver is offline. Location not updated."
    ]);

    exit;
}


/* =========================================
   UPDATE DRIVER LOCATION
========================================= */

$stmt = $conn->prepare(
    "UPDATE ambulance_drivers
     SET
        latitude = ?,
        longitude = ?,
        last_location_at = NOW(),
        updated_at = NOW()
     WHERE id = ?"
);

$stmt->bind_param(
    "ddi",
    $latitude,
    $longitude,
    $driver_id
);


if (!$stmt->execute()) {

    $stmt->close();

    echo json_encode([
        "success" => false,
        "message" =>
        "Failed to update location."
    ]);

    exit;
}

$stmt->close();


/* =========================================
   SUCCESS
========================================= */

echo json_encode([

    "success" => true,

    "message" =>
        "Driver location updated successfully.",

    "driver_id" =>
        $driver_id,

    "latitude" =>
        $latitude,

    "longitude" =>
        $longitude,

    "updated_at" =>
        date("Y-m-d H:i:s")

]);

?>