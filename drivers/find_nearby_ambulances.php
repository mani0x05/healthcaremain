<?php

header("Content-Type: application/json");

require_once "db.php";


/* =========================================
   ONLY POST
========================================= */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    echo json_encode([
        "success" => false,
        "message" => "POST method required."
    ]);

    exit;
}


/* =========================================
   READ JSON
========================================= */

$data = json_decode(
    file_get_contents("php://input"),
    true
);

if (!is_array($data)) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid request data."
    ]);

    exit;
}


/* =========================================
   USER LOCATION
========================================= */

$userLat = $data["latitude"] ?? null;
$userLng = $data["longitude"] ?? null;


/* =========================================
   VALIDATE LOCATION
========================================= */

if (
    !is_numeric($userLat) ||
    !is_numeric($userLng)
) {

    echo json_encode([
        "success" => false,
        "message" => "Valid location is required."
    ]);

    exit;
}


$userLat = (float)$userLat;
$userLng = (float)$userLng;


if (
    $userLat < -90 ||
    $userLat > 90 ||
    $userLng < -180 ||
    $userLng > 180
) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid GPS coordinates."
    ]);

    exit;
}


/* =========================================
   SEARCH NEARBY VERIFIED ONLINE DRIVERS

   Radius: 20 KM
========================================= */

$sql = "

SELECT

    id,
    full_name,
    phone,
    ambulance_number,
    ambulance_type,
    experience_years,
    latitude,
    longitude,

    (
        6371 * ACOS(

            COS(RADIANS(?))
            *
            COS(RADIANS(latitude))
            *
            COS(RADIANS(longitude) - RADIANS(?))
            +
            SIN(RADIANS(?))
            *
            SIN(RADIANS(latitude))

        )

    ) AS distance_km

FROM ambulance_drivers

WHERE

    phone_verified = 1

    AND verification_status = 'verified'

    AND status = 'online'

    AND latitude IS NOT NULL

    AND longitude IS NOT NULL

   AND last_location_at >= DATE_SUB(
    NOW(),
    INTERVAL 15 MINUTE
)

HAVING distance_km <= 20

ORDER BY distance_km ASC

LIMIT 20

";


$stmt = $conn->prepare($sql);


if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" =>
        "Database query failed."
    ]);

    exit;
}


/* latitude, longitude, latitude */

$stmt->bind_param(
    "ddd",
    $userLat,
    $userLng,
    $userLat
);


$stmt->execute();

$result = $stmt->get_result();


/* =========================================
   BUILD RESPONSE
========================================= */

$ambulances = [];


while ($row = $result->fetch_assoc()) {

    $ambulances[] = [

        "id" =>
            (int)$row["id"],

        "driver_name" =>
            $row["full_name"],

        "phone" =>
            $row["phone"],

        "ambulance_number" =>
            $row["ambulance_number"],

        "ambulance_type" =>
            $row["ambulance_type"],

        "experience_years" =>
            (int)$row["experience_years"],

        "latitude" =>
            (float)$row["latitude"],

        "longitude" =>
            (float)$row["longitude"],

        "distance_km" =>
            round(
                (float)$row["distance_km"],
                2
            )

    ];

}


$stmt->close();


/* =========================================
   RESPONSE
========================================= */

echo json_encode([

    "success" => true,

    "message" =>
        count($ambulances) > 0
        ? "Nearby ambulances found."
        : "No nearby ambulances available.",

    "count" =>
        count($ambulances),

    "ambulances" =>
        $ambulances

]);

?>