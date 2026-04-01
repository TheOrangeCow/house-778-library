<?php
session_start();

include "../db.php";

if (!isset($_SESSION['username'])) {
    $_SESSION['username'] = "Player" . rand(100, 999);
}
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




function getNewDeck() {
    $suits = ["H","D","C","S"];
    $vals  = ["A","2","3","4","5","6","7","8","9","10","J","Q","K"];
    $deck = [];
    foreach ($suits as $s) foreach ($vals as $v) $deck[] = $v.$s;
    shuffle($deck);
    return $deck;
}

function cardValue($hand) {
    $value = 0; $aces = 0;
    foreach ($hand as $c) {
        $v = substr($c,0,-1);
        if ($v=="A") { $aces++; $value+=11; }
        elseif (in_array($v,["J","Q","K"])) $value+=10;
        else $value+=intval($v);
    }
    while ($value>21 && $aces>0) { $value-=10; $aces--; }
    return $value;
}


$action = $_GET['action'] ?? null;
$handIndex = $_GET['hand'] ?? 0; 


if ($action==="new") {
    $_SESSION['deck'] = getNewDeck();
    $_SESSION['player'] = [[array_shift($_SESSION['deck']), array_shift($_SESSION['deck'])]];
    $_SESSION['dealer'] = [array_shift($_SESSION['deck']), array_shift($_SESSION['deck'])];
    $_SESSION['bets'] = [50];
    $_SESSION['finished'] = false;
    $_SESSION['splitCount'] = 0;
}


if (!isset($_SESSION['player'][$handIndex])) $handIndex = 0;


if ($action==="hit" && !$_SESSION['finished']) {
    $_SESSION['player'][$handIndex][] = array_shift($_SESSION['deck']);

    if (cardValue($_SESSION['player'][$handIndex])>21) {
        $_SESSION['finished'] = true;
    }
}

if ($action==="stand" && !$_SESSION['finished']) {
    while (cardValue($_SESSION['dealer'])<17) $_SESSION['dealer'][] = array_shift($_SESSION['deck']);
    $_SESSION['finished'] = true;
}

if ($action==="double" && !$_SESSION['finished']) {
    $_SESSION['bets'][$handIndex] *= 2;
    $_SESSION['player'][$handIndex][] = array_shift($_SESSION['deck']);
    $_SESSION['finished'] = true;
}

if ($action==="surrender" && !$_SESSION['finished']) {
    $_SESSION['bets'][$handIndex] /= 2;
    $_SESSION['player'][$handIndex] = [];
    $_SESSION['finished'] = true;
}

if ($action==="split" && !$_SESSION['finished'] && isset($_SESSION['player'][$handIndex])) {
    $hand = $_SESSION['player'][$handIndex];
    if (count($hand)==2 && substr($hand[0],0,-1)==substr($hand[1],0,-1) && $_SESSION['splitCount']<3) {
        $_SESSION['player'][$handIndex] = [$hand[0], array_shift($_SESSION['deck'])];
        $_SESSION['player'][] = [$hand[1], array_shift($_SESSION['deck'])];
        $_SESSION['bets'][] = $_SESSION['bets'][$handIndex];
        $_SESSION['splitCount']++;
    }
}


if ($_SESSION['finished']) {
    $dealerTotal = cardValue($_SESSION['dealer']);
    foreach ($_SESSION['player'] as $i=>$hand) {
        $bet = $_SESSION['bets'][$i];
        $total = cardValue($hand);
        if ($total==0) {
            $chips -= $bet;
        } elseif ($total>21) { 
            $chips -= $bet;
        } elseif ($dealerTotal>21 || $total>$dealerTotal) {
            $chips += $bet;
        } elseif ($total<$dealerTotal) {
            $chips -= $bet;
        }
    }
    $stmt = $conn->prepare("UPDATE chips SET chips = ? WHERE username = ?");
    $stmt->bind_param("is", $chips, $username);
    $stmt->execute();
}

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Blackjack</title>
        <link rel="stylesheet" href="style.css">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Comfortaa:wght@300..700&display=swap" rel="stylesheet">
        <link rel="icon" href="https://house-778.theorangecow.org/base/icon.ico" type="image/x-icon">

    </head>
    <body>
        <div class="main">
            <div class = "tittle">
                <h2>Blackjack – <?= htmlspecialchars($username) ?></h2>
                <p>Your Chips: <strong><?= $chips ?></strong></p>
            </div>
            <div class="yourhand">
                <h3>Your Hands</h3>
                <?php foreach ($_SESSION['player'] as $i=>$hand): ?>
                    <div>
                    <p>Hand <?= $i+1 ?> (Bet: <?= $_SESSION['bets'][$i] ?>)</p>
                    <?php foreach ($hand as $c): ?><span class="card" style="background-image:url('../cards/<?= $c ?>.png')"></span><?php endforeach; ?>
                    <p>Total: <?= cardValue($hand) ?></p>
                
                    <?php if (!$_SESSION['finished']): ?>
                        <?php if (count($hand)==2 && substr($hand[0],0,-1)==substr($hand[1],0,-1) && $_SESSION['splitCount']<3): ?>
                            <a class="btn" href="?action=split&hand=<?= $i ?>">Split</a>
                        <?php endif; ?>
                        
                        <a class="btn" href="?action=hit&hand=<?= $i ?>">Hit</a>
                        <a class="btn" href="?action=stand&hand=<?= $i ?>">Stand</a>
                        <a class="btn" href="?action=double&hand=<?= $i ?>">Double Down</a>
                        <a class="btn" href="?action=surrender&hand=<?= $i ?>">Surrender</a>
                    <?php endif; ?>
                    </div>
                    <br>
                <?php endforeach; ?>
            </div>
            <div class="dealer">
                <h3>Dealer</h3>
                <?php if (!$_SESSION['finished']): ?>
                    <span class="card" style="background-image:url('../cards/<?= $_SESSION['dealer'][0] ?>.png')"></span>
                    <span class="card" style="background-image:url('../cards/back.jpeg')"></span>
                <?php else: ?>
                    <?php foreach ($_SESSION['dealer'] as $c): ?><span class="card" style="background-image:url('../cards/<?= $c ?>.png')"></span><?php endforeach; ?>
                    <p>Total: <?= cardValue($_SESSION['dealer']) ?></p>
                <?php endif; ?>
            </div>
            
            <?php if (!isset($_SESSION['player']) || $_SESSION['finished'] || $action==="new"): ?>
                <br>
                <a class="btn" href="?action=new">Start New Game (inttle bet of 50 Chips)</a>
            <?php endif; ?>
        </div>
    </body>
</html>
