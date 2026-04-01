
<?php
session_start();

$file = __DIR__ . "/game.json";
$cardsFile = __DIR__ . "/cards.json";

$games = json_decode(file_get_contents($file), true);
$deckData = json_decode(file_get_contents($cardsFile), true);

$action   = $_GET['action'] ?? null;
$roomCode = $_GET['code'] ?? null;
$username = $_SESSION['username'] ?? null;

if (!$roomCode || !isset($games['games'][$roomCode])) {
    die(json_encode(["error" => "Game not found!"]));
}

function refillHand(&$game, $player) {
    while (count($game['hands'][$player]) < 3 && !empty($game['deck'])) {
        $game['hands'][$player][] = array_shift($game['deck']);
    }
}

function nextPlayer(&$game, $current) {
    $players = $game['players'];
    $idx = array_search($current, $players);
    $idx = ($idx + 1) % count($players);
    $game['turn'] = $players[$idx];
}

function nextPlayerN(&$game, $current, $n) {
    $players = $game['players'];
    $idx = array_search($current, $players);
    $idx = ($idx + 1 + $n) % count($players);
    $game['turn'] = $players[$idx];
}

function cardValue($c) {
    $r = preg_replace('/[^0-9JQKA]/','',$c);
    $map = ['J'=>11,'Q'=>12,'K'=>13,'A'=>14];
    return $map[$r] ?? intval($r);
}

function checkWin(&$game, $username) {
    if (empty($game['hands'][$username]) &&
        empty($game['faceup'][$username]) &&
        empty($game['facedown'][$username])) {
        
        unset($game['hands'][$username]);
        unset($game['faceup'][$username]);
        unset($game['facedown'][$username]);

        if (($key = array_search($username, $game['players'])) !== false) {
            unset($game['players'][$key]);
            $game['players'] = array_values($game['players']);
        }

        if ($game['turn'] === $username) {
            nextPlayer($game, $username);
        }

        return true;
    }
    return false;
}


if ($username && !in_array($username, $games['games'][$roomCode]['players'])) {
    $gameHasStarted = isset($games['games'][$roomCode]['hands']);
    if ($gameHasStarted) {
        echo json_encode(["error" => "Game already started, cannot join."]);
        exit;
    }
    
    $games['games'][$roomCode]['players'][] = $username;
    file_put_contents($file, json_encode($games, JSON_PRETTY_PRINT));
}


$game =& $games['games'][$roomCode];


switch ($action) {
    case "state":
        echo json_encode($game, JSON_PRETTY_PRINT);
        break;

    case "start":
        $deck = $deckData['deck'];
        shuffle($deck);

        $players = $game['players']; 
        $hands    = [];
        $faceup   = [];
        $facedown = [];

        foreach ($players as $p) {
            $facedown[$p] = array_splice($deck, 0, 3);
            $faceup[$p]   = array_splice($deck, 0, 3);
            $hands[$p]    = array_splice($deck, 0, 3);
        }

        $game['hands']    = $hands;
        $game['faceup']   = $faceup;
        $game['facedown'] = $facedown;
        $game['deck']     = $deck;
        $game['pile']     = [];
        $game['turn']     = $players[0]; 

        $games['games'][$roomCode] = $game;
        file_put_contents($file, json_encode($games, JSON_PRETTY_PRINT));
        echo json_encode($game);
        break;

case "play":
    if ($game['turn'] !== $username) {
        echo json_encode(["error" => "Not your turn"]);
        exit;
    }

    $cards = $_GET['cards'] ?? null;
   if (!$cards) {
        echo json_encode(["error" => "No cards specified"]);
        exit;
    }
    if (!is_array($cards)) $cards = [$cards];

    $ranksPlayed = array_map(fn($c) => preg_replace('/[^0-9JQKA]/','',$c), $cards);
    if (count(array_unique($ranksPlayed)) > 1) break;

    $cardRank = $ranksPlayed[0];
    $topCard = end($game['pile']);
    $cardValuePlayed = cardValue($cards[0]);

    $canPlay = true;

    $effectiveCard = $topCard;
    if ($topCard) {
        $lastRank = preg_replace('/[^0-9JQKA]/','', $topCard);
        if ($lastRank === "3") {
            for ($i = count($game['pile']) - 1; $i >= 0; $i--) {
                $tmpRank = preg_replace('/[^0-9JQKA]/','', $game['pile'][$i]);
                if ($tmpRank !== "3") {
                    $effectiveCard = $game['pile'][$i];
                    break;
                }
            }
        }
    }
    $effectiveValue = $effectiveCard ? cardValue($effectiveCard) : null;
    $effRank = $effectiveCard ? preg_replace('/[^0-9JQKA]/','', $effectiveCard) : null;

    if ($cardRank === "10" && $effRank === "7") $canPlay = false;

    if ($game['sevenRule']) {
        if ($cardValuePlayed > 7) {
            $canPlay = false;
        }
    }

        
    else{
       if ($effectiveCard && !in_array($cardRank, ["2","3", "10"]) && $cardValuePlayed < $effectiveValue) {
            $canPlay = false;
        } 
    }

    if (empty($game['hands'][$username])) {
        if (!array_diff($cards, $game['faceup'][$username]) || !array_diff($cards, $game['facedown'][$username])) {
        } else {
            $canPlay = false;
        }
    } else if (array_diff($cards, $game['hands'][$username])) {
        $canPlay = false;
    }

    if (!$canPlay) {
        $pickupCards = array_merge($game['pile'], $cards);
        foreach ($pickupCards as $c) {
            if (!in_array($c, $game['hands'][$username])) {
                $game['hands'][$username][] = $c;
            }
        }

    
        $game['faceup'][$username]   = array_values(array_diff($game['faceup'][$username], $cards));
        $game['facedown'][$username] = array_values(array_diff($game['facedown'][$username], $cards));
    
        $game['pile'] = [];
        $game['sevenRule'] = false;
        $game['skipNext'] = false;
        refillHand($game, $username);
        nextPlayer($game, $username);
        file_put_contents($file, json_encode($games, JSON_PRETTY_PRINT));
        echo json_encode($game);
        return;
    }

    foreach ($cards as $c) {
        $game['hands'][$username]    = array_values(array_diff($game['hands'][$username], [$c]));
        $game['faceup'][$username]   = array_values(array_diff($game['faceup'][$username], [$c]));
        $game['facedown'][$username] = array_values(array_diff($game['facedown'][$username], [$c]));
    }
    
    if (checkWin($game, $username)) {
        file_put_contents($file, json_encode($games, JSON_PRETTY_PRINT));
        echo json_encode($game);
        return;
    }

    $game['pile'] = array_merge($game['pile'], $cards);
    
    if ($game['sevenRule'] && $cardValuePlayed <= 7) {
        $game['sevenRule'] = false;
    }

    $extraTurn = false;
    $numEights = 0;

    foreach ($cards as $c) {
        $r = preg_replace('/[^0-9JQKA]/','',$c);
        switch($r){
            case "2":
                $game['sevenRule'] = false;
                $game['skipNext'] = false;
                break;
            case "3":

                if ($effRank === "7")  $game['sevenRule'] = true;
                if ($effRank === "8")  $numEights++;
                break;
            case "7":
                $game['sevenRule'] = true;
                break;

            case "8":
                $numEights++;
                break;
            case "10":
                $game['pile'] = [];
                $extraTurn = true;
                break;
        }
    }

    $pileCount = count($game['pile']);
    if ($pileCount >= 4) {
        $lastFour = array_slice($game['pile'], -4);

        $ranks = array_map(
            fn($c) => preg_replace('/[^0-9JQKA]/', '', $c),
            $lastFour
        );

        if (count(array_unique($ranks)) === 1) {
            $game['pile'] = [];
            $extraTurn = true;
        }
    }

    refillHand($game, $username);

    if($numEights > 0){
        nextPlayerN($game, $username, $numEights);
    } else if (!$extraTurn){
        nextPlayer($game, $username);
    }

    file_put_contents($file, json_encode($games, JSON_PRETTY_PRINT));
    echo json_encode($game);
    break;



}
