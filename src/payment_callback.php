<?php
session_start();
require "connection.php";
require "paystack_config.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student") {
    header("Location: login.php");
    exit;
}

$student_id = $_SESSION["user_id"];
$reference = isset($_GET["reference"]) ? trim($_GET["reference"]) : "";
$message = "";
$ok = false;

$stmt = $conn->prepare("SELECT p.Payment_ID, p.Amount, p.Status
                        FROM Payments p
                        JOIN Bookings b ON p.Booking_ID = b.Booking_ID
                        WHERE p.Paystack_Reference = ? AND b.Student_ID = ?");
$stmt->bind_param("si", $reference, $student_id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();

if (!$payment) {
    $message = "Payment record not found.";
} elseif ($payment["Status"] === "Success") {
    $ok = true;
    $message = "This payment was already confirmed.";
} else {
    $ch = curl_init("https://api.paystack.co/transaction/verify/" . urlencode($reference));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer " . PAYSTACK_SECRET_KEY]
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);

    $paid = isset($data["data"]["status"]) && $data["data"]["status"] === "success"
            && (int)$data["data"]["amount"] === (int)round($payment["Amount"] * 100);

    if ($paid) {
        $u = $conn->prepare("UPDATE Payments SET Status = 'Success', Paid_At = NOW() WHERE Payment_ID = ?");
        $u->bind_param("i", $payment["Payment_ID"]);
        $u->execute();
        $ok = true;
        $message = "Payment successful. Reference: " . $reference;
    } else {
        $u = $conn->prepare("UPDATE Payments SET Status = 'Failed' WHERE Payment_ID = ?");
        $u->bind_param("i", $payment["Payment_ID"]);
        $u->execute();
        $message = "Payment was not completed.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Result</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h2>Payment Result</h2>
    <p style="color:<?php echo $ok ? 'green' : 'red'; ?>"><?php echo htmlspecialchars($message); ?></p>
    <p><a href="my_bookings.php">My bookings</a></p>
</body>
</html>