<?php

header("Content-Type: application/json");
error_reporting(0);

function sendResponse($data)
{
    echo json_encode($data);
    exit;
}


/* ==============================
   DATABASE CONNECTION
   ============================== */

$host = "localhost";
$username = "root";
$password = "";
$database = "healthcare";

$conn = new mysqli(
    $host,
    $username,
    $password,
    $database
);


/* Database connection check */

if ($conn->connect_error) {

    sendResponse([
        "success" => false,
        "verified" => false,
        "message" => "Database connection failed."
    ]);
}

$conn->set_charset("utf8mb4");


/* ==============================
   REQUEST METHOD
   ============================== */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    sendResponse([
        "success" => false,
        "verified" => false,
        "message" => "Invalid request method."
    ]);
}


/* ==============================
   GET AMBULANCE NUMBER
   ============================== */

$ambulance_number = strtoupper(
    trim($_POST["ambulance_number"] ?? "")
);


if ($ambulance_number === "") {

    sendResponse([
        "success" => false,
        "verified" => false,
        "message" => "Please enter ambulance number."
    ]);
}


/* ==============================
   CHECK TABLE
   ============================== */

$tableCheck = $conn->query(
    "SHOW TABLES LIKE 'ambulance_vehicles'"
);

if (!$tableCheck || $tableCheck->num_rows === 0) {

    sendResponse([
        "success" => false,
        "verified" => false,
        "message" => "ambulance_vehicles table not found."
    ]);
}


/* ==============================
   SEARCH VEHICLE
   ============================== */

$stmt = $conn->prepare(
    "SELECT
        vehicle_number,
        vehicle_type,
        emergency_service,
        status
     FROM ambulance_vehicles
     WHERE UPPER(vehicle_number) = ?
     LIMIT 1"
);


if (!$stmt) {

    sendResponse([
        "success" => false,
        "verified" => false,
        "message" => "Database query preparation failed."
    ]);
}


$stmt->bind_param(
    "s",
    $ambulance_number
);


if (!$stmt->execute()) {

    sendResponse([
        "success" => false,
        "verified" => false,
        "message" => "Database query execution failed."
    ]);
}


$result = $stmt->get_result();


/* ==============================
   VEHICLE NOT FOUND
   ============================== */

if ($result->num_rows === 0) {

    $stmt->close();
    $conn->close();

    sendResponse([
        "success" => false,
        "verified" => false,
        "message" =>
            "Vehicle not found in verification database."
    ]);
}


/* ==============================
   GET VEHICLE
   ============================== */

$vehicle = $result->fetch_assoc();


/* ==============================
   EMERGENCY SERVICE CHECK
   ============================== */

if (
    strtoupper(trim($vehicle["emergency_service"]))
    !== "YES"
) {

    $stmt->close();
    $conn->close();

    sendResponse([
        "success" => false,
        "verified" => false,
        "message" =>
            "This vehicle is not registered as an emergency-service vehicle."
    ]);
}


/* ==============================
   STATUS CHECK
   ============================== */

if (
    strtoupper(trim($vehicle["status"]))
    !== "ACTIVE"
) {

    $stmt->close();
    $conn->close();

    sendResponse([
        "success" => false,
        "verified" => false,
        "message" =>
            "This ambulance is currently inactive."
    ]);
}


/* ==============================
   VERIFIED
   ============================== */

$stmt->close();
$conn->close();


sendResponse([
    "success" => true,
    "verified" => true,
    "message" =>
        "Ambulance verified successfully.",
    "vehicle_number" =>
        $vehicle["vehicle_number"],
    "vehicle_type" =>
        $vehicle["vehicle_type"],
    "emergency_service" =>
        $vehicle["emergency_service"],
    "status" =>
        $vehicle["status"]
]);

?>