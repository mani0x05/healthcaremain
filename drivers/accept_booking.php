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
   CHECK REQUEST
========================= */

$stmt = $conn->prepare(
    "SELECT
        id,
        booking_id,
        driver_id,
        request_status
     FROM ambulance_booking_requests
     WHERE booking_id = ?
       AND driver_id = ?
       AND request_status = 'sent'
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
        "message" => "This booking request is no longer available."
    ]);

    exit;
}

$request = $result->fetch_assoc();

$stmt->close();


/* =========================
   CHECK DRIVER
========================= */

$stmt = $conn->prepare(
    "SELECT
    id,
    full_name,
    phone,
    ambulance_number,
    ambulance_type,
    status
    FROM ambulance_drivers
     WHERE id = ?
       AND phone_verified = 1
       AND verification_status = 'verified'
     LIMIT 1"
);

$stmt->bind_param(
    "i",
    $driverId
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();

    echo json_encode([
        "success" => false,
        "message" => "Driver not found."
    ]);

    exit;
}

$driver = $result->fetch_assoc();

$stmt->close();


/* =========================
   CHECK BOOKING
========================= */

$stmt = $conn->prepare(
    "SELECT
        id,
        booking_code,
        patient_name,
        patient_phone,
        emergency_type,
        pickup_address,
        pickup_latitude,
        pickup_longitude,
        hospital_name,
        hospital_latitude,
        hospital_longitude,
        status
     FROM ambulance_bookings
     WHERE id = ?
       AND status = 'searching'
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
        "message" => "This booking has already been accepted or is unavailable."
    ]);

    exit;
}

$booking = $result->fetch_assoc();

$stmt->close();


/* =========================
   ACCEPT REQUEST
========================= */

$conn->begin_transaction();

try {

    /* Update request */

    $stmt = $conn->prepare(
        "UPDATE ambulance_booking_requests
         SET request_status = 'accepted',
             responded_at = NOW()
         WHERE id = ?
           AND request_status = 'sent'"
    );

    $stmt->bind_param(
        "i",
        $request["id"]
    );

    if (!$stmt->execute() || $stmt->affected_rows !== 1) {

        throw new Exception(
            "Unable to accept request."
        );
    }

    $stmt->close();


    /* Update booking */

    $stmt = $conn->prepare(
        "UPDATE ambulance_bookings
         SET status = 'accepted',
             driver_id = ?
         WHERE id = ?
           AND status = 'searching'"
    );

    $stmt->bind_param(
        "ii",
        $driverId,
        $bookingId
    );

    if (!$stmt->execute() || $stmt->affected_rows !== 1) {

        throw new Exception(
            "Unable to update booking."
        );
    }

    $stmt->close();


    /* Driver becomes busy */

    $stmt = $conn->prepare(
        "UPDATE ambulance_drivers
         SET status = 'busy',
             updated_at = NOW()
         WHERE id = ?"
    );

    $stmt->bind_param(
        "i",
        $driverId
    );

    if (!$stmt->execute()) {

        throw new Exception(
            "Unable to update driver status."
        );
    }

    $stmt->close();


    /* Commit */

    $conn->commit();


    echo json_encode([

        "success" => true,

        "message" =>
            "Booking accepted successfully.",

        "booking_id" =>
            $bookingId,

        "booking_code" =>
            $booking["booking_code"],

        "status" =>
            "accepted",

        "driver" => [

    "id" =>
        (int)$driver["id"],

    "name" =>
        $driver["full_name"],

    "phone" =>
        $driver["phone"],

    "ambulance_number" =>
        $driver["ambulance_number"],

    "ambulance_type" =>
        $driver["ambulance_type"]

],

        "patient" => [

            "name" =>
                $booking["patient_name"],

            "phone" =>
                $booking["patient_phone"],

            "emergency" =>
                $booking["emergency_type"],

            "pickup_address" =>
                $booking["pickup_address"],

            "pickup_latitude" =>
                $booking["pickup_latitude"],

            "pickup_longitude" =>
                $booking["pickup_longitude"],

            "hospital" =>
                $booking["hospital_name"]

        ]

    ]);

}
catch(Exception $e) {

    $conn->rollback();

    echo json_encode([

        "success" => false,

        "message" =>
            $e->getMessage()

    ]);

}

?>