<?php

namespace Core;

class View
{
    public static function render(string $view, array $data = []): void
    {
        extract($data);
        require __DIR__ . '/../app/views/partials/header.php';
        require __DIR__ . "/../app/views/{$view}.php";
        require __DIR__ . '/../app/views/partials/footer.php';
    }

    /** Renders without the shared header/footer chrome (JSON-ish/bare pages). */
    public static function renderBare(string $view, array $data = []): void
    {
        extract($data);
        require __DIR__ . "/../app/views/{$view}.php";
    }

    public static function flash(string $key, ?string $message = null): ?string
    {
        if ($message !== null) {
            $_SESSION['flash'][$key] = $message;
            return null;
        }
        $value = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $value;
    }

    public static function old(string $field, string $default = ''): string
    {
        return htmlspecialchars($_SESSION['old'][$field] ?? $default, ENT_QUOTES);
    }
}
