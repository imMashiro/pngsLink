<?php
declare(strict_types=1);

// WebDAV服务器配置
define('WEBDAV_URL', 'https://your-webdav-server.com');
define('WEBDAV_USERNAME', 'your_username');
define('WEBDAV_PASSWORD', 'your_password');

// 上传Token配置（为空数组则不启用token验证）
define('ALLOWED_TOKENS', [
    'your-secret-token-1',
    'your-secret-token-2'
]);

// 允许的图片MIME类型及其对应的扩展名
define('ALLOWED_TYPES', [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
    'image/bmp' => 'bmp'
]);

// 最大文件大小 (20MB)
define('MAX_FILE_SIZE', 20 * 1024 * 1024);

// 缓存时间（1年）
define('CACHE_DURATION', 31536000);

// 时区设置
date_default_timezone_set('Asia/Shanghai');
