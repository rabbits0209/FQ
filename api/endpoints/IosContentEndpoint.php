<?php
/**
 * iOS内容API端点
 * 处理iOS设备的内容获取请求
 * 完全按照原ios_content.php的逻辑实现
 */

class IosContentEndpoint extends BaseEndpoint
{
    public function handle()
    {
        $itemId = $_REQUEST['item_id'] ?? '';
        if (!$itemId) {
            $this->sendError(400, '缺少item_id参数');
        }

        try {
            $this->getIosContent($itemId);
        } catch (Exception $e) {
            $this->sendError(500, $e->getMessage());
        }
    }

    /**
     * 获取iOS设备内容 - 完全按照原ios_content.php的逻辑
     */
    private function getIosContent($itemId)
    {
        // 读取ios_key.json获取device_id
        $keyFile = $_SERVER['DOCUMENT_ROOT'] . '/ios_key.json';
        if (!file_exists($keyFile)) {
            $this->sendError(500, 'iOS密钥文件不存在');
        }

        $keyData = json_decode(file_get_contents($keyFile), true);
        if (!isset($keyData['key'])) {
            $this->sendError(500, 'iOS密钥文件格式错误');
        }

        // 取@后面的部分作为device_id，@前面的部分作为解密密钥
        $parts = explode('@', $keyData['key']);
        $secretKey = isset($parts[0]) ? $parts[0] : '';
        $deviceId = isset($parts[1]) ? $parts[1] : '';

        if (!$secretKey || !$deviceId) {
            $this->sendError(500, 'iOS密钥格式错误，应为"密钥@device_id"');
        }

        $params = [
            'item_id' => $itemId,
            'device_id' => $deviceId,
            'aid' => '507427',
            'device_platform' => 'iphone'
        ];

        // 拼接URL
        $queryString = http_build_query($params);
        $baseUrl = 'https://reading.snssdk.com/reading/reader/full/v';
        $url = $baseUrl . '?' . $queryString;

        // 使用iOS专用算法8402生成签名
        require_once $_SERVER['DOCUMENT_ROOT'] . '/config/8402.php';
        $xgData = generate_x_gorgon($queryString);

        // 发送请求
        $response = $this->curlRequest($url, [
            'X-Gorgon: ' . $xgData['x_gorgon'],
            'X-Khronos: ' . $xgData['timestamp'],
            'User-Agent: com.dragon.read',
            'Connection: keep-alive'
        ]);

        if (empty($response)) {
            $this->sendError(500, '响应为空', [
                'possible_reasons' => [
                    'X-Gorgon生成错误（检查8402.php中的generate_x_gorgon函数）',
                    '参数过期（比如device_id、iid可能有有效期）',
                    '接口限制（IP被临时封禁）'
                ]
            ]);
        }

        // 解析响应
        $responseArr = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendError(500, 'JSON解析失败: ' . json_last_error_msg());
        }

        // 解密正文内容 - 完全按照原文件逻辑
        if (isset($responseArr['data']['content']) && $secretKey) {
            $decryptedContent = $this->decrypt($responseArr['data']['content'], $secretKey);
            $processedContent = $this->processContent($decryptedContent);
            $responseArr['data']['content'] = $processedContent;
        }

        // 返回用户要求的格式
        $result = [
            'success' => true,
            'data' => [
                'content' => $responseArr['data']['content'] ?? ''
            ]
        ];

        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * 解密内容 - 完全按照原ios_content.php的decrypt函数
     */
    protected function decrypt($encrypted, $secretKey)
    {
        $raw = base64_decode($encrypted);
        if (strlen($raw) < 16) {
            return '';
        }
        
        $iv = substr($raw, 0, 16);
        $ciphertext = substr($raw, 16);
        
        if (strlen(hex2bin($secretKey)) !== 16) {
            return '';
        }
        
        $decrypted = openssl_decrypt(
            $ciphertext,
            'AES-128-CBC',
            hex2bin($secretKey),
            OPENSSL_RAW_DATA,
            $iv
        );
        
        if ($decrypted === false) {
            return '';
        }
        
        return @gzdecode($decrypted) ?: $decrypted;
    }

    /**
     * 处理内容 - 完全按照原ios_content.php的processContent函数
     */
    protected function processContent($content)
    {
        $patterns = [
            '/<p class=\\"pictureDesc\\" group-id=\\"\\d+" idx=\\"\\d+">/',
            '/<\\/body>|<\\/html>|<\\/div>/',
            '/<p class=\\"picture\\" group-id=\\"\\d+">/',
            '/<div data-fanqie-type=\\"image\\" source=\\"user\\">/',
            '/<head>.*<\\/h1>/',
            '/<!DOCTYPE.*<html>/',
            '/<\\?xml.*\\?>/',
            '/<p idx=\\"\\d+">/',
            '/<header>|<\\/header>/',
            '/<article>|<\\/article>/',
            '/<footer>|<\\/footer>/',
            '/<tt_keyword.*keyword_ad>/',
            '/<p>/'
        ];
        
        $content = preg_replace($patterns, '', $content);
        $content = preg_replace('/&amp;x/', "&x", $content);
        return preg_replace('/<\\/p>/', "\n", $content);
    }
}
