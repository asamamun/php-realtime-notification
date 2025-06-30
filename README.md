# Social App Notification System

This project implements a real-time notification system for a social app using PHP, MySQL, jQuery, and WebSockets (via Ratchet). It also includes a long polling fallback for environments where WebSockets are not supported.

## Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Composer (for installing Ratchet)
- A web server (e.g., Apache or Nginx)
- Node.js (optional, for WebSocket testing)
- Port 8080 open for WebSocket server

## Installation

1. **Clone or Download the Project**
   Download the project files or clone the repository to your local machine.

2. **Install Dependencies**
   Install Ratchet for WebSocket support using Composer:
   ```bash
   composer require cboden/ratchet
   ```

3. **Set Up the Database**
   - Create a MySQL database named `social_app`.
   - Execute the `schema.sql` file to create the `notifications` table:
     ```bash
     mysql -u your_username -p social_app < schema.sql
     ```
   - Update the database credentials (`your_username`, `your_password`, `social_app`) in `notification_system.php` and `fetch_notifications.php`.

4. **Configure the Web Server**
   - Place `index.html` and `fetch_notifications.php` in your web server's root directory (e.g., `/var/www/html` for Apache).
   - Ensure PHP is enabled on your web server.

5. **Run the WebSocket Server**
   Start the WebSocket server by running:
   ```bash
   php notification_system.php
   ```
   This will start the server on `ws://localhost:8080`. Ensure port 8080 is open.  Bettwe use IP instead of localhost for testing in LAN.
   

## Usage

1. **Access the Application**
   - Open `index.html` in a web browser (e.g., `http://localhost/index.html`).
   - Enter a `User ID` and `Message` in the input fields.
   - Click **Send Notification** to send a notification.

2. **Real-Time Notifications**
   - Notifications are sent via WebSocket and displayed in real-time to all connected clients.
   - If WebSocket fails, the system switches to long polling, fetching notifications from `fetch_notifications.php` every few seconds.

3. **Database Storage**
   - Notifications are stored in the `notifications` table in the MySQL database for persistence.

## Files

- `notification_system.php`: WebSocket server using Ratchet to handle real-time notifications.
- `index.html`: Frontend interface using jQuery for sending and displaying notifications.
- `fetch_notifications.php`: Backend script for long polling fallback to fetch notifications.
- `schema.sql`: SQL script to create the `notifications` table.

## Notes

- Replace `your_username`, `your_password`, and `social_app` in the PHP files with your actual MySQL credentials and database name.
- For production, secure the WebSocket connection with WSS (WebSocket Secure) and implement user authentication.
- If WebSockets are not feasible, you can rely solely on long polling by removing the WebSocket code and using only `fetch_notifications.php`.
- Ensure your web server and WebSocket server are running simultaneously.

## Troubleshooting

- **WebSocket Connection Fails**: Check if port 8080 is open and the WebSocket server (`notification_system.php`) is running.
- **Database Errors**: Verify MySQL credentials and ensure the `notifications` table exists.
- **Notifications Not Displaying**: Ensure jQuery is loaded and check the browser console for errors.

## License

This project is licensed under the MIT License.