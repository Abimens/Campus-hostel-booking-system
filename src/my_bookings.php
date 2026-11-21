<?php
session_start();
require "connection.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student") {
    header("Location: login.php");
    exit;
}

$student_id = $_SESSION["user_id"];
$stmt = $conn->prepare("SELECT b.Booking_ID, h.Hostel_Name, r.Room_Number, r.Price,
                               b.Check_In, b.Check_Out, b.Status,
                               (SELECT COUNT(*) FROM Payments p
                                WHERE p.Booking_ID = b.Booking_ID AND p.Status = 'Success') AS Paid
                        FROM Bookings b
                        JOIN Rooms r ON b.Room_ID = r.Room_ID
                        JOIN Hostels h ON r.Hostel_ID = h.Hostel_ID
                        WHERE b.Student_ID = ?
                        ORDER BY b.Booking_Date DESC");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$bookings = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Bookings</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h2>My Bookings</h2>
    <table border="1" cellpadding="8" cellspacing="0">
        <tr>
            <th>Hostel</th><th>Room</th><th>Price (GHS)</th><th>Check-in</th>
            <th>Check-out</th><th>Booking status</th><th>Payment</th>
        </tr>
        <?php if ($bookings->num_rows === 0): ?>
            <tr><td colspan="7">You have no bookings yet.</td></tr>
        <?php endif; ?>
        <?php while ($b = $bookings->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($b["Hostel_Name"]); ?></td>
                <td><?php echo htmlspecialchars($b["Room_Number"]); ?></td>
                <td><?php echo number_format($b["Price"], 2); ?></td>
                <td><?php echo htmlspecialchars($b["Check_In"]); ?></td>
                <td><?php echo htmlspecialchars($b["Check_Out"]); ?></td>
                <td><?php echo htmlspecialchars($b["Status"]); ?></td>
                <td>
                    <?php if ($b["Paid"] > 0): ?>
                        Paid
                    <?php elseif (in_array($b["Status"], ["Pending", "Approved"])): ?>
                        <a href="pay.php?booking_id=<?php echo (int)$b["Booking_ID"]; ?>">Pay now</a>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
    <p><a href="student_dashboard.php">Dashboard</a></p>
</body>
</html>