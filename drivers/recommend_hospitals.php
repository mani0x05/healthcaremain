<?php

session_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);

header("Content-Type: application/json; charset=UTF-8");

require_once "db.php";


/* =========================
   INPUT
========================= */

$booking_code = isset($_POST['booking_code'])
    ? trim($_POST['booking_code'])
    : '';

$emergency_type = isset($_POST['emergency_type'])
    ? trim($_POST['emergency_type'])
    : '';

$patient_lat = null;
$patient_lon = null;


if (
    isset($_POST['latitude']) &&
    $_POST['latitude'] !== ''
) {
    $patient_lat = floatval($_POST['latitude']);
}


if (
    isset($_POST['longitude']) &&
    $_POST['longitude'] !== ''
) {
    $patient_lon = floatval($_POST['longitude']);
}


/* =========================
   VALIDATION
========================= */

if ($booking_code === '') {

    echo json_encode(array(
        "success" => false,
        "message" => "Booking code required."
    ));

    exit;
}


if ($emergency_type === '') {

    echo json_encode(array(
        "success" => false,
        "message" => "Emergency type required."
    ));

    exit;
}


/* =========================
   DATABASE CHECK
========================= */

if (!isset($conn)) {

    echo json_encode(array(
        "success" => false,
        "message" => "Database connection variable not found."
    ));

    exit;
}


if ($conn->connect_error) {

    echo json_encode(array(
        "success" => false,
        "message" => "Database connection failed: " .
                     $conn->connect_error
    ));

    exit;
}


/* =========================
   GET HOSPITALS
========================= */

$sql = "
SELECT
    h.id,
    h.hospital_name,
    h.address,
    h.city,
    h.phone,
    h.latitude,
    h.longitude,
    h.emergency_available,
    h.opening_time,
    h.closing_time,

    COALESCE(b.general_available, 0)
        AS general_available,

    COALESCE(b.icu_available, 0)
        AS icu_available,

    COALESCE(b.emergency_available, 0)
        AS emergency_available_beds,

    (
        SELECT COUNT(*)
        FROM hospital_doctors d1
        WHERE d1.hospital_id = h.id
    ) AS doctor_count,

    (
        SELECT COUNT(*)
        FROM hospital_doctors d2
        WHERE d2.hospital_id = h.id
        AND d2.available = 1
        AND LOWER(d2.specialization)
            LIKE CONCAT('%', LOWER(?), '%')
    ) AS matching_doctors

FROM hospitals h

LEFT JOIN hospital_beds b
    ON b.hospital_id = h.id

ORDER BY h.id ASC
";


$stmt = $conn->prepare($sql);


if (!$stmt) {

    echo json_encode(array(
        "success" => false,
        "message" => "SQL Prepare Error: " .
                     $conn->error
    ));

    exit;
}


$stmt->bind_param(
    "s",
    $emergency_type
);


if (!$stmt->execute()) {

    echo json_encode(array(
        "success" => false,
        "message" => "SQL Execute Error: " .
                     $stmt->error
    ));

    exit;
}


$result = $stmt->get_result();


if (!$result) {

    echo json_encode(array(
        "success" => false,
        "message" => "Could not get hospital results."
    ));

    exit;
}


$hospitals = array();

$current_time = date("H:i:s");


/* =========================
   PROCESS EACH HOSPITAL
========================= */

while ($row = $result->fetch_assoc()) {


    /* =====================
       OPEN / CLOSED
    ===================== */

    $open = true;

    $opening = $row['opening_time'];
    $closing = $row['closing_time'];


    if (
        !empty($opening) &&
        !empty($closing)
    ) {

        if ($opening <= $closing) {

            if (
                $current_time < $opening ||
                $current_time > $closing
            ) {
                $open = false;
            }

        } else {

            /* Overnight hospital */

            if (
                $current_time < $opening &&
                $current_time > $closing
            ) {
                $open = false;
            }
        }
    }


    /* =====================
       DISTANCE
    ===================== */

    $distance = null;


    if (
        $patient_lat !== null &&
        $patient_lon !== null &&
        $row['latitude'] !== null &&
        $row['longitude'] !== null
    ) {

        $lat1 = deg2rad($patient_lat);
        $lat2 = deg2rad(
            floatval($row['latitude'])
        );

        $lon1 = deg2rad($patient_lon);
        $lon2 = deg2rad(
            floatval($row['longitude'])
        );

        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;


        $a =
            sin($dlat / 2) *
            sin($dlat / 2)

            +

            cos($lat1) *
            cos($lat2) *
            sin($dlon / 2) *
            sin($dlon / 2);


        $c =
            2 *
            atan2(
                sqrt($a),
                sqrt(1 - $a)
            );


        $distance = 6371 * $c;
    }


    /* =====================
       SCORE
    ===================== */

    $score = 0;


    if ($open) {
        $score += 30;
    }


    if (
        intval($row['emergency_available']) == 1
    ) {
        $score += 20;
    }


    if (
        intval($row['matching_doctors']) > 0
    ) {
        $score += 30;
    }


    if (
        intval($row['emergency_available_beds']) > 0
    ) {
        $score += 15;
    }


    if (
        intval($row['icu_available']) > 0
    ) {
        $score += 10;
    }


    if (
        intval($row['general_available']) > 0
    ) {
        $score += 5;
    }


    if ($distance !== null) {

        if ($distance <= 5) {
            $score += 20;
        }

        else if ($distance <= 10) {
            $score += 15;
        }

        else if ($distance <= 20) {
            $score += 10;
        }
    }


    /* =====================
       FINAL VALUES
    ===================== */

    $row['open_now'] = $open;


    if ($distance !== null) {

        $row['distance_km'] =
            round($distance, 2);

    } else {

        $row['distance_km'] = "N/A";
    }


    $row['score'] = $score;


    $hospitals[] = $row;
}


$stmt->close();


/* =========================
   SORT BEST HOSPITAL FIRST
========================= */

usort(
    $hospitals,
    function($a, $b) {

        if (
            intval($a['score']) ==
            intval($b['score'])
        ) {

            return
                intval($a['id']) -
                intval($b['id']);
        }

        return
            intval($b['score']) -
            intval($a['score']);
    }
);


/* =========================
   RESULT
========================= */

if (count($hospitals) == 0) {

    echo json_encode(array(
        "success" => false,
        "message" => "No hospitals found."
    ));

    exit;
}


echo json_encode(array(
    "success" => true,
    "message" =>
        count($hospitals) .
        " hospitals found successfully.",
    "hospitals" => $hospitals
));

exit;

?>