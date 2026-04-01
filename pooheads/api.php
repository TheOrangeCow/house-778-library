<?php
session_start();
include "../db.php";

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$action   = $_GET['action'] ?? null;
$roomCode = $_GET['code'] ?? null;
$username = $_SESSION['username'] ?? null;

$stmt = $conn->prepare("SELECT * FROM pooheads WHERE room_code = ?");
$stmt->bind_param("s", $roomCode);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die(json_encode(["error" => "Game not found!"]));
}

$game = $result->fetch_assoc();

$game['players']  = json_decode($game['players'] ?? '[]', true) ?: [];
$game['deck']     = json_decode($game['deck'] ?? '[]', true) ?: [];
$game['pile']     = json_decode($game['pile'] ?? '[]', true) ?: [];
$game['hands']    = json_decode($game['hands'] ?? '{}', true) ?: [];
$game['faceup']   = json_decode($game['faceup'] ?? '{}', true) ?: [];
$game['facedown'] = json_decode($game['facedown'] ?? '{}', true) ?: [];
$game['turn']     = $game['turn'] ?? null;
$game['sevenRule']= (int)($game['sevenRule'] ?? 0);
$game['skipNext'] = (int)($game['skipNext'] ?? 0);

$players = $game['players'];

function saveGame($conn, $roomCode, $game) {
    $stmt = $conn->prepare("
        UPDATE pooheads 
        SET players=?, deck=?, pile=?, hands=?, faceup=?, facedown=?, turn=?, sevenRule=?, skipNext=? 
        WHERE room_code=?
    ");

    $playersJson  = json_encode($game['players']);
    $deckJson     = json_encode($game['deck']);
    $pileJson     = json_encode($game['pile']);
    $handsJson    = json_encode($game['hands']);
    $faceupJson   = json_encode($game['faceup']);
    $facedownJson = json_encode($game['facedown']);
    $turn         = $game['turn'] ?? null;
    $sevenRule    = (int)($game['sevenRule'] ?? 0);
    $skipNext     = (int)($game['skipNext'] ?? 0);

    $stmt->bind_param(
        "sssssssiss",
        $playersJson, 
        $deckJson, 
        $pileJson, 
        $handsJson, 
        $faceupJson, 
        $facedownJson,
        $turn, 
        $sevenRule, 
        $skipNext, 
        $roomCode
    );

    $stmt->execute();
}

function refillHand(&$game, $player) {
    if (!isset($game['hands'][$player])) $game['hands'][$player] = [];
    while (count($game['hands'][$player]) < 3 && !empty($game['deck'])) {
        $game['hands'][$player][] = array_shift($game['deck']);
    }
}

function nextPlayer(&$game, $current) {
    $players = $game['players'];
    if (empty($players)) return;
    $idx = array_search($current, $players);
    $idx = ($idx === false ? 0 : ($idx + 1) % count($players));
    $game['turn'] = $players[$idx];
}

function nextPlayerN(&$game, $current, $n) {
    $players = $game['players'];
    if (empty($players)) return;
    $idx = array_search($current, $players);
    $idx = ($idx === false ? 0 : ($idx + $n + 1) % count($players));
    $game['turn'] = $players[$idx];
}

function cardValue($c) {
    $r = preg_replace('/[^0-9JQKA]/','',$c);
    $map = ['J'=>11,'Q'=>12,'K'=>13,'A'=>14];
    return $map[$r] ?? intval($r);
}

function checkWin(&$game, $username) {
    if (empty($game['hands'][$username] ?? []) &&
        empty($game['faceup'][$username] ?? []) &&
        empty($game['facedown'][$username] ?? [])) {
        
        unset($game['hands'][$username], $game['faceup'][$username], $game['facedown'][$username]);
        if (($key = array_search($username, $game['players'])) !== false) {
            unset($game['players'][$key]);
            $game['players'] = array_values($game['players']);
        }
        if ($game['turn'] === $username) nextPlayer($game, $username);
        return true;
    }
    return false;
}

if ($username && !in_array($username, $game['players'])) {
    if (!empty($game['hands'])) {
        echo json_encode(["error" => "Game already started, cannot join."]);
        exit;
    }
    $game['players'][] = $username;
    $players = $game['players'];
    saveGame($conn, $roomCode, $game);
}

$deckFile = __DIR__ . '/cards.json';
if (!file_exists($deckFile)) die(json_encode(["error"=>"Deck file not found"]));
$deckData = json_decode(file_get_contents($deckFile), true);

switch ($action) {
    case "state":
        echo json_encode($game, JSON_PRETTY_PRINT);
        break;

    case "start":
        $deck = $deckData['deck'];
        shuffle($deck);
        $hands = $faceup = $facedown = [];
        foreach ($players as $p) {
            $hands[$p] = [];
            $faceup[$p] = [];
            $facedown[$p] = [];
        }
        $game['hands'] = $hands;
        $game['faceup'] = $faceup;
        $game['facedown'] = $facedown;
        $game['deck'] = $deck;
        $game['pile'] = [];
        $game['turn'] = $players[0] ?? null;
        saveGame($conn, $roomCode, $game);
        echo json_encode($game);
        break;

    case "play":
        if ($game['turn'] !== $username) {
            echo json_encode(["error" => "Not your turn"]);
            exit;
        }
        $cards = $_GET['cards'] ?? null;
        if (!$cards) { echo json_encode(["error"=>"No cards specified"]); exit; }
        if (!is_array($cards)) $cards = [$cards];

        $ranksPlayed = array_map(fn($c)=>preg_replace('/[^0-9JQKA]/','',$c), $cards);
        if (count(array_unique($ranksPlayed)) > 1) { echo json_encode(["error"=>"All cards must be same rank"]); exit; }

        $cardRank = $ranksPlayed[0];
        $topCard = end($game['pile']);
        $cardValuePlayed = cardValue($cards[0]);
        $canPlay = true;

        $effectiveCard = $topCard;
        if ($topCard) {
            $lastRank = preg_replace('/[^0-9JQKA]/','', $topCard);
            if ($lastRank === "3") {
                for ($i=count($game['pile'])-1;$i>=0;$i--) {
                    $tmpRank = preg_replace('/[^0-9JQKA]/','',$game['pile'][$i]);
                    if ($tmpRank !== "3") { $effectiveCard = $game['pile'][$i]; break; }
                }
            }
        }
        $effectiveValue = $effectiveCard ? cardValue($effectiveCard) : null;
        $effRank = $effectiveCard ? preg_replace('/[^0-9JQKA]/','',$effectiveCard) : null;

        if ($cardRank==="10" && $effRank==="7") $canPlay=false;
        if ($game['sevenRule'] && $cardValuePlayed>7) $canPlay=false;
        elseif ($effectiveCard && !in_array($cardRank,["2","3","10"]) && $cardValuePlayed<$effectiveValue) $canPlay=false;

        $playerHand = $game['hands'][$username] ?? [];
        $playerFaceup = $game['faceup'][$username] ?? [];
        $playerFacedown = $game['facedown'][$username] ?? [];
        if (!empty($playerHand) && array_diff($cards,$playerHand)) $canPlay=false;
        if (empty($playerHand) && array_diff($cards,$playerFaceup) && array_diff($cards,$playerFacedown)) $canPlay=false;

        if (!$canPlay) {
            $pickup = array_merge($game['pile'],$cards);
            foreach ($pickup as $c) if (!in_array($c,$playerHand)) $game['hands'][$username][]=$c;
            $game['faceup'][$username] = array_values(array_diff($playerFaceup,$cards));
            $game['facedown'][$username] = array_values(array_diff($playerFacedown,$cards));
            $game['pile']=[];
            $game['sevenRule']=0;
            $game['skipNext']=0;
            refillHand($game,$username);
            nextPlayer($game,$username);
            saveGame($conn,$roomCode,$game);
            echo json_encode($game);
            break;
        }

        foreach ($cards as $c) {
            $game['hands'][$username] = array_values(array_diff($game['hands'][$username],[$c]));
            $game['faceup'][$username] = array_values(array_diff($game['faceup'][$username],[$c]));
            $game['facedown'][$username] = array_values(array_diff($game['facedown'][$username],[$c]));
        }

        if (checkWin($game,$username)) { saveGame($conn,$roomCode,$game); echo json_encode($game); break; }

        $game['pile'] = array_merge($game['pile'],$cards);

        $extraTurn=false; $numEights=0;
        foreach($cards as $c){
            $r=preg_replace('/[^0-9JQKA]/','',$c);
            switch($r){
                case "2": $game['sevenRule']=0; $game['skipNext']=0; break;
                case "3": if($effRank==="7") $game['sevenRule']=1; if($effRank==="8") $numEights++; break;
                case "7": $game['sevenRule']=1; break;
                case "8": $numEights++; break;
                case "10": $game['pile']=[]; $extraTurn=true; break;
            }
        }

        $lastFour = array_slice($game['pile'],-4);
        $ranks = array_map(fn($c)=>preg_replace('/[^0-9JQKA]/','',$c),$lastFour);
        if(count($ranks)===4 && count(array_unique($ranks))===1) { $game['pile']=[]; $extraTurn=true; }

        refillHand($game,$username);
        if($numEights>0) nextPlayerN($game,$username,$numEights);
        elseif(!$extraTurn) nextPlayer($game,$username);

        saveGame($conn,$roomCode,$game);
        echo json_encode($game);
        break;

    default:
        echo json_encode(["error"=>"Invalid action"]);
        break;
}