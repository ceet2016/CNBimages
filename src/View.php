<?php
/**
 * View.php —— 极简模板渲染（变量 extract + 输出转义辅助）
 */

declare(strict_types=1);

namespace App;

final class View
{
    /**
     * @param array<string,mixed> $data
     */
    public static function render(string $name, array $data = []): string
    {
        $file = __DIR__ . '/Views/' . $name . '.php';
        if (!file_exists($file)) {
            return '';  // 视图缺失时返回空，避免暴露路径
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return ob_get_clean();
    }

    /** HTML 转义（在模板中调用） */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
