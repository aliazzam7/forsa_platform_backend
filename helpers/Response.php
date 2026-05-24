<?php
class Response {
    public static function json(bool $success, string $message, $data = null, int $code = 200): void {
        http_response_code($code);
        $res = ['success' => $success, 'message' => $message];
        if ($data !== null) $res['data'] = $data;
        echo json_encode($res);
        exit;
    }

    public static function success($data = null, string $message = 'OK', int $code = 200): void {
        self::json(true, $message, $data, $code);
    }

    public static function error(string $message, int $code = 400): void {
        self::json(false, $message, null, $code);
    }
}