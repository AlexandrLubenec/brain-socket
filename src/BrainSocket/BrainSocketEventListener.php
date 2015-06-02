<?php
namespace BrainSocket;

use Illuminate\Support\Facades\App;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class BrainSocketEventListener implements MessageComponentInterface {
	protected $clients;
	protected $response;

	public function __construct(BrainSocketResponseInterface $response) {
		$this->clients = new \SplObjectStorage;
		$this->response = $response;
	}

	public function onOpen(ConnectionInterface $conn) {
		echo "Connection Established! \n";
		$this->clients->attach($conn, ['user_id' => \Crypt::decrypt($conn->WebSocket->request->getQuery()->get('u'))]);
	}

	public function onMessage(ConnectionInterface $from, $msg) {
            $msgData = json_decode($msg, TRUE);
            
            $msgData['client']['data']['user']['user_id'] = $this->clients[$from]['user_id'];
            $msgData['client']['data']['user']['user_ip'] = $from->remoteAddress;
            $resp = $this->response->make(json_encode($msgData));
           
            if(strpos($resp, '"event":"private.') !== FALSE)
            {
                echo sprintf('Connection %d sending message "%s" to server' . "\n"
			, $from->resourceId, $msg);
                $from->send($this->response->make($msg));
            } else
            {
                $numRecv = count($this->clients) - 1;
		echo sprintf('Connection %d sending message "%s" to %d other connection%s' . "\n"
			, $from->resourceId, $msg, $numRecv, $numRecv == 1 ? '' : 's');
                $path = isset($msgData['client']['data']['path']) ? $msgData['client']['data']['path'] : $from->WebSocket->request->getPath(); 
                    
                foreach ($this->clients as $client) {
                    if($client->WebSocket->request->getPath() == $path)
                    {
                        $client->send($resp);
                    }
                }
            }
	}

	public function onClose(ConnectionInterface $conn) {
		$this->clients->detach($conn);
		echo "Connection {$conn->resourceId} has disconnected\n";
	}

	public function onError(ConnectionInterface $conn, \Exception $e) {
		echo "An error has occurred: {$e->getMessage()}\n";
		$conn->close();
	}
}
