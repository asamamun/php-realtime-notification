<?php
require __DIR__ . '/vendor/autoload.php';
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;

class NotificationServer implements MessageComponentInterface {
    protected $clients;
    protected $conn;
    protected $userConnections;

    public function __construct($dbConnection) {
        $this->clients = new \SplObjectStorage;
        $this->userConnections = [];
        $this->conn = $dbConnection;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        if (isset($data['action']) && $data['action'] === 'register') {
            $this->userConnections[$data['user_id']] = $from;
            echo "User {$data['user_id']} registered\n";
            return;
        }

        if (isset($data['sender_id'], $data['recipient_id'], $data['message'])) {
            $message_id = $this->saveNotification($data['sender_id'], $data['recipient_id'], $data['message']);
            echo "Sending message from {$data['sender_id']} to {$data['recipient_id']}\n";
            $messageData = [
                'message_id' => $message_id,
                'sender_id' => $data['sender_id'],
                'recipient_id' => $data['recipient_id'],
                'message' => $data['message'],
                'created_at' => date('Y-m-d H:i:s'),
                'is_read' => 0
            ];
            if (isset($this->userConnections[$data['recipient_id']])) {
                $recipientConn = $this->userConnections[$data['recipient_id']];
                $recipientConn->send(json_encode($messageData));
                echo "Message sent to recipient {$data['recipient_id']}\n";
            } else {
                echo "Recipient {$data['recipient_id']} not connected\n";
            }
            if (isset($this->userConnections[$data['sender_id']])) {
                $from->send(json_encode($messageData));
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        foreach ($this->userConnections as $user_id => $client) {
            if ($client === $conn) {
                unset($this->userConnections[$user_id]);
                echo "User {$user_id} disconnected\n";
                break;
            }
        }
        $this->clients->detach($conn);
        echo "Connection closed! ({$conn->resourceId})\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    protected function saveNotification($sender_id, $recipient_id, $message) {
        $stmt = $this->conn->prepare("INSERT INTO notifications (sender_id, recipient_id, message, created_at, is_read) VALUES (?, ?, ?, NOW(), 0)");
        $stmt->bind_param("iis", $sender_id, $recipient_id, $message);
        $stmt->execute();
        $message_id = $this->conn->insert_id;
        $stmt->close();
        return $message_id;
    }
}

$conn = new mysqli("localhost", "root", "", "r64_notifications");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new NotificationServer($conn)
        )
    ),
    8080
);
$server->run();
?>