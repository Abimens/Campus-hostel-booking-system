<?php
session_start();
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student") {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION["name"]); ?></h2>
    <p>You are logged in as a student.</p>
    <p><a href="hostels.php">Browse hostels</a></p>
    <p><a href="my_bookings.php">My bookings</a></p>
    <p><a href="logout.php">Log out</a></p>
</body>
</html>