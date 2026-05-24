<?php
class ContactController {

    // POST /api/contact  — public, no auth
    public static function send(): void {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $err = Validator::required($body, ['name', 'email', 'message']);
        if ($err) Response::error($err);

        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            'INSERT INTO contact_messages (name, email, message) VALUES (?, ?, ?)'
        );
        $stmt->execute([
            trim($body['name']),
            trim($body['email']),
            trim($body['message']),
        ]);

        Response::success(null, 'Message sent successfully.');
    }

    // GET /api/admin/messages  — admin only
    public static function index(): void {
        $payload = AuthMiddleware::handle();
        RoleMiddleware::require($payload, 'admin');

        $db   = Database::getInstance()->getConnection();
        $rows = $db->query(
            'SELECT * FROM contact_messages ORDER BY created_at DESC'
        )->fetchAll(PDO::FETCH_ASSOC);

        Response::success($rows);
    }

    // PUT /api/admin/messages/:id/read  — admin only
    public static function markRead(int $id): void {
        $payload = AuthMiddleware::handle();
        RoleMiddleware::require($payload, 'admin');

        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare('UPDATE contact_messages SET is_read = 1 WHERE id = ?');
        $stmt->execute([$id]);

        Response::success(null, 'Marked as read.');
    }
}