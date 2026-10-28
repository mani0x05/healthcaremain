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

$driverId = (int)($data["driver_id"] ?? 0);
$bookingId = (int)($data["booking_id"] ?? 0);

if ($driverId <= 0 || $bookingId <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "Driver ID and booking ID are required."
    ]);

    exit;
}


/* =========================
   REJECT REQUEST
========================= */

$stmt = $conn->prepare(
    "UPDATE ambulance_booking_requests
     SET request_status = 'rejected',
         responded_at = NOW()
     WHERE booking_id = ?
       AND driver_id = ?
       AND request_status = 'sent'"
);

$stmt->bind_param(
    "ii",
    $bookingId,
    $driverId
);

$stmt->execute();

$changed = $stmt->affected_rows;

$stmt->close();


if ($changed !== 1) {

    echo json_encode([
        "success" => false,
        "message" => "Request is no longer available."
    ]);

    exit;
}


/* =========================
   UPDATE BOOKING
========================= */

$stmt = $conn->prepare(
    "UPDATE ambulance_bookings
     SET status = 'searching'
     WHERE id = ?
       AND status = 'searching'"
);

$stmt->bind_param(
    "i",
    $bookingId
);

$stmt->execute();

$stmt->close();


echo json_encode([

    "success" => true,

    "message" =>
        "Booking request rejected.",

    "booking_id" =>
        $bookingId

]);

?>