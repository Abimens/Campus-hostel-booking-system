<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$path = file_exists(__DIR__ . "/../connection.php")
    ? __DIR__ . "/../connection.php"
    : __DIR__ . "/../src/connection.php";
require $path;

$passed = 0;
$failed = 0;

function check($name, $result) {
    global $passed, $failed;
    if ($result) {
        $passed++;
        echo "PASS: $name\n";
    } else {
        $failed++;
        echo "FAIL: $name\n";
    }
}

function rejected(callable $fn) {
    try {
        $fn();
        return false;
    } catch (mysqli_sql_exception $e) {
        return true;
    }
}

$conn->begin_transaction();

// Test data (rolled back at the end)
$conn->query("INSERT INTO Managers (Full_Name, Email, Phone, Password)
              VALUES ('Test Manager', 'test_manager@example.com', '0200000000', 'x')");
$manager_id = $conn->insert_id;

$conn->query("INSERT INTO Students (Full_Name, Email, Phone, Password)
              VALUES ('Test Student', 'test_student@example.com', '0200000001', 'x')");
$student_id = $conn->insert_id;

$conn->query("INSERT INTO Hostels (Hostel_Name, Location, Manager_ID)
              VALUES ('Test Hostel', 'Test Location', $manager_id)");
$hostel_id = $conn->insert_id;

$conn->query("INSERT INTO Rooms (Hostel_ID, Room_Number, Room_Type, Price, Capacity)
              VALUES ($hostel_id, 'T1', 'Single', 1000.00, 1)");
$room_id = $conn->insert_id;

// Test 1: passwords are hashed and can be verified
$hash = password_hash("secret123", PASSWORD_DEFAULT);
check("Password is stored as a hash, not plain text", $hash !== "secret123");
check("Correct password verifies against the hash", password_verify("secret123", $hash));
check("Wrong password fails verification", !password_verify("wrong", $hash));

// Test 2: two students cannot share an email
check("Duplicate student email is rejected", rejected(function () use ($conn) {
    $conn->query("INSERT INTO Students (Full_Name, Email, Phone, Password)
                  VALUES ('Another', 'test_student@example.com', '0200000002', 'x')");
}));

// Test 3: a hostel cannot have two rooms with the same number
check("Duplicate room number in the same hostel is rejected", rejected(function () use ($conn, $hostel_id) {
    $conn->query("INSERT INTO Rooms (Hostel_ID, Room_Number, Room_Type, Price, Capacity)
                  VALUES ($hostel_id, 'T1', 'Double', 900.00, 2)");
}));

// Test 4: a booking cannot be made for a student who does not exist
check("Booking for a non-existent student is rejected", rejected(function () use ($conn, $room_id) {
    $conn->query("INSERT INTO Bookings (Student_ID, Room_ID, Check_In, Check_Out)
                  VALUES (999999, $room_id, '2027-01-10', '2027-05-10')");
}));

// Test 5: a new room is Available by default
$r = $conn->query("SELECT Availability FROM Rooms WHERE Room_ID = $room_id")->fetch_assoc();
check("New room is Available by default", $r["Availability"] === "Available");

// Test 6: a new booking starts as Pending
$conn->query("INSERT INTO Bookings (Student_ID, Room_ID, Check_In, Check_Out)
              VALUES ($student_id, $room_id, '2027-01-10', '2027-05-10')");
$b = $conn->query("SELECT Status FROM Bookings WHERE Booking_ID = " . $conn->insert_id)->fetch_assoc();
check("New booking starts with status Pending", $b["Status"] === "Pending");

$conn->rollback();

echo "\nResults: $passed passed, $failed failed\n";
exit($failed > 0 ? 1 : 0);