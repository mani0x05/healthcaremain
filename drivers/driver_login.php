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
   READ JSON DATA
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


$phone =
    trim($data["phone"] ?? "");

$password =
    $data["password"] ?? "";


/* =========================================
   VALIDATE PHONE
========================================= */

if (!preg_match(
    "/^[6-9][0-9]{9}$/",
    $phone
)) {

    echo json_encode([
        "success" => false,
        "message" =>
        "Please enter a valid 10-digit mobile number."
    ]);

    exit;
}


/* =========================================
   VALIDATE PASSWORD
========================================= */

if ($password === "") {

    echo json_encode([
        "success" => false,
        "message" =>
        "Please enter your password."
    ]);

    exit;
}


/* =========================================
   FIND DRIVER
========================================= */

$stmt = $conn->prepare(

    "SELECT *
     FROM ambulance_drivers
     WHERE phone = ?
     LIMIT 1"

);


if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" =>
        "Database query error."
    ]);

    exit;
}


$stmt->bind_param(
    "s",
    $phone
);

$stmt->execute();

$result =
    $stmt->get_result();


/* =========================================
   DRIVER NOT FOUND
========================================= */

if ($result->num_rows === 0) {

    $stmt->close();

    echo json_encode([
        "success" => false,
        "message" =>
        "Driver account not found."
    ]);

    exit;
}


$driver =
    $result->fetch_assoc();

$stmt->close();


/* =========================================
   CHECK PHONE VERIFICATION
========================================= */

if ((int)$driver["phone_verified"] !== 1) {

    echo json_encode([
        "success" => false,
        "message" =>
        "Please verify your phone number first."
    ]);

    exit;
}


/* =========================================
   CHECK PASSWORD
========================================= */

if (!password_verify(
    $password,
    $driver["password_hash"]
)) {

    echo json_encode([
        "success" => false,
        "message" =>
        "Incorrect password."
    ]);

    exit;
}


/* =========================================
   REMOVE PASSWORD FROM RESPONSE
========================================= */

unset(
    $driver["password_hash"]
);


/* =========================================
   LOGIN SUCCESS
========================================= */

echo json_encode([

    "success" => true,

    "message" =>
        "Login successful.",

    /* IMPORTANT:
       Dashboard needs this directly
    */

    "driver_id" =>
        (int)$driver["id"],

    "phone" =>
        $driver["phone"],

    "driver" =>
        $driver

]);

?>