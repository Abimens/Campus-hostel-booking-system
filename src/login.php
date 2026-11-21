<?php
session_start();
require "connection.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    $stmt = $conn->prepare("SELECT Student_ID, Full_Name, Password FROM Students WHERE Email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row["Password"])) {
            $_SESSION["user_id"] = $row["Student_ID"];
            $_SESSION["name"] = $row["Full_Name"];
            $_SESSION["role"] = "student";
            header("Location: student_dashboard.php");
            exit;
        }
    }

    $stmt = $conn->prepare("SELECT Manager_ID, Full_Name, Password FROM Managers WHERE Email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row["Password"])) {
            $_SESSION["user_id"] = $row["Manager_ID"];
            $_SESSION["name"] = $row["Full_Name"];
            $_SESSION["role"] = "manager";
            header("Location: manager_dashboard.php");
            exit;
        }
    }

    $error = "Incorrect email or password.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h2>Login</h2>
    <?php if ($error) echo "<p style='color:red'>$error</p>"; ?>
    <form method="post">
        <input type="email" name="email" placeholder="Email" required><br><br>
        <input type="password" name="password" placeholder="Password" required><br><br>
        <button type="submit">Log in</button>
    </form>
    <p>No account? <a href="register.php">Register</a></p>
</body>
</html>