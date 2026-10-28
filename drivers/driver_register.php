<?php

header("Content-Type: application/json");

require_once "db.php";


/* ==========================================
   ALLOW ONLY POST
========================================== */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);

    exit;
}


/* ==========================================
   READ JSON DATA
========================================== */

$rawData = file_get_contents("php://input");

$data = json_decode($rawData, true);


if (!is_array($data)) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid registration data."
    ]);

    exit;
}


/* ==========================================
   GET VALUES
========================================== */

$full_name =
    trim($data["full_name"] ?? "");

$phone =
    trim($data["phone"] ?? "");

$email =
    trim($data["email"] ?? "");

$password =
    $data["password"] ?? "";

$ambulance_number =
    strtoupper(
        trim($data["ambulance_number"] ?? "")
    );

$ambulance_type =
    trim($data["ambulance_type"] ?? "");

$experience_years =
    intval($data["experience_years"] ?? 0);


/* ==========================================
   REQUIRED FIELD CHECK
========================================== */

if (
    $full_name === "" ||
    $phone === "" ||
    $password === "" ||
    $ambulance_number === "" ||
    $ambulance_type === ""
) {

    echo json_encode([
        "success" => false,
        "message" => "Please fill all required fields."
    ]);

    exit;
}


/* ==========================================
   PHONE VALIDATION
========================================== */

if (!preg_match('/^[6-9][0-9]{9}$/', $phone)) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Enter a valid 10-digit Indian mobile number."
    ]);

    exit;
}


/* ==========================================
   PASSWORD VALIDATION
========================================== */

if (strlen($password) < 6) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Password must contain at least 6 characters."
    ]);

    exit;
}


/* ==========================================
   EXPERIENCE VALIDATION
========================================== */

if (
    $experience_years < 0 ||
    $experience_years > 50
) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Enter valid driving experience."
    ]);

    exit;
}


/* ==========================================
   🔐 BACKEND AMBULANCE VERIFICATION
========================================== */

$ambulanceCheck = $conn->prepare(
    "SELECT
        vehicle_number,
        vehicle_type,
        emergency_service,
        status
     FROM ambulance_vehicles
     WHERE UPPER(vehicle_number) = ?
     LIMIT 1"
);


if (!$ambulanceCheck) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Ambulance verification query failed."
    ]);

    exit;
}


$ambulanceCheck->bind_param(
    "s",
    $ambulance_number
);


$ambulanceCheck->execute();

$ambulanceResult =
    $ambulanceCheck->get_result();


/* Vehicle does not exist */

if ($ambulanceResult->num_rows === 0) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Ambulance verification failed. Vehicle not found."
    ]);

    $ambulanceCheck->close();

    exit;
}


$vehicle =
    $ambulanceResult->fetch_assoc();


/* Emergency service check */

if (
    strtoupper(
        trim($vehicle["emergency_service"])
    ) !== "YES"
) {

    echo json_encode([
        "success" => false,
        "message" =>
            "This vehicle is not registered as an emergency-service vehicle."
    ]);

    $ambulanceCheck->close();

    exit;
}


/* Active status check */

if (
    strtoupper(
        trim($vehicle["status"])
    ) !== "ACTIVE"
) {

    echo json_encode([
        "success" => false,
        "message" =>
            "This ambulance is currently inactive."
    ]);

    $ambulanceCheck->close();

    exit;
}


/* Backend verification successful */

$ambulanceCheck->close();


/* ==========================================
   CHECK DUPLICATE PHONE / AMBULANCE
========================================== */

$check = $conn->prepare(
    "SELECT
        id,
        phone,
        ambulance_number
     FROM ambulance_drivers
     WHERE phone = ?
        OR ambulance_number = ?
     LIMIT 1"
);


if (!$check) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Database query preparation failed."
    ]);

    exit;
}


$check->bind_param(
    "ss",
    $phone,
    $ambulance_number
);


$check->execute();

$result =
    $check->get_result();


if ($result->num_rows > 0) {

    $existing =
        $result->fetch_assoc();


    if ($existing["phone"] === $phone) {

        echo json_encode([
            "success" => false,
            "message" =>
                "This mobile number is already registered."
        ]);

    } else {

        echo json_encode([
            "success" => false,
            "message" =>
                "This ambulance number is already registered."
        ]);

    }


    $check->close();

    exit;
}


$check->close();


/* ==========================================
   EMAIL CHECK
========================================== */

if ($email !== "") {

    if (!filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    )) {

        echo json_encode([
            "success" => false,
            "message" =>
                "Enter a valid email address."
        ]);

        exit;
    }


    $emailCheck = $conn->prepare(
        "SELECT id
         FROM ambulance_drivers
         WHERE email = ?
         LIMIT 1"
    );


    if (!$emailCheck) {

        echo json_encode([
            "success" => false,
            "message" =>
                "Email verification query failed."
        ]);

        exit;
    }


    $emailCheck->bind_param(
        "s",
        $email
    );


    $emailCheck->execute();

    $emailResult =
        $emailCheck->get_result();


    if ($emailResult->num_rows > 0) {

        echo json_encode([
            "success" => false,
            "message" =>
                "This email is already registered."
        ]);

        $emailCheck->close();

        exit;
    }


    $emailCheck->close();
}


/* ==========================================
   HASH PASSWORD
========================================== */

$password_hash =
    password_hash(
        $password,
        PASSWORD_DEFAULT
    );


/* ==========================================
   INSERT DRIVER
========================================== */

$stmt = $conn->prepare(
    "INSERT INTO ambulance_drivers
    (
        full_name,
        phone,
        email,
        password_hash,
        ambulance_number,
        ambulance_type,
        experience_years
    )
    VALUES (?, ?, ?, ?, ?, ?, ?)"
);


if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Database query preparation failed."
    ]);

    exit;
}


$stmt->bind_param(
    "ssssssi",
    $full_name,
    $phone,
    $email,
    $password_hash,
    $ambulance_number,
    $ambulance_type,
    $experience_years
);


/* ==========================================
   EXECUTE REGISTRATION
========================================== */

if ($stmt->execute()) {

    $driver_id =
        $conn->insert_id;


    echo json_encode([
        "success" => true,
        "message" =>
            "Registration successful. Please verify OTP.",
        "driver_id" =>
            $driver_id,
        "phone" =>
            $phone
    ]);

} else {

    echo json_encode([
        "success" => false,
        "message" =>
            "Registration failed. Please try again."
    ]);
}


$stmt->close();

$conn->close();

?>