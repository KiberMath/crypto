<?php

use Crypto\WSFrameClient;

include __DIR__."/../vendor/autoload.php";
ini_set('display_startup_errors',1);
ini_set('display_errors', 1);

$keys = file_get_contents(__DIR__."/../storage/hitkeys.data");
$keys = unserialize($keys);
shuffle($keys);
var_dump($keys[0]);

set_error_handler(function($no, $str, $file, $line, $context){
    $msg =  "$str, $file:$line $no";
    echo "$msg\n";
    throw new \Exception($msg);
});

class TradeListener
{
    public $key;
    public $secret;
    public $lastTime;
    public static $isRuning = false;

    /**
     * @var WSFrameClient
     */
    public $wsClient;
    /**
     * @var TradeListener[]
     */
    public static $listeners;
    public static $id=0;
    /**
     * @var EventBase
     */
    public static $eventBase;
    public static $proxyList;

    public function __construct($key, $secret)
    {
        $this->key = $key;
        $this->secret = $secret;
    }

    private function getProxy()
    {
        if(!static::$proxyList){
            $file = __DIR__ . "/proxy.data";
            static::$proxyList = $data = unserialize(file_get_contents($file));
        }

        $proxy = array_shift(static::$proxyList);
        array_push(static::$proxyList, $proxy);
        $proxy = "tcp://".$proxy;
        $p = parse_url($proxy);
        return $proxy;
    }

    public function init()
    {

        $n = 0;
        m1:
        $this->log('INIT: Loop m1');
        try{

            if(!static::$isRuning){
                TradeListener::$eventBase->loop(EventBase::LOOP_NONBLOCK);
            }

            $proxy =  $this->getProxy();
            $client = new \Crypto\WSFrameClient('api.hitbtc.com', 443, '/api/2/ws', $proxy);
            $this->log('INIT: client created');
        }catch (\Throwable $t)
        {
            $this->log('INIT: '.exceptionToString($t));
            var_dump($t->getMessage());
            if($t->getCode() === 429 ){
                $n+=$n*(0.1);
            }

            if($t->getCode() === -2){
                var_dump($proxy);
                $p = array_pop(static::$proxyList);
                var_dump($p);
            }

            if(!static::$isRuning){
                TradeListener::$eventBase->loop(EventBase::LOOP_NONBLOCK);
            }

                if(count(static::$proxyList)>0){
                    goto m1;
                }
                else
                {
                    var_dump("NO PROXY");
                 }

            }

        static::$id++;
        $nonce = 'HELLO';
        $hash = hash_hmac('sha256', $nonce, $this->secret);

        $message = "{
              \"method\": \"login\",
              \"params\": {
                \"algo\": \"HS256\",
                \"pKey\": \"{$this->key}\",
                \"nonce\": \"{$nonce}\",
                \"signature\": \"{$hash}\"
              }
            }";

        $client->send($message);

        //var_dump($client->getFrame()->getData());

        $id = static::$id;
        $message = "{ \"method\": \"getTradingBalance\", \"params\": {}, \"id\": {$id} }";

        $client->send($message);
        //var_dump($client->getFrame()->getData());

        $message = json_encode([ 'method'=>'subscribeReports', 'params'=>[], 'id'=>$id, ]);
        $client->send($message);
        //var_dump($client->getFrame()->getData());
        stream_set_timeout($client->socket, 10);
        stream_set_blocking($client->socket, false);

        $event = new Event(static::$eventBase, $client->socket, Event::READ, function($socket, $n, $x)use($client, &$event){
            try{
                $frame = $client->getFrame();
            }
            catch (Throwable $t)
            {
                $frame = null;
                $this->log($t->getMessage());
                $this->init();
            }

            if($frame && (9 != $frame->opcode))
            {
                $msg = $frame->getData();
                $msg = substr($msg, 0, 500);
                $dt = (new \DateTime())->format("Y-m-d H:i:s");
                $this->log("[{$frame->opcode}] [length={$frame->dataLength}] {$msg}");
            }
            $r = $event->add(1);
            if(!$r)
            {
                var_dump("WARNING2. $r");
                sleep(1);
                $r = $event->add(1);
                var_dump("ASSERT 2 $r");
            }
        });

        unset($this->wsClient);
        $this->wsClient = $client;
        TradeListener::$listeners[$this->key] = &$this;
        $event->add(1);
    }

    public function log($m)
    {
        $dt = (new \DateTime())->format("Y-m-d H:i:s");
        $m =  "[{$dt}] {$this->key} {$m}\n";
        echo $m;
    }
}

TradeListener::$eventBase = new EventBase();

$counter = 0;
var_dump("count api keys: ".count($keys));


/**
 * @var $te Event
 */
$timerCounter = 0;
$te = Event::timer(TradeListener::$eventBase, function ($n) use(&$te, &$timerCounter){

    try{
        $timerCounter++;
        $dt = new DateTime();
        $dt->sub(new DateInterval("PT30S"));

        $redTime = clone $dt;
        $redTime->sub(new DateInterval("PT5M"));

        foreach (TradeListener::$listeners as $listener){

            if($listener->wsClient->lastTime < $dt){
                $listener->log("No messages for 30 sec [{$timerCounter}] ".$listener->wsClient->lastTime->format("Y-m-d H:i:s"));
                try{
                    $r = $listener->wsClient->ping();
                }
                catch (\Throwable $t){
                    $listener->log($t->getMessage());
                    $r = 0;
                }

                $listener->log("Ping $r [{$timerCounter}]");
                if(!$r){
                    $listener->log("Reinit [{$timerCounter}]");
                    $listener->wsClient = null;
                    $listener->init();
                }
            }

            if($listener->wsClient->lastTime < $redTime){
                $listener->log("RED LIMIT [{$timerCounter}]");
                $listener->log("Reinit [{$timerCounter}]");
                $listener->wsClient = null;
                $listener->init();
            }

        }

        $msg = (new \DateTime())->format("Y-m-d H:i:s")." \n";
        $msg .= " listeners = ".count(TradeListener::$listeners)." \n";
        $msg .= " proxy count ".count(TradeListener::$proxyList)." \n";
        $msg .= "\n";
        echo($msg);
    }catch (\Throwable $t){
        var_dump("WARNING ".$t->getMessage()." ".$t->getFile()." ".$t->getLine());
    }

    $r = $te->add(10);
    if(!$r){
        var_dump("WARNING! $r");
        sleep(1);
        $r = $te->add(10);
        var_dump("ASSERT $r");
    }


}, null);
$te->addTimer(10);

foreach ($keys as $apiKey)
{

        $counter++;
        $l = new TradeListener($apiKey['key'], $apiKey['secret']);
        try{
            $l->init();
        }
        catch (\Throwable $t)
        {
            $str = $t->getMessage() . "\n" . $t->getFile() . ":" . $t->getLine();
            var_dump($str);
        }

        if(!TradeListener::$isRuning){
            TradeListener::$eventBase->loop(EventBase::LOOP_NONBLOCK);
        }

        var_dump("counter = $counter");
}
TradeListener::$isRuning = true;
TradeListener::$eventBase->dispatch();