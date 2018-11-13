<?php
/**
 * Created by PhpStorm.
 * User: RusinovArtem
 * Date: 11/9/2018
 * Time: 7:53 PM
 */

use Crypto\A1;

require __DIR__."/../vendor/autoload.php";
$config = include __DIR__ . "/../config.php";

$bs = new \Crypto\Bot\BotStorage();
$hit = new \Crypto\HitBTC\Client($config['hitbtc.api.key'], $config['hitbtc.api.secret']);
$bot = new \Crypto\Bot\Bot();
$bot->client = $hit;
$bot->id = "BTCUSD-EXTREME";
$bot->pairID = "BTCUSD";
$bot->buyPercentage = 0.00005;
$bot->sellPercentage = 0.0005;

$bs->saveBot($bot);
//$r = $bs->getBot('BTCUSD-01');
//$r = $bs->getAll();
//var_dump($r);
//$bot->tick();
