<?php

header("Content-Type: application/json");

require_once "db.php";

$bookingId = (int)($_GET["booking_id"] ?? 0);

if ($bookingId <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "Booking ID is required."
    ]);

    exit;
}


/* =========================
   GET BOOKING + DRIVER
========================= */

$stmt = $conn->prepare(
    "SELECT
        b.id,
        b.booking_code,
        b.status,
        b.driver_id,

        d.full_name,
        d.phone,
        d.ambulance_number,
        d.ambulance_type

     FROM ambulance_bookings b

     LEFT JOIN ambulance_drivers d
     ON b.driver_id = d.id

     WHERE b.id = ?

     LIMIT 1"
);

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


$data = $result->fetch_assoc();

$stmt->close();


/* =========================
   RESPONSE
========================= */

$response = [

    "success" => true,

    "booking_id" =>
        (int)$data["id"],

    "booking_code" =>
        $data["booking_code"],

    "status" =>
        $data["status"]

];


if (
    $data["status"] === "accepted" &&
    !empty($data["driver_id"])
) {

    $response["driver"] = [

        "id" =>
            (int)$data["driver_id"],

        "name" =>
            $data["full_name"],

        "phone" =>
            $data["phone"],

        "ambulance_number" =>
            $data["ambulance_number"],

        "ambulance_type" =>
            $data["ambulance_type"]

    ];

}

echo json_encode($response);

?>

