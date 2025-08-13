<?php
/**
 * API端点基类
 * 提供公共功能如配置加载等
 */

abstract class BaseEndpoint
{
    protected $config;
    protected $algorithmManager;

    public function __construct()
    {
        $this->config = $this->loadConfig();
        $this->algorithmManager = new AlgorithmManager($this->config);
    }

    /**
     * 抽象方法，子类必须实现
     */
    abstract public function handle();

    /**
     * 加载配置文件
     */
    protected function loadConfig()
    {
        $configPath = $_SERVER['DOCUMENT_ROOT'] . '/config.json';
        if (!file_exists($configPath)) {
            throw new Exception('配置文件不存在');
        }

        $config = json_decode(file_get_contents($configPath), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('配置文件解析失败: ' . json_last_error_msg());
        }

        return $config;
    }

    /**
     * 发送成功响应
     */
    protected function sendSuccess($data)
    {
        echo json_encode([
            'success' => true,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * 发送错误响应
     */
    protected function sendError($httpCode, $error, $message = null, $trace = null)
    {
        http_response_code($httpCode);
        $response = [
            'success' => false,
            'error' => $error
        ];
        
        if ($message) {
            $response['message'] = $message;
        }
        
        if ($trace) {
            $response['trace'] = $trace;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * 执行CURL请求
     */
    protected function curlRequest($url, $headers = [], $postData = null, $timeout = 10)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => $timeout
        ]);

        if ($postData !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("CURL Error: {$error}");
        }

        if ($httpCode !== 200) {
            throw new Exception("HTTP Error: {$httpCode}");
        }

        return $response;
    }

    /**
     * 执行CURL POST请求的便捷方法
     */
    protected function curlPostRequest($url, $postData, $headers = [], $timeout = 10)
    {
        return $this->curlRequest($url, $headers, $postData, $timeout);
    }

    /**
     * 获取设备密钥信息
     */
    protected function getDeviceKeys($isIos = false)
    {
        $keyFile = $isIos ? 
            $_SERVER['DOCUMENT_ROOT'] . '/ios_key.json' : 
            $_SERVER['DOCUMENT_ROOT'] . '/key.json';

        if (!file_exists($keyFile)) {
            throw new Exception('设备密钥文件不存在，请先注册设备');
        }

        $keyData = json_decode(file_get_contents($keyFile), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('设备密钥文件解析失败');
        }

        if (!isset($keyData['key']) || !preg_match('/(.*)@(.*)/', $keyData['key'], $matches)) {
            throw new Exception('无效的设备密钥格式，应为"密钥@device_id"');
        }

        return [
            'secret_key' => $matches[1],
            'device_id' => $matches[2],
            'full_key' => $keyData['key']
        ];
    }

    /**
     * 处理内容（清理HTML标签等）
     * 完全复制原content.php的processContent函数逻辑
     */
    protected function processContent($content)
    {
        // 清理HTML标签和格式化内容，完全按照原content.php的逻辑
        $patterns = [
            '/<p class=\"pictureDesc\" group-id=\"\d+" idx=\"\d+">/',
            '/<\/body>|<\/html>|<\/div>/',
            '/<p class=\"picture\" group-id=\"\d+\">/',
            '/<div data-fanqie-type=\"image\" source=\"user\">/',
            '/<head>.*<\/h1>/',
            '/<!DOCTYPE.*<html>/',
            '/<\?xml.*\?>/',
            '/<p idx="\d+">/',
            '/<header>|<\/header>/',
            '/<article>|<\/article>/',
            '/<footer>|<\/footer>/',
            '/<tt_keyword.*keyword_ad>/',
            '/<p>/'
        ];

        $content = preg_replace($patterns, '', $content);
        $content = preg_replace('/&amp;x/', "&x", $content);
        return preg_replace('/<\/p>/', "\n", $content);
    }

    /**
     * AES解密
     * 复制原content.php的解密逻辑，包括refreshPage机制
     */
    protected function decrypt($encrypted, $secretKey)
    {
        $raw = base64_decode($encrypted);
        if (strlen($raw) < 16) {
            $this->refreshPage();
        }

        $iv = substr($raw, 0, 16);
        $ciphertext = substr($raw, 16);

        if (strlen(hex2bin($secretKey)) !== 16) {
            $this->refreshPage();
        }

        $decrypted = openssl_decrypt(
            $ciphertext,
            'AES-128-CBC',
            hex2bin($secretKey),
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($decrypted === false) {
            $this->refreshPage();
        }

        return @gzdecode($decrypted) ?: $decrypted;
    }

    /**
     * 刷新页面机制，复制自原content.php
     */
    private function refreshPage()
    {
        // 调用设备刷新接口
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: Mozilla/5.0 (PHP script)\r\n'
            ]
        ];
        $context = stream_context_create($opts);
        @file_get_contents('http://' . $_SERVER['HTTP_HOST'] . '/mygx.php', false, $context);

        // 刷新当前页
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}
