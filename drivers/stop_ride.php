<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

try {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    $driver_id = isset($data['driver_id']) ? (int)$data['driver_id'] : 0;
    $booking_code = trim($data['booking_code'] ?? '');

    if ($driver_id <= 0 || $booking_code === '') {
        echo json_encode([
            'success' => false,
            'message' => 'Driver ID and booking code are required.'
        ]);
        exit;
    }

    $stmt = $conn->prepare(
        "UPDATE ambulance_bookings
         SET status = 'completed', completed_at = NOW()
         WHERE booking_code = ? AND driver_id = ? AND status = 'accepted'"
    );

    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param('si', $booking_code, $driver_id);
    $stmt->execute();

    if ($stmt->affected_rows < 1) {
        echo json_encode([
            'success' => false,
            'message' => 'Active accepted booking was not found.'
        ]);
        $stmt->close();
        exit;
    }

    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Ride completed successfully.'
    ]);

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
