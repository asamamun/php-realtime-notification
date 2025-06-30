<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "r64_php";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to save notification
function saveNotification($user_id, $message, $conn) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, created_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("is", $user_id, $message);
    $stmt->execute();
    $stmt->close();
}

// WebSocket server using Ratchet
require __DIR__ . '/vendor/autoload.php';
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;

class NotificationServer implements MessageComponentInterface {
    protected $clients;
    protected $conn;

    public function __construct($dbConnection) {
        $this->clients = new \SplObjectStorage;
        $this->conn = $dbConnection;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        if (isset($data['user_id']) && isset($data['message'])) {
            saveNotification($data['user_id'], $data['message'], $this->conn);
            foreach ($this->clients as $client) {
                $client->send(json_encode([
                    'user_id' => $data['user_id'],
                    'message' => $data['message'],
                    'created_at' => date('Y-m-d H:i:s')
                ]));
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Connection closed! ({$conn->resourceId})\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}

// Start WebSocket server
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