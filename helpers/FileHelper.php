<?php
class FileHelper {
    private static string $uploadDir = __DIR__ . '/../uploads/';

    public static function upload(array $file, string $subfolder = ''): ?string {
        $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
        if (!in_array($file['type'], $allowed)) return null;

        $dir = self::$uploadDir . $subfolder;
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('forsa_', true) . '.' . $ext;
        $path     = $dir . '/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $path)) {
            return 'uploads/' . ($subfolder ? $subfolder . '/' : '') . $filename;
        }
        return null;
    }

    public static function delete(string $relativePath): void {
        $full = __DIR__ . '/../' . $relativePath;
        if (file_exists($full)) unlink($full);
    }
}