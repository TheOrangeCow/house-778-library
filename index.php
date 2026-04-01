<?php
include "chech.php"; 
include "db.php";
$username = $_SESSION['username'];

$stmt = $conn->prepare("SELECT chips FROM chips WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt = $conn->prepare("INSERT INTO chips (username, chips) VALUES (?, 100)");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $chips = 100;
} else {
    $row = $result->fetch_assoc();
    $chips = $row['chips'];
}
?>


<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login</title>
        <link rel="stylesheet" href="style.css">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Comfortaa:wght@300..700&display=swap" rel="stylesheet">
        <link rel="icon" href="https://house-778.theorangecow.org/base/icon.ico" type="image/x-icon">
    </head>
    <body>
        <?php include '../base/sidebar.php'; ?>
        <h2>Choose your game</h2>
        <?php
        include "db.php";

        $username = $_SESSION['username'];
        $stmt = $conn->prepare("SELECT chips FROM chips WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt = $conn->prepare("INSERT INTO chips (username, chips) VALUES (?, 100)");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $chips = 100;
        } else {
            $row = $result->fetch_assoc();
            $chips = $row['chips'];
        }
        
        ?>
        <p>Your Chips: <strong><?= $chips ?></strong></p>

    
        <button onclick="window.location.href='https://library.house-778.theorangecow.org/pooheads/';">
            Pooheads
        </button>
        <button onclick="window.location.href='https://library.house-778.theorangecow.org/blackjack/';">
            Blackjack
        </button>
    </body>
    <script src="https://house-778.theorangecow.org/base/main.js"></script>
    <script src="https://auth.house-778.theorangecow.org/account/track.js"></script>
    <script src="https://house-778.theorangecow.org/base/sidebar.js"></script>
</html>
