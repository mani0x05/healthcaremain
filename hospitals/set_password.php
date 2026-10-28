
<?php
require_once "db.php";

$newPassword = "password";
$hash = password_hash($newPassword, PASSWORD_DEFAULT);

$stmt = $conn->prepare(
    "UPDATE hospitals SET password = ?"
);
$stmt->bind_param("s", $hash);

if ($stmt->execute()) {
    echo "Test passwords updated successfully.";
} else {
    echo "Update failed: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
