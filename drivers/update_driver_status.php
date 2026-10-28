<?php

header("Content-Type: application/json");

require "db.php";


/* Only POST allowed */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    echo json_encode([
        "success" => false,
        "message" => "POST method required."
    ]);

    exit;
}


/* Read JSON */

$data = json_decode(
    file_get_contents("php://input"),
    true
);


/* Get values */

$driver_id = (int)($data["driver_id"] ?? 0);

$status = $data["status"] ?? "";


/* Validate driver ID */

if ($driver_id <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid driver ID."
    ]);

    exit;
}


/* Validate status */

if (!in_array(
    $status,
    ["online", "offline"],
    true
)) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid status."
    ]);

    exit;
}


/* Check driver */

$stmt = $conn->prepare(
    "SELECT id, full_name, phone_verified,
            verification_status, status
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


/* Driver not found */

if (!$driver) {

    echo json_encode([
        "success" => false,
        "message" => "Driver not found."
    ]);

    exit;
}


/*
   Driver can go ONLINE only when:

   1. Phone verified
   2. Account verified
*/

if ($status === "online") {

    if ((int)$driver["phone_verified"] !== 1) {

        echo json_encode([
            "success" => false,
            "message" =>
            "Please verify your phone number first."
        ]);

        exit;
    }


    if ($driver["verification_status"] !== "verified") {

        echo json_encode([
            "success" => false,
            "message" =>
            "Your driver account is not verified yet."
        ]);

        exit;
    }

}


/* Update status */

$stmt = $conn->prepare(
    "UPDATE ambulance_drivers
     SET status = ?, updated_at = NOW()
     WHERE id = ?"
);

$stmt->bind_param(
    "si",
    $status,
    $driver_id
);


if (!$stmt->execute()) {

    echo json_encode([
        "success" => false,
        "message" =>
        "Failed to update driver status."
    ]);

    $stmt->close();

    exit;
}

$stmt->close();


/* Success */

echo json_encode([

    "success" => true,

    "message" =>
        "Driver is now " . $status . ".",

    "driver_id" =>
        $driver_id,

    "status" =>
        $status

]);

?>