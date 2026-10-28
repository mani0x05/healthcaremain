<?php

session_start();

require_once "db.php";

header("Content-Type: application/json");

$id = intval($_POST['id'] ?? 0);

$status = $_POST['status'] ?? '';

$allowed = [
    'Incoming',
    'Arrived',
    'Cancelled'
];

if ($id <= 0 || !in_array($status, $allowed)) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid request."
    ]);

    exit;
}

$stmt = $conn->prepare("
    UPDATE incoming_patients
    SET status = ?
    WHERE id = ?
");

$stmt->bind_param(
    "si",
    $status,
    $id
);

if ($stmt->execute()) {

    echo json_encode([
        "success" => true,
        "message" => "Status updated."
    ]);

} else {

    echo json_encode([
        "success" => false,
        "message" => "Update failed."
    ]);
}

?>