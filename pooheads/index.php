<?php

include "../chech.php"; 
include "../db.php"

$file = __DIR__ . "/game.json";




if (isset($_POST['create'])) {
    $roomCode = strtoupper(substr(md5(time()), 0, 6));

    $stmt = $conn->prepare("INSERT INTO pooheads (room_code, players, deck, pile, turn) VALUES (?, ?, ?, ?, ?)");
    
    $emptyPlayers = json_encode([]);
    $emptyDeck = json_encode([]);
    $emptyPile = json_encode([]);
    $turn = null;

    $stmt->bind_param("sssss", $roomCode, $emptyPlayers, $emptyDeck, $emptyPile, $turn);
    $stmt->execute();

    header("Location: game.php?code=$roomCode");
    exit;
}


if (isset($_POST['join'])) {
    $roomCode = strtoupper(trim($_POST['room_code']));

    $stmt = $conn->prepare("SELECT * FROM pooheads WHERE room_code = ?");
    $stmt->bind_param("s", $roomCode);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        header("Location: game.php?code=$roomCode");
        exit;
    } else {
        $error = "Game not found!";
    }
}
?>
<!DOCTYPE html>
<html>
    <head>
        <title>Pooheads</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>House</title>
        <link rel="stylesheet" href="style.css">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Comfortaa:wght@300..700&display=swap" rel="stylesheet">
        <link rel="icon" href="https://house-778.theorangecow.org/base/icon.ico" type="image/x-icon">
        <style>
    ..rule-section h1 {
        text-align: center;
        color: #000 !important; 
    }
    .rule-section h2 {
        color: #000;
        border-bottom: 2px solid #d1c4e9;
        padding-bottom: 5px;
    }
    .rule-section h3 {
        color: #000;
    }
    .rule-section {
        background-color: #ffffff;
        border-radius: 10px;
        padding: 20px;
        color: #000;
        margin: 15px 0;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .rule-section ul {
        list-style: none;
        padding-left: 0;
    }
    .rule-section li {
        padding: 5px 0;
        position: relative;
        padding-left: 25px;
    }
    .rule-section li::before {
        content: "•";
        position: absolute;
        left: 0;
        color: #4a148c;
        font-weight: bold;
    }
    .special {
        font-weight: bold;
        color: #d32f2f;
    }
    .highlight {
        background-color: #f3e5f5;
        padding: 2px 5px;
        border-radius: 4px;
    }
    
        </style>
    </head>
    <body>
        <h1>Pooheads (No Betting game)</h1>
        
        <form method="post">
            <button name="create">Create New Game</button><br><br>
        </form>
        <form method="post">
            <input type="text" name="room_code" placeholder="Enter Room Code"><br>
            <button name="join">Join Game</button><br>
        </form>
        <?php if(isset($error)) echo "<p style='color:red'>$error</p>"; ?>
        
        <div class="rule-section">
            <h1>House Rules</h1>
            <h2>Game Setup</h2>
                <ul>
                    <li>Each player gets 3 <span class="highlight">face-down</span> cards (cannot look at them).</li>
                    <li>Each player gets 3 <span class="highlight">face-up</span> cards (everyone can see them).</li>
                    <li>Each player gets 3 cards in <span class="highlight">hand</span>.</li>
                    <li>The remaining cards form the <span class="highlight">deck</span>.</li>
                    <li>The first player in the room goes first.</li>
                    <li>On your turn, you must play cards of the <span class="highlight">same rank</span>.</li>
                </ul>

                <h2>Objective</h2>
                <ul>
                    <li>Be the first to get rid of all <span class="highlight">hand</span>, <span class="highlight">face-up</span>, and <span class="highlight">face-down</span> cards.</li>
                    <li>If you finish all cards, you win and leave the game.</li>
                </ul>

                <h2>Drawing Cards</h2>
                <ul>
                    <li>After a successful play, draw from the deck until you have at least 3 cards in hand (unless the deck is empty).</li>
                </ul>
            
                <h2>Playing Rules</h2>
                <ul>
                    <li>You must play a card equal or higher than the top card of the pile.</li>
                    <li>If you cannot play legally, pick up the entire pile AND the card(s) you tried to play.</li>
                </ul>
            
                <h2>Special Cards</h2>
                <ul>
                    <li><span class="special">2 — Reset</span>: Resets rules; next player can play anything.</li>
                    <li><span class="special">3 — Invisible Card</span>: Counts as the last non-3 card on the pile.</li>
                    <li><span class="special">7 — Must Play Lower</span>: Next player must play a card 7 or lower.</li>
                    <li><span class="special">8 — Skip Player</span>: Skips one player per 8. Multiple 8s stack.</li>
                    <li><span class="special">10 — Burn</span>: Clears the pile; the player who played it gets another turn.</li>
                    <li><span class="special">4-of-a-Kind Burn</span>: If the top four cards are the same rank, pile burns; last player gets another turn.</li>
                </ul>

                <h2>Hand, Face-Up & Face-Down️</h2>
                <ul>
                    <li>Play from <span class="highlight">hand</span> first.</li>
                    <li>Then play from <span class="highlight">face-up</span> cards.</li>
                    <li>Finally, flip and play <span class="highlight">face-down</span> cards blindly.</li>
                    <li>Illegal plays in face-up/down result in picking up the pile.</li>
                </ul>

                <h2>Winning</h2>
                <ul>
                    <li>You win when <span class="highlight">hand</span>, <span class="highlight">face-up</span>, and <span class="highlight">face-down</span> cards are empty.</li>
                    <li>Winning players are removed from the game rotation.</li>
                </ul>
        </div>

</body>
</html>
    </body>
</html>

