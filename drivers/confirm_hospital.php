<?php
header("Content-Type: application/json; charset=utf-8");

require_once "db.php";

function response_json($success, $message, $extra = []) {
    echo json_encode(array_merge([
        "success" => $success,
        "message" => $message
    ], $extra));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    response_json(false, "POST request required.");
}

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!is_array($data)) {
    $data = $_POST;
}

$booking_code = trim($data["booking_code"] ?? "");
$hospital_id  = intval($data["hospital_id"] ?? 0);
$driver_id    = intval($data["driver_id"] ?? 0);

if ($booking_code === "") {
    response_json(false, "Booking ID is required.");
}

if ($hospital_id <= 0) {
    response_json(false, "Hospital ID is required.");
}

if ($driver_id <= 0) {
    response_json(false, "Driver ID is required.");
}

/*
|--------------------------------------------------------------------------
| 1. Verify booking and driver
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT
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
        driver_id,
        status
    FROM ambulance_bookings
    WHERE booking_code = ?
    LIMIT 1
");

if (!$stmt) {
    response_json(false, "Booking query failed.");
}

$stmt->bind_param("s", $booking_code);
$stmt->execute();
$booking_result = $stmt->get_result();
$booking = $booking_result->fetch_assoc();
$stmt->close();

if (!$booking) {
    response_json(false, "Booking not found.");
}

if ((int)$booking["driver_id"] !== $driver_id) {
    response_json(false, "This booking is not assigned to this driver.");
}

/*
|--------------------------------------------------------------------------
| 2. Get selected hospital details
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT
        id,
        hospital_name,
        address,
        city,
        phone,
        latitude,
        longitude,
        emergency_available
    FROM hospitals
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    response_json(false, "Hospital query failed.");
}

$stmt->bind_param("i", $hospital_id);
$stmt->execute();
$hospital_result = $stmt->get_result();
$hospital = $hospital_result->fetch_assoc();
$stmt->close();

if (!$hospital) {
    response_json(false, "Selected hospital not found.");
}

/*
|--------------------------------------------------------------------------
| 3. Notify hospital
|
| The hospital dashboard receives this through incoming_patients.
| If the same booking was already sent, update it instead of creating
| duplicate notifications.
|--------------------------------------------------------------------------
*/
$patient_name = $booking["patient_name"];
$patient_phone = $booking["patient_phone"];
$emergency_type = $booking["emergency_type"];
$pickup_address = $booking["pickup_address"];
$ambulance_number = "";
$driver_name = "";
$driver_phone = "";

$stmt = $conn->prepare("
    SELECT
        full_name,
        phone,
        ambulance_number
    FROM ambulance_drivers
    WHERE id = ?
    LIMIT 1
");

if ($stmt) {
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $driver_result = $stmt->get_result();
    $driver = $driver_result->fetch_assoc();
    $stmt->close();

    if ($driver) {
        $driver_name = $driver["full_name"] ?? "";
        $driver_phone = $driver["phone"] ?? "";
        $ambulance_number = $driver["ambulance_number"] ?? "";
    }
}

/*
 * The incoming_patients table does not contain booking_code, so use the
 * existing patient/ambulance/driver fields and update the matching
 * active incoming record when possible.
 */
$existing_id = 0;

$stmt = $conn->prepare("
    SELECT id
    FROM incoming_patients
    WHERE hospital_id = ?
      AND patient_name = ?
      AND ambulance_number = ?
      AND status IN ('Incoming', 'Accepted', 'Arriving')
    ORDER BY id DESC
    LIMIT 1
");

if ($stmt) {
    $stmt->bind_param("iss", $hospital_id, $patient_name, $ambulance_number);
    $stmt->execute();
    $existing_result = $stmt->get_result();
    $existing = $existing_result->fetch_assoc();
    $stmt->close();

    if ($existing) {
        $existing_id = (int)$existing["id"];
    }
}

$required_specialization = $emergency_type;
$eta_minutes = 0;
$status = "Incoming";

if ($existing_id > 0) {
    $stmt = $conn->prepare("
        UPDATE incoming_patients
        SET
            patient_phone = patient_phone,
            emergency_type = ?,
            required_specialization = ?,
            ambulance_number = ?,
            driver_name = ?,
            driver_phone = ?,
            eta_minutes = ?,
            status = 'Incoming'
        WHERE id = ?
    ");

    /*
     * patient_phone is not a column in incoming_patients in the current
     * project schema, so this branch is intentionally not used if the
     * column is absent. Fall through to a safe update below.
     */
    if ($stmt) {
        $stmt->close();
    }

    $stmt = $conn->prepare("
        UPDATE incoming_patients
        SET
            emergency_type = ?,
            required_specialization = ?,
            ambulance_number = ?,
            driver_name = ?,
            driver_phone = ?,
            eta_minutes = ?,
            status = 'Incoming'
        WHERE id = ?
    ");

    if (!$stmt) {
        response_json(false, "Unable to update hospital notification.");
    }

    $stmt->bind_param(
        "sssssii",
        $emergency_type,
        $required_specialization,
        $ambulance_number,
        $driver_name,
        $driver_phone,
        $eta_minutes,
        $existing_id
    );

    if (!$stmt->execute()) {
        $stmt->close();
        response_json(false, "Hospital notification update failed.");
    }

    $stmt->close();
} else {
    $stmt = $conn->prepare("
        INSERT INTO incoming_patients
        (
            hospital_id,
            patient_name,
            age,
            gender,
            emergency_type,
            required_specialization,
            ambulance_number,
            driver_name,
            driver_phone,
            eta_minutes,
            status
        )
        VALUES
        (?, ?, 0, 'Unknown', ?, ?, ?, ?, ?, ?, 'Incoming')
    ");

    if (!$stmt) {
        response_json(false, "Unable to create hospital notification.");
    }

    $stmt->bind_param(
        "issssssi",
        $hospital_id,
        $patient_name,
        $emergency_type,
        $required_specialization,
        $ambulance_number,
        $driver_name,
        $driver_phone,
        $eta_minutes
    );

    if (!$stmt->execute()) {
        $stmt->close();
        response_json(false, "Hospital notification failed.");
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| 4. Update the ambulance booking with the selected hospital
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    UPDATE ambulance_bookings
    SET
        hospital_name = ?,
        hospital_latitude = ?,
        hospital_longitude = ?
    WHERE id = ?
");

if (!$stmt) {
    response_json(false, "Booking hospital update failed.");
}

$hospital_name = $hospital["hospital_name"];
$hospital_latitude = $hospital["latitude"];
$hospital_longitude = $hospital["longitude"];
$booking_id = (int)$booking["id"];

$stmt->bind_param(
    "sddi",
    $hospital_name,
    $hospital_latitude,
    $hospital_longitude,
    $booking_id
);

if (!$stmt->execute()) {
    $stmt->close();
    response_json(false, "Unable to save selected hospital.");
}

$stmt->close();

/*
|--------------------------------------------------------------------------
| 5. Return hospital coordinates so the browser can immediately open
|    driving navigation after the notification succeeds.
|--------------------------------------------------------------------------
*/
$destination = trim(
    ($hospital["latitude"] ?? "") . "," .
    ($hospital["longitude"] ?? "")
);

response_json(true, "Hospital notified successfully.", [
    "booking_code" => $booking_code,
    "hospital" => [
        "id" => (int)$hospital["id"],
        "name" => $hospital["hospital_name"],
        "address" => $hospital["address"],
        "city" => $hospital["city"],
        "phone" => $hospital["phone"],
        "latitude" => $hospital["latitude"],
        "longitude" => $hospital["longitude"]
    ],
    "navigation" => [
        "destination" => $destination,
        "hospital_name" => $hospital["hospital_name"]
    ]
]);
?>
