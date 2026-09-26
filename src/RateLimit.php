<?php
/**
 * RateLimit.php —— 简易速率限制（基于 SQLite，按 key + 时间窗计数）
 */

declare(strict_types=1);

namespace App;

final class RateLimit
{
    /**
     * 命中一次计数；若窗口内已超上限返回 false（表示被限流）。
     */
    public static function hit(string $key, int $max, int $windowSec): bool
    {
        $pdo = Database::pdo();
        $now = time();
        $stmt = $pdo->prepare('SELECT hits, window_start FROM rate_limit WHERE key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        if ($row === false || $now - (int) $row['window_start'] >= $windowSec) {
            $pdo->prepare('INSERT OR REPLACE INTO rate_limit (key, hits, window_start) VALUES (?, 1, ?)')
                ->execute([$key, $now]);
            return true;
        }
        if ((int) $row['hits'] >= $max) {
            return false;
        }
        $pdo->prepare('UPDATE rate_limit SET hits = hits + 1 WHERE key = ?')
            ->execute([$key]);
        return true;
    }
}
