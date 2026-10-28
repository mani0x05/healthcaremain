<?php

header("Content-Type: application/json");

require_once "db.php";


/* Allow only POST requests */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);

    exit;
}


/* Read JSON data */

$data = json_decode(
    file_get_contents("php://input"),
    true
);


/* Check data */

if (!is_array($data)) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid request data."
    ]);

    exit;
}


/* Get phone number */

$phone = trim(
    $data["phone"] ?? ""
);


/* Validate Indian mobile number */
/* Exactly 10 digits and starts with 6-9 */

if (!preg_match('/^[6-9][0-9]{9}$/', $phone)) {

    echo json_encode([
        "success" => false,
        "message" => "Enter a valid 10-digit mobile number."
    ]);

    exit;
}


/* Check whether driver is registered */

$driverCheck = $conn->prepare(
    "SELECT id, full_name, phone_verified
     FROM ambulance_drivers
     WHERE phone = ?
     LIMIT 1"
);


if (!$driverCheck) {

    echo json_encode([
        "success" => false,
        "message" => "Database error."
    ]);

    exit;
}


$driverCheck->bind_param(
    "s",
    $phone
);

$driverCheck->execute();

$driverResult =
    $driverCheck->get_result();


/* Driver not found */

if ($driverResult->num_rows === 0) {

    echo json_encode([
        "success" => false,
        "message" =>
            "No driver registration found for this mobile number."
    ]);

    $driverCheck->close();

    exit;
}


$driver =
    $driverResult->fetch_assoc();


$driverCheck->close();


/*
    Generate 6-digit OTP
*/

$otp = str_pad(
    random_int(0, 999999),
    6,
    "0",
    STR_PAD_LEFT
);


/*
    Hash OTP before storing
*/

$otp_hash =
    password_hash(
        $otp,
        PASSWORD_DEFAULT
    );


/*
    OTP expires after 5 minutes
*/

$expires_at =
    date(
        "Y-m-d H:i:s",
        time() + 300
    );


/*
    Delete previous unverified OTP
*/

$delete = $conn->prepare(
    "DELETE FROM ambulance_otp
     WHERE phone = ?
     AND verified_at IS NULL"
);


if (!$delete) {

    echo json_encode([
        "success" => false,
        "message" => "Unable to process OTP request."
    ]);

    exit;
}


$delete->bind_param(
    "s",
    $phone
);

$delete->execute();

$delete->close();


/*
    Insert new OTP
*/

$stmt = $conn->prepare(
    "INSERT INTO ambulance_otp
    (
        phone,
        otp_hash,
        purpose,
        expires_at
    )
    VALUES (?, ?, 'registration', ?)"
);


if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" => "Unable to create OTP."
    ]);

    exit;
}


$stmt->bind_param(
    "sss",
    $phone,
    $otp_hash,
    $expires_at
);


/*
    Save OTP
*/

if ($stmt->execute()) {

    /*
        DEVELOPMENT MODE

        Local testing kosam OTP response lo
        dev_otp chupistunnam.

        Production lo real SMS gateway
        connect cheyyali.
    */

    echo json_encode([
        "success" => true,
        "message" =>
            "OTP generated successfully.",
        "dev_otp" =>
            $otp,
        "phone" =>
            $phone,
        "expires_in" =>
            300
    ]);

} else {

    echo json_encode([
        "success" => false,
        "message" =>
            "Unable to generate OTP."
    ]);
}


$stmt->close();

$conn->close();

?>