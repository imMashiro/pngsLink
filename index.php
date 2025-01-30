<?php
declare(strict_types=1);

require_once 'config.php';
require_once 'WebDAVClient.php';

$webdav = new WebDAVClient(WEBDAV_URL, WEBDAV_USERNAME, WEBDAV_PASSWORD);

/**
 * 验证上传Token
 */
function validateToken(): bool {
    if (empty(ALLOWED_TOKENS)) {
        return true; // 如果未设置token，则不进行验证
    }
    
    $token = $_SERVER['HTTP_X_UPLOAD_TOKEN'] ?? null;
    return $token !== null && in_array($token, ALLOWED_TOKENS, true);
}

/**
 * 处理图片请求
 */
if (isset($_GET['f'])) {
    try {
        $file = $_GET['f'];

        // 安全检查：防止目录遍历和非法访问
        if (strpos($file, '..') !== false || 
            !preg_match('/^\/uploads\/\d{4}\/\d{2}\/\d{2}\/[a-f0-9]{64}\.(jpg|png|gif|webp)$/', $file)) {
            throw new Exception('Invalid file path', 400);
        }

        // 使用 basename() 来避免路径注入
        $fileName = basename($file);

        // 获取文件扩展名并设置对应的 Content-Type
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        $allowedTypes = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
        
        if (!array_key_exists($ext, $allowedTypes)) {
            throw new Exception('Invalid file type', 400);
        }

        // 获取图片内容
        $imageContent = $webdav->getFile($file);

        if ($imageContent === false) {
            throw new Exception('File not found or cannot be read', 404);
        }

        // 使用文件的实际 MIME 类型，增强安全性
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $imageContent);
        finfo_close($finfo);

        if ($mimeType !== $allowedTypes[$ext]) {
            throw new Exception('MIME type mismatch', 400);
        }

        // 设置缓存控制头（1年）
        $expiresTime = time() + CACHE_DURATION;
        header('Cache-Control: public, max-age=' . CACHE_DURATION);
        header('Expires: ' . gmdate('D, d M Y H:i:s', $expiresTime) . ' GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT');
        header('ETag: "' . md5($imageContent) . '"');
        header('Content-Type: ' . $allowedTypes[$ext]);
        header('Content-Length: ' . strlen($imageContent));
        
        echo $imageContent;
        exit;

    } catch (Exception $e) {
        // 错误处理和日志记录
        error_log($e->getMessage());  // 在服务器日志中记录错误
        http_response_code($e->getCode() ?: 500);
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

/**
 * 处理图片上传
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        // 验证Token
        if (!validateToken()) {
            throw new Exception('Invalid or missing upload token', 403);
        }

        if (!isset($_FILES['image'])) {
            throw new Exception('No file uploaded', 400);
        }

        $file = $_FILES['image'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Upload failed: ' . $file['error'], 400);
        }

        // 验证文件大小
        if ($file['size'] > MAX_FILE_SIZE) {
            throw new Exception('File too large (max ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB)', 400);
        }

        // 验证文件类型
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!isset(ALLOWED_TYPES[$mimeType])) {
            throw new Exception('Invalid file type', 400);
        }

        // 生成文件路径
        $ext = ALLOWED_TYPES[$mimeType];
        $sha256 = hash_file('sha256', $file['tmp_name']);
        $datePath = date('/Y/m/d/');
        $remotePath = '/uploads' . $datePath . $sha256 . '.' . $ext;

        // 上传文件
        if (!$webdav->uploadFile($file['tmp_name'], $remotePath)) {
            throw new Exception('Upload failed', 500);
        }

        // 返回成功信息
        echo json_encode([
            'success' => true,
            'url' => 'index.php?f=' . $remotePath,
            'hash' => $sha256,
            'size' => $file['size'],
            'type' => $mimeType
        ], JSON_UNESCAPED_SLASHES);

    } catch (Exception $e) {
        http_response_code($e->getCode() ?: 500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// 显示上传表单
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>WebDAV Image Uploader</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 2em auto; padding: 0 1em; }
        form { margin: 2em 0; }
        .error { color: red; }
        .success { color: green; }
        .token-input { margin-bottom: 1em; }
    </style>
</head>
<body>
    <h1>Image Uploader</h1>
    <form method="POST" enctype="multipart/form-data">
        <?php if (!empty(ALLOWED_TOKENS)): ?>
        <div class="token-input">
            <label for="token">Upload Token:</label>
            <input type="password" id="token" required>
        </div>
        <?php endif; ?>
        <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp" required>
        <button type="submit">Upload</button>
    </form>
    <div id="result"></div>

    <script>
    document.querySelector('form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        
        try {
            const token = document.getElementById('token')?.value;
            const response = await fetch('', {
                method: 'POST',
                body: formData,
                headers: token ? {
                    'X-Upload-Token': token
                } : {}
            });
            
            const result = await response.json();
            const resultDiv = document.getElementById('result');
            
            if (result.error) {
                resultDiv.className = 'error';
                resultDiv.textContent = `Error: ${result.error}`;
            } else {
                resultDiv.className = 'success';
                resultDiv.innerHTML = `
                    Upload successful!<br>
                    URL: <a href="${result.url}" target="_blank">${result.url}</a><br>
                    Hash: ${result.hash}<br>
                    Size: ${(result.size / 1024).toFixed(2)}KB
                `;
            }
        } catch (err) {
            document.getElementById('result').className = 'error';
            document.getElementById('result').textContent = `Error: ${err.message}`;
        }
    });
    </script>
</body>
</html>
