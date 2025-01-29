<?php
declare(strict_types=1);

define('WEBDAV_URL', 'https://yourdomain.com/dav');
define('WEBDAV_USERNAME', 'username');
define('WEBDAV_PASSWORD', 'ttttttoken');

define('ALLOWED_TOKENS', [
    '1234',
    '5678'
]);


define('ALLOWED_TYPES', [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
    'image/bmp' => 'bmp'
]);

define('MAX_FILE_SIZE', 10 * 1024 * 1024);
date_default_timezone_set('UTC+8:00');
define('CACHE_DURATION', 31536000);
