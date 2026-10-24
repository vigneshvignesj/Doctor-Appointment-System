<?php

namespace Core;

class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function field(): string
    {
        $token = htmlspecialchars(self::token(), ENT_QUOTES);
        return "<input type=\"hidden\" name=\"csrf_token\" value=\"{$token}\">";
    }

    public static function verify(?string $token): bool
    {
        return is_string($token)
            && !empty($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function verifyRequest(): bool
    {
        // JSON requests (e.g. the Concurrency Stress Test's fetch() calls)
        // carry the token in the body rather than a form-encoded POST field.
        if (str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            return self::verify($body['csrf_token'] ?? null);
        }
        return self::verify($_POST['csrf_token'] ?? null);
    }
}
