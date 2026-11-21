<?php
session_start();
require "connection.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student") {
    header("Location: login.php");
    exit;
}

$student_id = $_SESSION["user_id"];
$room_id = isset($_REQUEST["room_id"]) ? (int)$_REQUEST["room_id"] : 0;
$error = "";
$success = "";

$stmt = $conn->prepare("SELECT r.Room_ID, r.Room_Number, r.Room_Type, r.Price, r.Capacity,
                               r.Availability, h.Hostel_Name, h.Location
                        FROM Rooms r
                        JOIN Hostels h ON r.Hostel_ID = h.Hostel_ID
                        WHERE r.Room_ID = ?");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();

if (!$room) {
    die("Room not found. <a href='hostels.php'>Back to hostels</a>");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $check_in = $_POST["check_in"];
    $check_out = $_POST["check_out"];
    $today = date("Y-m-d");

    if ($check_in === "" || $check_out === "") {
        $error = "Choose both dates.";
    } elseif ($check_in < $today) {
        $error = "Check-in date cannot be in the past.";
    } elseif ($check_out <= $check_in) {
        $error = "Check-out must be after check-in.";
    } else {
        $q = $conn->prepare("SELECT Booking_ID FROM Bookings
                             WHERE Student_ID = ? AND Status IN ('Pending','Approved')");
        $q->bind_param("i", $student_id);
        $q->execute();
        $q->store_result();

        if ($q->num_rows > 0) {
            $error = "You already have an active booking.";
        } else {
            $conn->begin_transaction();
            try {
                $lock = $conn->prepare("SELECT Availability FROM Rooms WHERE Room_ID = ? FOR UPDATE");
                $lock->bind_param("i", $room_id);
                $lock->execute();
                $current = $lock->get_result()->fetch_assoc();

                if (!$current || $current["Availability"] !== "Available") {
                    $conn->rollback();
                    $error = "Sorry, this room is no longer available.";
                } else {
                    $ins = $conn->prepare("INSERT INTO Bookings (Student_ID, Room_ID, Check_In, Check_Out)
                                           VALUES (?, ?, ?, ?)");
                    $ins->bind_param("iiss", $student_id, $room_id, $check_in, $check_out);
                    $ins->execute();

                    $upd = $conn->prepare("UPDATE Rooms SET Availability = 'Booked' WHERE Room_ID = ?");
                    $upd->bind_param("i", $room_id);
                    $upd->execute();

                    $conn->commit();
                    $success = "Booking submitted. Status: Pending.";
                    $room["Availability"] = "Booked";
                }
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Something went wrong. Try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Book Room</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h2>Book a Room</h2>
    <p>
        <strong><?php echo htmlspecialchars($room["Hostel_Name"]); ?></strong>,
        <?php echo htmlspecialchars($room["Location"]); ?><br>
        Room <?php echo htmlspecialchars($room["Room_Number"]); ?>
        (<?php echo htmlspecialchars($room["Room_Type"]); ?>),
        capacity <?php echo (int)$room["Capacity"]; ?><br>
        Price: GHS <?php echo number_format($room["Price"], 2); ?>
    </p>

    <?php if ($error) echo "<p style='color:red'>" . htmlspecialchars($error) . "</p>"; ?>
    <?php if ($success) echo "<p style='color:green'>" . htmlspecialchars($success) . "</p>"; ?>

    <?php if ($room["Availability"] === "Available" && !$success): ?>
        <form method="post">
            <input type="hidden" name="room_id" value="<?php echo (int)$room_id; ?>">
            <label>Check-in: <input type="date" name="check_in" required></label><br><br>
            <label>Check-out: <input type="date" name="check_out" required></label><br><br>
            <button type="submit">Submit booking</button>
        </form>
    <?php elseif (!$success): ?>
        <p>This room is not available.</p>
    <?php endif; ?>

    <p><a href="hostels.php">Back to hostels</a> | <a href="student_dashboard.php">Dashboard</a></p>
</body>
</html>