<?php

require_once "db.php";

$hospital_name = "City Care Emergency Hospital";
$username = "citycare";
$password = "password";

$address = "Main Road";
$city = "Hyderabad";
$phone = "9876543210";

$latitude = 17.3850440;
$longitude = 78.4866710;

$hashed_password = password_hash(
    $password,
    PASSWORD_DEFAULT
);


/* Check existing hospital */

$stmt = $conn->prepare(
    "SELECT id FROM hospitals WHERE username = ? LIMIT 1"
);

$stmt->bind_param("s", $username);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows > 0) {

    die("
        <h2>Hospital already exists.</h2>
        <p>Username: citycare</p>
        <p>Delete this file after setup.</p>
    ");
}

$stmt->close();


/* Insert hospital */

$stmt = $conn->prepare(
    "INSERT INTO hospitals
    (
        hospital_name,
        username,
        password,
        address,
        city,
        phone,
        latitude,
        longitude,
        emergency_available
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)"
);

$stmt->bind_param(
    "ssssssdd",
    $hospital_name,
    $username,
    $hashed_password,
    $address,
    $city,
    $phone,
    $latitude,
    $longitude
);

if (!$stmt->execute()) {
    die("Hospital creation failed: " . $stmt->error);
}

$hospital_id = $stmt->insert_id;

$stmt->close();


/* Create bed record */

$stmt = $conn->prepare(
    "INSERT INTO hospital_beds
    (
        hospital_id,
        general_total,
        general_available,
        icu_total,
        icu_available,
        emergency_total,
        emergency_available
    )
    VALUES (?, 50, 30, 10, 5, 10, 7)"
);

$stmt->bind_param("i", $hospital_id);
$stmt->execute();
$stmt->close();


/* Add doctors */

$doctors = [
    ["Dr. Rahul Sharma", "Cardiology"],
    ["Dr. Priya Reddy", "Neurology"],
    ["Dr. Arjun Kumar", "General Medicine"]
];

foreach ($doctors as $doctor) {

    $stmt = $conn->prepare(
        "INSERT INTO hospital_doctors
        (hospital_id, doctor_name, specialization, available)
        VALUES (?, ?, ?, 1)"
    );

    $stmt->bind_param(
        "iss",
        $hospital_id,
        $doctor[0],
        $doctor[1]
    );

    $stmt->execute();
    $stmt->close();
}

?>

<!DOCTYPE html>

<html>

<head>

<title>Hospital Setup Complete</title>

<style>

body {
    font-family: Arial, sans-serif;
    background: #f4f7fb;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
}

.box {
    background: white;
    padding: 40px;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,.1);
    text-align: center;
}

h1 {
    color: #16803c;
}

.login {
    display: inline-block;
    margin-top: 20px;
    padding: 12px 25px;
    background: #1565c0;
    color: white;
    text-decoration: none;
    border-radius: 8px;
}

</style>

</head>

<body>

<div class="box">

    <h1>✅ Hospital Created Successfully</h1>

    <p><strong>Hospital:</strong> City Care Emergency Hospital</p>

    <p><strong>Username:</strong> citycare</p>

    <p><strong>Password:</strong> password</p>

    <p style="color:red;">
        Delete create_demo_hospital.php after running this setup.
    </p>

    <a class="login" href="hospitals_login.php">
        Go to Hospital Login
    </a>

</div>

</body>

</html>