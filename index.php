<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Social App Chat</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; display: flex; height: 100vh; }
        .sidebar { width: 250px; background: #f1f1f1; padding: 10px; overflow-y: auto; }
        .sidebar h3 { margin-top: 0; }
        .sidebar ul { list-style: none; padding: 0; }
        .sidebar li { padding: 10px; cursor: pointer; }
        .sidebar li:hover { background: #ddd; }
        .sidebar li.active { background: #007bff; color: white; }
        .chat-panel { flex: 1; display: flex; flex-direction: column; padding: 10px; }
        #notifications { flex: 1; border: 1px solid #ccc; padding: 10px; overflow-y: auto; margin-bottom: 10px; }
        .message { margin: 5px 0; }
        .message.sent { text-align: right; color: #007bff; }
        .message.received { text-align: left; color: #333; }
        .message.unread { font-weight: bold; }
        .input-group { display: flex; }
        #message { flex: 1; padding: 8px; }
        button { padding: 8px 16px; background: #007bff; color: white; border: none; cursor: pointer; }
        button:hover { background: #0056b3; }
        .header { padding: 10px; background: #007bff; color: white; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h3>Users</h3>
        <ul id="user-list"></ul>
    </div>
    <div class="chat-panel">
        <div class="header">
            <span>Logged in as User ID: <?php echo htmlspecialchars($_SESSION['user_id']); ?> 
            (<a href="logout.php" style="color: white;">Logout</a>)</span>
            <span id="recipient-name" style="float: right;"></span>
        </div>
        <div id="notifications"></div>
        <div class="input-group">
            <input type="text" id="message" placeholder="Type a message">
            <button onclick="sendMessage()">Send</button>
        </div>
    </div>

    <script>
        var conn = new WebSocket('ws://localhost:8080');
        var senderId = <?php echo json_encode($_SESSION['user_id']); ?>;
        var isWebSocketActive = false;
        var lastMessageId = 0;
        var selectedRecipientId = null;
        var userMap = {};
        var unreadCounts = {};
        var pollingInterval = null;

        conn.onopen = function(e) {
            console.log("Connected to WebSocket server");
            isWebSocketActive = true;
            if (senderId) {
                conn.send(JSON.stringify({ action: 'register', user_id: senderId }));
                console.log("Registered user ID: " + senderId);
            }
        };

        conn.onmessage = function(e) {
            if (!isWebSocketActive) return;
            var data = JSON.parse(e.data);
            if (data.message_id && data.message_id <= lastMessageId) return;
            if (data.sender_id == selectedRecipientId || (data.sender_id == senderId && data.recipient_id == selectedRecipientId)) {
                console.log("WebSocket message received for recipient:", selectedRecipientId, data);
                displayMessage(data);
                lastMessageId = data.message_id || lastMessageId;
            }
            if (data.sender_id != senderId && data.recipient_id == senderId && !data.is_read) {
                updateUnreadCount(data.sender_id, 1);
            }
        };

        conn.onclose = function() {
            console.log("WebSocket closed, switching to long polling");
            isWebSocketActive = false;
            if (selectedRecipientId) {
                startLongPolling();
            }
        };

        // Fetch user list and unread counts
        function fetchUsersAndUnreadCounts() {
            $.getJSON('fetch_users.php', function(users) {
                userMap = {};
                $('#user-list').empty();
                users.forEach(function(user) {
                    if (user.id != senderId) {
                        userMap[user.id] = user.username;
                        $('#user-list').append('<li data-id="' + user.id + '">' + user.username + ' <span class="unread-count" data-id="' + user.id + '"></span></li>');
                    }
                });
                // Fetch unread counts
                $.getJSON('fetch_notifications.php', { action: 'unread_counts', user_id: senderId }, function(response) {
                    if (response.status === 'success') {
                        unreadCounts = response.unread_counts || {};
                        updateUnreadCountsDisplay();
                    }
                });
                $('#user-list li').click(function() {
                    $('#user-list li').removeClass('active');
                    $(this).addClass('active');
                    selectedRecipientId = $(this).data('id');
                    $('#recipient-name').text('Chatting with: ' + userMap[selectedRecipientId]);
                    $('#notifications').empty();
                    lastMessageId = 0;
                    loadMessages(selectedRecipientId);
                    markMessagesAsRead(selectedRecipientId);
                    if (!isWebSocketActive) {
                        startLongPolling();
                    }
                });
            });
        }

        function updateUnreadCount(sender_id, increment) {
            unreadCounts[sender_id] = (unreadCounts[sender_id] || 0) + increment;
            updateUnreadCountsDisplay();
        }

        function updateUnreadCountsDisplay() {
            $('.unread-count').each(function() {
                var user_id = $(this).data('id');
                var count = unreadCounts[user_id] || 0;
                if (count > 0 && user_id != selectedRecipientId) {
                    $(this).text('(' + count + ')');
                } else {
                    $(this).text('');
                }
            });
        }

        function markMessagesAsRead(sender_id) {
            $.post('mark_messages_read.php', { sender_id: sender_id }, function(response) {
                if (response.status === 'success') {
                    unreadCounts[sender_id] = 0;
                    updateUnreadCountsDisplay();
                }
            }, 'json');
        }

        function displayMessage(data) {
            var isSent = data.sender_id == senderId;
            var senderName = userMap[data.sender_id] || 'User ' + data.sender_id;
            var messageClass = isSent ? 'sent' : 'received';
            if (!isSent && !data.is_read) {
                messageClass += ' unread';
            }
            $('#notifications').append('<div class="message ' + messageClass + '"><strong>' + senderName + ':</strong> ' + data.message + ' <em>(' + data.created_at + ')</em></div>');
            $('#notifications').scrollTop($('#notifications')[0].scrollHeight);
        }

        function loadMessages(recipientId) {
            $.getJSON('fetch_notifications.php', { user_id: senderId, recipient_id: recipientId }, function(notifications) {
                console.log("Loaded messages for recipient:", recipientId, "Messages:", notifications);
                $('#notifications').empty();
                notifications.forEach(function(notification) {
                    displayMessage(notification);
                    lastMessageId = Math.max(lastMessageId, notification.message_id);
                });
            });
        }

        function startLongPolling() {
            if (pollingInterval) {
                clearInterval(pollingInterval);
            }
            longPollMessages();
        }

        function longPollMessages(last_id = 0) {
            if (isWebSocketActive || !selectedRecipientId) return;
            $.ajax({
                url: 'fetch_notifications.php',
                method: 'GET',
                data: { last_id: last_id, user_id: senderId, recipient_id: selectedRecipientId },
                success: function(data) {
                    console.log("Long polling data for recipient:", selectedRecipientId, "Data:", data);
                    var notifications = JSON.parse(data);
                    notifications.forEach(function(notification) {
                        if (notification.message_id <= lastMessageId) return;
                        displayMessage(notification);
                        last_id = Math.max(last_id, notification.message_id);
                        lastMessageId = last_id;
                        if (notification.sender_id != senderId && notification.recipient_id == senderId && !notification.is_read) {
                            updateUnreadCount(notification.sender_id, 1);
                        }
                    });
                    longPollMessages(last_id);
                },
                error: function() {
                    setTimeout(function() { longPollMessages(last_id); }, 5000);
                }
            });
        }

        function sendMessage() {
            var message = $('#message').val();
            if (selectedRecipientId && message) {
                var messageData = {
                    sender_id: senderId,
                    recipient_id: selectedRecipientId,
                    message: message
                };
                if (isWebSocketActive && conn.readyState === WebSocket.OPEN) {
                    conn.send(JSON.stringify(messageData));
                } else {
                    $.post('send_message.php', messageData, function(response) {
                        console.log("Message saved via HTTP:", response);
                        if (response.status === 'success' && response.message_id > lastMessageId) {
                            displayMessage({
                                message_id: response.message_id,
                                sender_id: senderId,
                                recipient_id: selectedRecipientId,
                                message: message,
                                created_at: response.created_at,
                                is_read: response.is_read
                            });
                            lastMessageId = response.message_id;
                        }
                    }, 'json');
                }
                $('#message').val('');
            } else {
                alert('Please select a user and enter a message');
            }
        }

        // Initial setup
        fetchUsersAndUnreadCounts();

        // Handle Enter key for sending messages
        $('#message').keypress(function(e) {
            if (e.which === 13) {
                sendMessage();
            }
        });
    </script>
</body>
</html>
