<?php
session_start();
require "connection.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "manager") {
    header("Location: login.php");
    exit;
}

$manager_id = $_SESSION["user_id"];
$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "approve" || $action === "reject") {
        $booking_id = (int)$_POST["booking_id"];

        $q = $conn->prepare("SELECT b.Room_ID FROM Bookings b
                             JOIN Rooms r ON b.Room_ID = r.Room_ID
                             JOIN Hostels h ON r.Hostel_ID = h.Hostel_ID
                             WHERE b.Booking_ID = ? AND h.Manager_ID = ? AND b.Status = 'Pending'");
        $q->bind_param("ii", $booking_id, $manager_id);
        $q->execute();
        $booking = $q->get_result()->fetch_assoc();

        if (!$booking) {
            $error = "Booking not found or already handled.";
        } else {
            $status = $action === "approve" ? "Approved" : "Rejected";
            $conn->begin_transaction();
            try {
                $u = $conn->prepare("UPDATE Bookings SET Status = ? WHERE Booking_ID = ?");
                $u->bind_param("si", $status, $booking_id);
                $u->execute();

                if ($action === "reject") {
                    $r = $conn->prepare("UPDATE Rooms SET Availability = 'Available' WHERE Room_ID = ?");
                    $r->bind_param("i", $booking["Room_ID"]);
                    $r->execute();
                }
                $conn->commit();
                $message = "Booking " . strtolower($status) . ".";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Something went wrong. Try again.";
            }
        }
    }

    if ($action === "add_room") {
        $hostel_id = (int)$_POST["hostel_id"];
        $room_number = trim($_POST["room_number"]);
        $room_type = trim($_POST["room_type"]);
        $price = $_POST["price"];
        $capacity = (int)$_POST["capacity"];

        $own = $conn->prepare("SELECT Hostel_ID FROM Hostels WHERE Hostel_ID = ? AND Manager_ID = ?");
        $own->bind_param("ii", $hostel_id, $manager_id);
        $own->execute();
        $own->store_result();

        if ($own->num_rows === 0) {
            $error = "Invalid hostel.";
        } elseif ($room_number === "" || !is_numeric($price) || $price <= 0 || $capacity < 1) {
            $error = "Fill in all room details correctly.";
        } else {
            try {
                $ins = $conn->prepare("INSERT INTO Rooms (Hostel_ID, Room_Number, Room_Type, Price, Capacity)
                                       VALUES (?, ?, ?, ?, ?)");
                $ins->bind_param("issdi", $hostel_id, $room_number, $room_type, $price, $capacity);
                $ins->execute();
                $message = "Room added.";
            } catch (Exception $e) {
                $error = "That room number already exists in this hostel.";
            }
        }
    }
}

$hs = $conn->prepare("SELECT Hostel_ID, Hostel_Name FROM Hostels WHERE Manager_ID = ?");
$hs->bind_param("i", $manager_id);
$hs->execute();
$hostels = $hs->get_result()->fetch_all(MYSQLI_ASSOC);

$pb = $conn->prepare("SELECT b.Booking_ID, s.Full_Name, s.Phone, h.Hostel_Name, r.Room_Number,
                             b.Check_In, b.Check_Out, b.Booking_Date
                      FROM Bookings b
                      JOIN Students s ON b.Student_ID = s.Student_ID
                      JOIN Rooms r ON b.Room_ID = r.Room_ID
                      JOIN Hostels h ON r.Hostel_ID = h.Hostel_ID
                      WHERE h.Manager_ID = ? AND b.Status = 'Pending'
                      ORDER BY b.Booking_Date");
$pb->bind_param("i", $manager_id);
$pb->execute();
$pending = $pb->get_result();

$oc = $conn->prepare("SELECT h.Hostel_Name, r.Room_Number, r.Room_Type, r.Price, r.Availability,
                             s.Full_Name AS Occupant, b.Status
                      FROM Rooms r
                      JOIN Hostels h ON r.Hostel_ID = h.Hostel_ID
                      LEFT JOIN Bookings b ON b.Room_ID = r.Room_ID AND b.Status IN ('Pending','Approved')
                      LEFT JOIN Students s ON b.Student_ID = s.Student_ID
                      WHERE h.Manager_ID = ?
                      ORDER BY h.Hostel_Name, r.Room_Number");
$oc->bind_param("i", $manager_id);
$oc->execute();
$occupancy = $oc->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manager Dashboard</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h2>Manager Dashboard</h2>
    <p>Welcome, <?php echo htmlspecialchars($_SESSION["name"]); ?> | <a href="logout.php">Log out</a></p>

    <?php if ($message) echo "<p style='color:green'>" . htmlspecialchars($message) . "</p>"; ?>
    <?php if ($error) echo "<p style='color:red'>" . htmlspecialchars($error) . "</p>"; ?>

    <h3>Pending bookings</h3>
    <table border="1" cellpadding="8" cellspacing="0">
        <tr>
            <th>Student</th><th>Phone</th><th>Hostel</th><th>Room</th>
            <th>Check-in</th><th>Check-out</th><th>Action</th>
        </tr>
        <?php if ($pending->num_rows === 0): ?>
            <tr><td colspan="7">No pending bookings.</td></tr>
        <?php endif; ?>
        <?php while ($p = $pending->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($p["Full_Name"]); ?></td>
                <td><?php echo htmlspecialchars($p["Phone"]); ?></td>
                <td><?php echo htmlspecialchars($p["Hostel_Name"]); ?></td>
                <td><?php echo htmlspecialchars($p["Room_Number"]); ?></td>
                <td><?php echo htmlspecialchars($p["Check_In"]); ?></td>
                <td><?php echo htmlspecialchars($p["Check_Out"]); ?></td>
                <td>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="booking_id" value="<?php echo (int)$p["Booking_ID"]; ?>">
                        <button type="submit" name="action" value="approve">Approve</button>
                        <button type="submit" name="action" value="reject">Reject</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>

    <h3>Add a room</h3>
    <form method="post">
        <input type="hidden" name="action" value="add_room">
        <select name="hostel_id" required>
            <?php foreach ($hostels as $h): ?>
                <option value="<?php echo (int)$h["Hostel_ID"]; ?>"><?php echo htmlspecialchars($h["Hostel_Name"]); ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="room_number" placeholder="Room number" required>
        <select name="room_type">
            <option>Single</option><option>Double</option><option>Triple</option><option>Quad</option>
        </select>
        <input type="number" name="price" step="0.01" min="1" placeholder="Price" required>
        <input type="number" name="capacity" min="1" placeholder="Capacity" required>
        <button type="submit">Add room</button>
    </form>

    <h3>Occupancy</h3>
    <table border="1" cellpadding="8" cellspacing="0">
        <tr>
            <th>Hostel</th><th>Room</th><th>Type</th><th>Price (GHS)</th>
            <th>Status</th><th>Student</th>
        </tr>
        <?php while ($o = $occupancy->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($o["Hostel_Name"]); ?></td>
                <td><?php echo htmlspecialchars($o["Room_Number"]); ?></td>
                <td><?php echo htmlspecialchars($o["Room_Type"]); ?></td>
                <td><?php echo number_format($o["Price"], 2); ?></td>
                <td><?php echo htmlspecialchars($o["Availability"]); ?></td>
                <td><?php echo $o["Occupant"] ? htmlspecialchars($o["Occupant"]) . " (" . htmlspecialchars($o["Status"]) . ")" : "-"; ?></td>
            </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>