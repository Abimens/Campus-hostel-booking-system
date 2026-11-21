<?php
session_start();
require "connection.php";

$type = isset($_GET["room_type"]) ? trim($_GET["room_type"]) : "";
$max_price = isset($_GET["max_price"]) ? trim($_GET["max_price"]) : "";
$is_student = isset($_SESSION["role"]) && $_SESSION["role"] === "student";

$sql = "SELECT r.Room_ID, h.Hostel_Name, h.Location, r.Room_Number, r.Room_Type,
               r.Price, r.Capacity, r.Availability
        FROM Rooms r
        JOIN Hostels h ON r.Hostel_ID = h.Hostel_ID
        WHERE 1=1";
$types = "";
$params = [];

if ($type !== "") {
    $sql .= " AND r.Room_Type = ?";
    $types .= "s";
    $params[] = $type;
}
if ($max_price !== "" && is_numeric($max_price)) {
    $sql .= " AND r.Price <= ?";
    $types .= "d";
    $params[] = $max_price;
}
$sql .= " ORDER BY h.Hostel_Name, r.Room_Number";

$stmt = $conn->prepare($sql);
if ($types !== "") {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rooms = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Browse Hostels</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h2>Hostels and Rooms</h2>

    <form method="get">
        <select name="room_type">
            <option value="">All room types</option>
            <?php foreach (["Single", "Double", "Triple", "Quad"] as $t): ?>
                <option value="<?php echo $t; ?>" <?php if ($type === $t) echo "selected"; ?>><?php echo $t; ?></option>
            <?php endforeach; ?>
        </select>
        <input type="number" name="max_price" placeholder="Maximum price" value="<?php echo htmlspecialchars($max_price); ?>">
        <button type="submit">Filter</button>
        <a href="hostels.php">Clear</a>
    </form>
    <br>

    <table border="1" cellpadding="8" cellspacing="0">
        <tr>
            <th>Hostel</th>
            <th>Location</th>
            <th>Room</th>
            <th>Type</th>
            <th>Price (GHS)</th>
            <th>Capacity</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
        <?php if ($rooms->num_rows === 0): ?>
            <tr><td colspan="8">No rooms match your search.</td></tr>
        <?php endif; ?>
        <?php while ($row = $rooms->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row["Hostel_Name"]); ?></td>
                <td><?php echo htmlspecialchars($row["Location"]); ?></td>
                <td><?php echo htmlspecialchars($row["Room_Number"]); ?></td>
                <td><?php echo htmlspecialchars($row["Room_Type"]); ?></td>
                <td><?php echo number_format($row["Price"], 2); ?></td>
                <td><?php echo (int)$row["Capacity"]; ?></td>
                <td><?php echo htmlspecialchars($row["Availability"]); ?></td>
                <td>
                    <?php if ($row["Availability"] === "Available"): ?>
                        <?php if ($is_student): ?>
                            <a href="book_room.php?room_id=<?php echo (int)$row["Room_ID"]; ?>">Book</a>
                        <?php else: ?>
                            <a href="login.php">Log in to book</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>

    <p><a href="index.php">Home</a></p>
</body>
</html>