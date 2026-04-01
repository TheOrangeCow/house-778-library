<?php
include "../base/chech.php"; 
include "../base/main.php";
session_start();

if (stripos($_SESSION['username'], 'player') !== false || stripos($_SESSION['username'], 'guest') !== false) {
    echo "You have to have a house account to use this feature";
    http_response_code(418);
    exit;
}