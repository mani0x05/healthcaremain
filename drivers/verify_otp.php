<?php

header("Content-Type: application/json");

require "db.php";


/* ==============================
   ONLY POST
============================== */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    echo json_encode([
        "success" => false,
        "message" => "POST method required."
    ]);

    exit;
}


/* ==============================
   READ JSON
============================== */

$data = json_decode(
    file_get_contents("php://input"),
    true
);

$phone = trim($data["phone"] ?? "");
$otp   = trim($data["otp"] ?? "");


/* ==============================
   VALIDATION
============================== */

if (!preg_match("/^[6-9][0-9]{9}$/", $phone)) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid phone number."
    ]);

    exit;
}


if (!preg_match("/^[0-9]{6}$/", $otp)) {

    echo json_encode([
        "success" => false,
        "message" => "Please enter a valid 6-digit OTP."
    ]);

    exit;
}


/* ==============================
   FIND OTP
============================== */

$stmt = $conn->prepare(
    "SELECT id, otp_hash, expires_at, attempts
     FROM ambulance_otp
     WHERE phone = ?
       AND purpose = 'registration'
       AND verified_at IS NULL
     ORDER BY id DESC
     LIMIT 1"
);

$stmt->bind_param(
    "s",
    $phone
);

$stmt->execute();

$result = $stmt->get_result();

$otpRow = $result->fetch_assoc();

$stmt->close();


/* ==============================
   OTP NOT FOUND
============================== */

if (!$otpRow) {

    echo json_encode([
        "success" => false,
        "message" =>
        "OTP not found. Please request a new OTP."
    ]);

    exit;
}


/* ==============================
   CHECK ATTEMPTS
============================== */

if ((int)$otpRow["attempts"] >= 5) {

    echo json_encode([
        "success" => false,
        "message" =>
        "Too many attempts. Please request a new OTP."
    ]);

    exit;
}


/* ==============================
   CHECK EXPIRY
============================== */

if (strtotime($otpRow["expires_at"]) < time()) {

    echo json_encode([
        "success" => false,
        "message" =>
        "OTP has expired. Please request a new OTP."
    ]);

    exit;
}


/* ==============================
   VERIFY OTP
============================== */

if (!password_verify(
    $otp,
    $otpRow["otp_hash"]
)) {

    /* Increase attempts */

    $updateAttempts = $conn->prepare(
        "UPDATE ambulance_otp
         SET attempts = attempts + 1
         WHERE id = ?"
    );

    $updateAttempts->bind_param(
        "i",
        $otpRow["id"]
    );

    $updateAttempts->execute();

    $updateAttempts->close();


    echo json_encode([
        "success" => false,
        "message" => "Incorrect OTP."
    ]);

    exit;
}


/* ==============================
   MARK OTP VERIFIED
============================== */

$stmt = $conn->prepare(
    "UPDATE ambulance_otp
     SET verified_at = NOW()
     WHERE id = ?"
);

$stmt->bind_param(
    "i",
    $otpRow["id"]
);

$stmt->execute();

$stmt->close();


/* ==============================
   VERIFY DRIVER
============================== */

$stmt = $conn->prepare(
    "UPDATE ambulance_drivers
     SET
        phone_verified = 1,
        verification_status = 'verified',
        updated_at = NOW()
     WHERE phone = ?"
);

$stmt->bind_param(
    "s",
    $phone
);

$stmt->execute();

$driverUpdated =
    $stmt->affected_rows > 0;

$stmt->close();


/* ==============================
   DRIVER NOT FOUND
============================== */

if (!$driverUpdated) {

    echo json_encode([
        "success" => false,
        "message" =>
        "OTP verified, but driver account was not found."
    ]);

    exit;
}


/* ==============================
   GET DRIVER ID
============================== */

$stmt = $conn->prepare(
    "SELECT id, full_name, phone,
            ambulance_number,
            ambulance_type,
            verification_status
     FROM ambulance_drivers
     WHERE phone = ?
     LIMIT 1"
);

$stmt->bind_param(
    "s",
    $phone
);

$stmt->execute();

$result = $stmt->get_result();

$driver = $result->fetch_assoc();

$stmt->close();


/* ==============================
   SUCCESS
============================== */

echo json_encode([

    "success" => true,

    "message" =>
        "Phone verified successfully. Your driver account is now verified.",

    "driver_id" =>
        $driver["id"],

    "phone" =>
        $driver["phone"],

    "driver" =>
        $driver

]);

?>