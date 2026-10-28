<?php

header("Content-Type: application/json");

require_once "db.php";

$driver_id =
    intval($_GET["id"] ?? 0);


$stmt =
    $conn->prepare(
        "SELECT
            id,
            full_name,
            phone,
            email,
            ambulance_number,
            ambulance_type,
            experience_years,
            phone_verified,
            verification_status,
            status,
            latitude,
            longitude
         FROM ambulance_drivers
         WHERE id = ?
         LIMIT 1"
    );

$stmt->bind_param(
    "i",
    $driver_id
);

$stmt->execute();

$result =
    $stmt->get_result();


if ($result->num_rows === 0) {

    echo json_encode([
        "success" => false,
        "message" =>
        "Driver not found."
    ]);

    exit;
}


$driver =
    $result->fetch_assoc();


echo json_encode([
    "success" => true,
    "driver" => $driver
]);

?>
