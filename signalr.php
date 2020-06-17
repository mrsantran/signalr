<?php

namespace SignalR;

use Ratchet\Client\WebSocket;

class Client {

    private $base_url;
    private $hubs;
    private $connectionToken;
    private $connectionId;
    private $loop;
    private $callbacks;
    private $channels;
    private $messageId = 1000;
    private $start = 0;
    private $path;

    public function __construct($base_url, $hubs, $path) {
        $this->base_url = $base_url;
        $this->hubs = $hubs;
        $this->path = $path;
        $this->callbacks = [];
    }

    public function run() {
        if (!$this->negotiate()) {
            throw new \RuntimeException("Cannot negotiate");
        }

        $this->connect();

        if (!$this->start()) {
            throw new \RuntimeException("Cannot start");
        }

        $this->loop->run();
    }

    public function on($hub, $method, $function) {
        $this->callbacks[strtolower($hub . "." . $method)] = $function;
    }

    private function connect() {
        $this->loop = \React\EventLoop\Factory::create();
        $react = new \React\Socket\Connector($this->loop);
        $connector = new \Ratchet\Client\Connector($this->loop, $react);
        //$this->loop = \React\EventLoop\Factory::create();
        //$connector = new \Ratchet\Client\Connector($this->loop);
        $connectUrl = $this->buildConnectUrl();
        print_r($connectUrl);
        $connector($connectUrl)->then(function ( $ws ) { //use ($callback, $symbol, $loop, $endpoint ) {
            
                $ws->on('message', function ( $data ) use ($ws) { //, $callback, $loop, $endpoint ) {
                    print_r(json_decode($data));
                    //$this->subscribe($ws);
                    if ($this->start == 0) {
                        $this->start = 1;
                    $subscribeMsg = json_encode([
                    'H' => 'c2',
                    'M' => 'SubscribeToSummaryLiteDeltas',
                    'A' => [],
                    'I' => 0//$this->messageId
                ]);
print_r($subscribeMsg);
//                $ws->send($subscribeMsg);
                    }
                });
                $ws->on('close', function ( $code = null, $reason = null ) { //use ($symbol, $loop ) {
                print_r("Stop");
                    //echo "depthCache({$symbol}) WebSocket Connection closed! ({$code} - {$reason})" . PHP_EOL;
                    $this->loop->stop();
                });
            }, function ( $e ) { //use ($loop, $symbol ) {
                print_r($e->getMessage());
                //echo "depthCache({$symbol})) Could not connect: {$e->getMessage()}" . PHP_EOL;
                $this->loop->stop();
            });
            
            
            
            
//        $connector($connectUrl)->then(function(\Ratchet\Client\WebSocket $conn) {
//            $this->subscribe($conn);
//            $conn->on('message', function(\Ratchet\RFC6455\Messaging\MessageInterface $msg) use ($conn) {
//                $data = json_decode($msg);
//                
//                print_r($data);
//                
//                if (\property_exists($data, "M")) {
//                    foreach ($data->M as $message) {
//                        $hub = $message->H;
//                        $method = $message->M;
//                        $callback = \strtolower($hub . "." . $method);
//                        if (array_key_exists($callback, $this->callbacks)) {
//                            foreach ($message->A as $payload) {
//                                $this->callbacks[$callback]($payload);
//                            }
//                        }
//                    }
//                }
//            });
//        }, function(\Exception $e) {
//            echo "Could not connect: {$e->getMessage()}\n";
//            $this->loop->stop();
//        });
    }

    private function buildNegotiateUrl() {
        $base = str_replace("wss://", "https://", $this->base_url);

        $hubs = [];
        foreach ($this->hubs as $hubName) {
            $hubs[] = (object) ["name" => $hubName];
        }

        $query = [
            "clientProtocol" => 1.5,
            "connectionData" => json_encode($hubs),
            "_"  => (int) (microtime(true) * 1000)
        ];

        return $base . "/negotiate?" . http_build_query($query);
    }

    private function buildStartUrl() {
        $base = str_replace("wss://", "https://", $this->base_url);

        $hubs = [];
        foreach ($this->hubs as $hubName) {
            $hubs[] = (object) ["name" => $hubName];
        }

        $query = [
            "transport" => "webSockets",
            "clientProtocol" => 1.5,
            "connectionToken" => $this->connectionToken,
            "connectionData" => json_encode($hubs),
            "_"  => (int) (microtime(true) * 1000)
        ];

        return $base . "/start?" . http_build_query($query);
    }

    private function buildConnectUrl() {
        $hubs = [];
        foreach ($this->hubs as $hubName) {
            $hubs[] = (object) ["name" => $hubName];
        }

        $query = [
            "transport" => "webSockets",
            "clientProtocol" => 1.5,
            "connectionToken" => $this->connectionToken,
            "connectionData" => json_encode($hubs),
//            "tid" => 1
        ];

        return $this->base_url . "/connect?" . http_build_query($query);
    }

    private function negotiate() {
        try {
            $url = $this->buildNegotiateUrl();// . "&_=" . (int) (microtime(true) * 1000);
            print_r($url);
            $client = new \GuzzleHttp\Client();
            $res = $client->request('GET', $url, ['verify' => $this->path]);
            print_r($res);
            $body = json_decode($res->getBody());
            print_r($body);
            $this->connectionToken = $body->ConnectionToken;
            $this->connectionId = $body->ConnectionId;
            return true;
        } catch (\Exception $e) {
            print_r($e->getMessage());
            return false;
        }
    }

    private function start() {
        try {
            $url = $this->buildStartUrl();
            $client = new \GuzzleHttp\Client();
            $res = $client->request('GET', $url, ['verify' => $this->path]);

            $body = json_decode($res->getBody());

            print_r($url);
            print_r($body);
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function subscribe(WebSocket $conn) {
        foreach ($this->hubs as $hub) {
            foreach ($this->channels as $channel) {
                //{H: "c2", M: "SubscribeToSummaryLiteDeltas", A: [], I: 0}
                $subscribeMsg = json_encode([
                    'H' => 'c2',
                    'M' => 'SubscribeToSummaryLiteDeltas',
                    'A' => [],
                    'I' => 0//$this->messageId
                ]);
print_r($subscribeMsg);
                $conn->send($subscribeMsg);
            }
        }
    }

    public function setChannels($channels) {
        $this->channels = $channels;
    }

}
