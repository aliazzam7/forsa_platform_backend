<?php
class JWT {
    private static string $secret = 'forsa_super_secret_key_2025';
    private static int    $expiry  = 86400; // 24 hours

    public static function generate(array $payload): string {
        $header  = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload['exp'] = time() + self::$expiry;
        $payload = base64_encode(json_encode($payload));
        $sig     = base64_encode(hash_hmac('sha256', "$header.$payload", self::$secret, true));
        return "$header.$payload.$sig";
    }

    public static function verify(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        [$header, $payload, $sig] = $parts;
        $expectedSig = base64_encode(hash_hmac('sha256', "$header.$payload", self::$secret, true));

        if (!hash_equals($expectedSig, $sig)) return null;

        $data = json_decode(base64_decode($payload), true);
        if (!$data || $data['exp'] < time()) return null;

        return $data;
    }
}