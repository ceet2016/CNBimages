<?php
/**
 * ImageProcessor.php —— 基于 GD 的图片处理（校验 / 缩放 / 格式转换 / 压缩）
 */

declare(strict_types=1);

namespace App;

final class ImageProcessor
{
    /** 允许的图片类型（扩展名 => GD 加载函数 / 保存格式） */
    private const LOADERS = [
        IMAGETYPE_JPEG => 'imagecreatefromjpeg',
        IMAGETYPE_PNG  => 'imagecreatefrompng',
        IMAGETYPE_GIF  => 'imagecreatefromgif',
        IMAGETYPE_WEBP => 'imagecreatefromwebp',
        IMAGETYPE_AVIF => 'imagecreatefromavif',
    ];

    /** 读取图片真实信息；非图片或不可读返回 null */
    public static function info(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        $g = @getimagesize($path);
        if ($g === false) {
            return null;
        }
        $mime = $g['mime'];
        $ext = self::extFromMime($mime);
        if ($ext === null) {
            return null;
        }
        return ['width' => $g[0], 'height' => $g[1], 'mime' => $mime, 'ext' => $ext, 'type' => $g[2]];
    }

    /**
     * 等比缩放并转换格式，写入 $dest。
     * $maxW/$maxH 为 0 表示不限制该维度；$fmt 支持 jpg/png/webp/avif。
     */
    public static function resize(
        string $srcPath,
        string $destPath,
        int $maxW,
        int $maxH,
        string $fmt,
        int $quality = 85
    ): bool {
        $info = self::info($srcPath);
        if ($info === null) {
            return false;
        }
        $loader = self::LOADERS[$info['type']] ?? null;
        if (!is_callable($loader)) {
            return false;
        }
        $src = $loader($srcPath);
        if ($src === false) {
            return false;
        }

        $ow = $info['width'];
        $oh = $info['height'];
        $ratio = $ow / $oh;
        if ($maxW > 0 && $maxH > 0) {
            $nw = min($maxW, $ow);
            $nh = (int) round($nw / $ratio);
            if ($nh > $maxH) {
                $nh = $maxH;
                $nw = (int) round($nh * $ratio);
            }
        } elseif ($maxW > 0) {
            $nw = min($maxW, $ow);
            $nh = (int) round($nw / $ratio);
        } elseif ($maxH > 0) {
            $nh = min($maxH, $oh);
            $nw = (int) round($nh * $ratio);
        } else {
            $nw = $ow;
            $nh = $oh;
        }

        $dst = imagecreatetruecolor($nw, $nh);
        if ($dst === false) {
            return false;
        }
        // 保留透明度（非 jpg）
        if ($fmt !== 'jpg' && $fmt !== 'jpeg') {
            imagecolortransparent($dst, imagecolorallocatealpha($dst, 0, 0, 0, 127));
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $ow, $oh);

        $ok = self::save($dst, $destPath, $fmt, $quality);
        imagedestroy($src);
        imagedestroy($dst);
        return $ok;
    }

    private static function save(\GdImage $im, string $destPath, string $fmt, int $quality): bool
    {
        $dir = dirname($destPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $ok = match ($fmt) {
            'jpg', 'jpeg' => imagejpeg($im, $destPath, $quality),
            'png'         => imagepng($im, $destPath, (int) round(9 * $quality / 100)),
            'webp'        => function_exists('imagewebp') ? imagewebp($im, $destPath, $quality) : false,
            'avif'        => function_exists('imageavif') ? imageavif($im, $destPath, $quality) : false,
            default       => false,
        };
        return (bool) $ok;
    }

    public static function supportedFmts(): array
    {
        $fmts = ['jpg', 'png'];
        if (function_exists('imagewebp')) {
            $fmts[] = 'webp';
        }
        if (function_exists('imageavif')) {
            $fmts[] = 'avif';
        }
        return $fmts;
    }

    public static function extFromMime(string $mime): ?string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            default      => null,
        };
    }
}
