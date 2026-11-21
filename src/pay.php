<?php
session_start();
require "connection.php";
require "paystack_config.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student") {
    header("Location: login.php");
    exit;
}

$student_id = $_SESSION["user_id"];
$booking_id = isset($_GET["booking_id"]) ? (int)$_GET["booking_id"] : 0;

$stmt = $conn->prepare("SELECT b.Booking_ID, r.Price, s.Email
                        FROM Bookings b
                        JOIN Rooms r ON b.Room_ID = r.Room_ID
                        JOIN Students s ON b.Student_ID = s.Student_ID
                        WHERE b.Booking_ID = ? AND b.Student_ID = ?
                          AND b.Status IN ('Pending','Approved')");
$stmt->bind_param("ii", $booking_id, $student_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    die("Booking not found. <a href='my_bookings.php'>Back</a>");
}

$chk = $conn->prepare("SELECT Payment_ID FROM Payments WHERE Booking_ID = ? AND Status = 'Success'");
$chk->bind_param("i", $booking_id);
$chk->execute();
$chk->store_result();
if ($chk->num_rows > 0) {
    die("This booking is already paid. <a href='my_bookings.php'>Back</a>");
}

$reference = "HB" . time() . rand(1000, 9999);
$amount = $booking["Price"];

$ins = $conn->prepare("INSERT INTO Payments (Booking_ID, Amount, Paystack_Reference) VALUES (?, ?, ?)");
$ins->bind_param("ids", $booking_id, $amount, $reference);
$ins->execute();

$callback = "http://" . $_SERVER["HTTP_HOST"] . dirname($_SERVER["SCRIPT_NAME"]) . "/payment_callback.php";

$ch = curl_init("https://api.paystack.co/transaction/initialize");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
        "Content-Type: application/json"
    ],
    CURLOPT_POSTFIELDS => json_encode([
        "email" => $booking["Email"],
        "amount" => (int)round($amount * 100),
        "currency" => "GHS",
        "reference" => $reference,
        "callback_url" => $callback
    ])
]);
$response = curl_exec($ch);
$curl_error = curl_error($ch);
curl_close($ch);

if ($response === false) {
    die("Could not reach Paystack: " . htmlspecialchars($curl_error));
}

$data = json_decode($response, true);
if (!empty($data["status"]) && isset($data["data"]["authorization_url"])) {
    header("Location: " . $data["data"]["authorization_url"]);
    exit;
}

die("Payment could not start: " . htmlspecialchars($data["message"] ?? "Unknown error") .
    " <a href='my_bookings.php'>Back</a>");
?>