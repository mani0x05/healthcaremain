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

$status = trim(
    $data["status"] ?? ""
);


if (
    $driverId <= 0 ||
    $bookingId <= 0 ||
    $status === ""
) {

    echo json_encode([
        "success" => false,
        "message" => "Driver, booking and status are required."
    ]);

    exit;
}


/*
Allowed status transitions
*/

$allowedStatuses = [

    "accepted",
    "arriving",
    "picked_up",
    "completed"

];


if (!in_array(
    $status,
    $allowedStatuses,
    true
)) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid booking status."
    ]);

    exit;
}


/* =========================================
   CHECK BOOKING
========================================= */

$stmt = $conn->prepare(
    "SELECT
        id,
        driver_id,
        status
     FROM ambulance_bookings
     WHERE id = ?
       AND driver_id = ?
     LIMIT 1"
);


$stmt->bind_param(
    "ii",
    $bookingId,
    $driverId
);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows === 0) {

    $stmt->close();

    echo json_encode([
        "success" => false,
        "message" => "Booking not found for this driver."
    ]);

    exit;
}


$booking = $result->fetch_assoc();

$stmt->close();


/* =========================================
   UPDATE BOOKING
========================================= */

if ($status === "completed") {

    $stmt = $conn->prepare(
        "UPDATE ambulance_bookings
         SET status = ?,
             completed_at = NOW()
         WHERE id = ?
           AND driver_id = ?"
    );

} else {

    $stmt = $conn->prepare(
        "UPDATE ambulance_bookings
         SET status = ?
         WHERE id = ?
           AND driver_id = ?"
    );

}


$stmt->bind_param(
    "sii",
    $status,
    $bookingId,
    $driverId
);


if (!$stmt->execute()) {

    echo json_encode([
        "success" => false,
        "message" => "Unable to update booking status."
    ]);

    $stmt->close();

    exit;
}


$stmt->close();


/* =========================================
   DRIVER STATUS
========================================= */

if ($status === "completed") {

    $stmt = $conn->prepare(
        "UPDATE ambulance_drivers
         SET status = 'online',
             updated_at = NOW()
         WHERE id = ?"
    );

    $stmt->bind_param(
        "i",
        $driverId
    );

    $stmt->execute();

    $stmt->close();

}


/* =========================================
   RESPONSE
========================================= */

echo json_encode([

    "success" => true,

    "message" =>
        "Booking status updated successfully.",

    "booking_id" =>
        $bookingId,

    "status" =>
        $status

]);

?>