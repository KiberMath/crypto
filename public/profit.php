<?php
/**
 * Created by PhpStorm.
 * User: RusinovArtem
 * Date: 11/10/2018
 * Time: 4:43 PM
 */

use Crypto\Bot\BotStorage as BotStorage;
use Crypto\Bot\CircleBot as CircleBot;
use Crypto\Exchange\Order;
use Crypto\HitBTC\Client as Client;

require __DIR__."/../vendor/autoload.php";
$config = include __DIR__ . "/../config.php";


$counter = 0;
$bs = new BotStorage();
$hit = new Client($config['hitbtc.api.key'], $config['hitbtc.api.secret']);

$handler = new \Monolog\Handler\RotatingFileHandler(__DIR__."/../storage/log/main.log", 3);
$logger = new \Monolog\Logger("hitbtc.client");
$logger->pushHandler($handler);

$botLogger = new \Monolog\Logger("BotNext");
$botLogger->pushHandler($handler);

$hit->setLogger($logger);

$bots = $bs->getAll();


$orders = $hit->getActiveOrders();
foreach ($orders as $order)
{
    if($order->eClientOrderID == '1c2688edf981732bd795828f0a7e279e')
    {
        $order->price += 0.0001;
        $order->value += 0.001;
        $hit->updateOrder($order);
        var_dump($order->eClientOrderID);
    }
}