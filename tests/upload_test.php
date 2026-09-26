<?php
/**
 * upload_test.php —— 命令行上传测试脚本
 *
 * 用法：
 *   php tests/upload_test.php <API地址> [TOKEN]
 * 示例：
 *   php tests/upload_test.php http://localhost:8080/api/upload
 *   php tests/upload_test.php https://your-cnb-domain.cnb.run/api/upload your_token
 */

$url   = $argv[1] ?? 'http://localhost:8080/api/upload';
$token = $argv[2] ?? '';

// 生成一张 1x1 的测试 PNG
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC');
$tmp = tempnam(sys_get_temp_dir(), 't_') . '.png';
file_put_contents($tmp, $png);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => $token !== '' ? ["Authorization: Bearer $token"] : [],
    CURLOPT_POSTFIELDS     => ['file' => new CURLFile($tmp, 'image/png', 'test.png')],
    CURLOPT_TIMEOUT        => 30,
]);

$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);
@unlink($tmp);

echo "HTTP 状态码: $code\n";
if ($err !== '') {
    echo "cURL 错误: $err\n";
}
echo "响应体:\n";
echo ($resp === false ? '(无响应)' : $resp) . "\n";
