<?php
namespace App\Core;

class View
{
    public static function render(string $template, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $content = self::renderPartial($template, $data);
        require __DIR__ . '/../../views/layouts/public.php';
    }

    public static function renderPartial(string $template, array $data = []): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require __DIR__ . '/../../views/' . $template;
        return ob_get_clean();
    }
}
