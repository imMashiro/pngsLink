<?php
declare(strict_types=1);

class WebDAVClient {
    private string $baseUrl;
    private string $username;
    private string $password;

    public function __construct(string $baseUrl, string $username, string $password) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * 创建目录
     * @throws Exception
     */
    public function createDirectory(string $path): bool {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . $path,
            CURLOPT_USERPWD => "{$this->username}:{$this->password}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'MKCOL',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            throw new Exception('CURL Error: ' . curl_error($ch));
        }
        
        curl_close($ch);
        
        return $httpCode === 201 || $httpCode === 405; // 405表示目录已存在
    }

    /**
     * 上传文件
     * @throws Exception
     */
    public function uploadFile(string $localPath, string $remotePath): bool {
        // 确保目标目录存在
        $dirPath = dirname($remotePath);
        $this->createDirectory($dirPath);

        $ch = curl_init();
        $fp = fopen($localPath, 'r');
        
        if ($fp === false) {
            throw new Exception('Failed to open local file');
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . $remotePath,
            CURLOPT_USERPWD => "{$this->username}:{$this->password}",
            CURLOPT_UPLOAD => true,
            CURLOPT_PUT => true,
            CURLOPT_INFILE => $fp,
            CURLOPT_INFILESIZE => filesize($localPath),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            fclose($fp);
            throw new Exception('CURL Error: ' . curl_error($ch));
        }
        
        fclose($fp);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 300;
    }

    /**
     * 获取文件内容
     * @throws Exception
     */
    public function getFile(string $remotePath): string {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . $remotePath,
            CURLOPT_USERPWD => "{$this->username}:{$this->password}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            throw new Exception('CURL Error: ' . curl_error($ch));
        }
        
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('File not found', 404);
        }

        return $response;
    }
}
