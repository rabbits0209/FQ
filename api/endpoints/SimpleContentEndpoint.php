<?php
/**
 * 简化的内容API端点
 * 专注于处理基本的章节内容获取
 */

class SimpleContentEndpoint extends BaseEndpoint
{
    public function handle()
    {
        try {
            // 获取参数
            $item_ids = $_GET['item_ids'] ?? null;
            
            if (!$item_ids) {
                $this->sendError(400, '缺少item_ids参数');
                return;
            }
            
            // 调用主处理逻辑
            $this->handleContent($item_ids);
            
        } catch (Exception $e) {
            $this->sendError(500, '处理失败: ' . $e->getMessage(), $e->getTraceAsString());
        }
    }
    
    private function handleContent($item_ids)
    {
        // 获取密钥信息
        $keyFile = $_SERVER['DOCUMENT_ROOT'] . '/key.json';
        if (!file_exists($keyFile)) {
            throw new Exception('设备密钥文件不存在');
        }
        
        $keyData = json_decode(file_get_contents($keyFile), true);
        if (!preg_match('/(.*)@(.*)/', $keyData['key'], $matches)) {
            throw new Exception('无效的密钥格式');
        }
        
        $zwkey1 = $matches[1]; // 密钥
        $zwkey2 = $matches[2]; // 设备ID
        
        // 构建URL - 使用batch接口
        $url = "https://api5-normal-sinfonlineb.fqnovel.com/reading/reader/batch_full/v?aid=1967&app_name=novelapp&channel=0&device_platform=android&device_id={$zwkey2}&device_type=Honor10&os_version=0&version_code=66.9&book_id=0&item_ids={$item_ids}&novel_text_type=1&req_type=1";
        
        // 生成签名
        $query_string = parse_url($url, PHP_URL_QUERY);
        $xg_data = $this->generateSignature($query_string);
        
        // 发送请求
        $response = $this->makeRequest($url, $xg_data);
        
        // 处理响应
        $responseData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON解析失败: ' . json_last_error_msg());
        }
        
        // 添加调试信息
        if (empty($responseData['data']) || !is_array($responseData['data'])) {
            $this->sendError(500, '响应数据格式错误', [
                'response_data' => $responseData,
                'url' => $url,
                'query_string' => $query_string
            ]);
            return;
        }
        
        // 处理章节数据
        $chapter_list = [];
        foreach ($responseData['data'] as $chapterInfo) {
            if (empty($chapterInfo['content'])) {
                // 记录空内容的章节信息
                $chapter_list[] = [
                    'error' => '章节内容为空',
                    'chapter_info' => $chapterInfo
                ];
                continue;
            }
            
            // 检查content内容，如果是Invalid，尝试刷新设备密钥
            if ($chapterInfo['content'] === 'Invalid') {
                $this->refreshDevice();
                $chapter_list[] = [
                    'error' => '章节内容无效，已尝试刷新设备密钥',
                    'content' => $chapterInfo['content'],
                    'chapter_info' => $chapterInfo,
                    'suggestion' => '请稍后重试，或检查设备密钥是否需要重新注册'
                ];
                continue;
            }
            
            if (strlen($chapterInfo['content']) < 20) {
                $chapter_list[] = [
                    'error' => '章节内容太短',
                    'content' => $chapterInfo['content'],
                    'chapter_info' => $chapterInfo
                ];
                continue;
            }
            
            try {
                // 解密内容
                $decrypted = $this->simpleDecrypt($chapterInfo['content'], $zwkey1);
                $chapterInfo['content'] = $this->cleanContent($decrypted);
                $chapter_list[] = $chapterInfo;
            } catch (Exception $e) {
                $chapter_list[] = [
                    'error' => '解密失败: ' . $e->getMessage(),
                    'content_preview' => substr($chapterInfo['content'], 0, 50),
                    'chapter_info' => $chapterInfo
                ];
            }
        }
        
        if (empty($chapter_list)) {
            $this->sendError(500, '所有章节均无内容');
            return;
        }
        
        $this->sendSuccess(['chapters' => $chapter_list]);
    }
    
    private function generateSignature($query_string)
    {
        // 简化的签名生成，直接调用算法
        $config = json_decode(file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/config.json'), true);
        $algorithm = $config['zwsf'] ?? '8404';
        
        require_once $_SERVER['DOCUMENT_ROOT'] . '/config/' . $algorithm . '.php';
        return generate_x_gorgon($query_string);
    }
    
    private function makeRequest($url, $xg_data)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => [
                'X-Gorgon: ' . $xg_data['x_gorgon'],
                'X-Khronos: ' . $xg_data['timestamp'],
                'User-Agent: com.dragon.read'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("请求失败: {$error}");
        }
        
        return $response;
    }
    
    private function simpleDecrypt($encrypted, $secretKey)
    {
        $raw = base64_decode($encrypted);
        if (strlen($raw) < 16) {
            $this->refreshDevice();
            throw new Exception('加密数据太短，已尝试刷新设备');
        }
        
        $iv = substr($raw, 0, 16);
        $ciphertext = substr($raw, 16);
        
        if (strlen(hex2bin($secretKey)) !== 16) {
            $this->refreshDevice();
            throw new Exception('密钥长度错误，已尝试刷新设备');
        }
        
        $decrypted = openssl_decrypt(
            $ciphertext,
            'AES-128-CBC',
            hex2bin($secretKey),
            OPENSSL_RAW_DATA,
            $iv
        );
        
        if ($decrypted === false) {
            $this->refreshDevice();
            throw new Exception('解密失败，已尝试刷新设备');
        }
        
        return @gzdecode($decrypted) ?: $decrypted;
    }
    
    private function refreshDevice()
    {
        // 调用设备刷新接口，模仿原content.php的refreshPage功能
        try {
            $opts = [
                'http' => [
                    'method' => 'GET',
                    'header' => 'User-Agent: Mozilla/5.0 (PHP script)\r\n',
                    'timeout' => 10
                ]
            ];
            $context = stream_context_create($opts);
            @file_get_contents('http://' . $_SERVER['HTTP_HOST'] . '/mygx.php', false, $context);
        } catch (Exception $e) {
            // 忽略刷新错误，继续抛出原始错误
        }
    }
    
    private function cleanContent($content)
    {
        // 简化的内容清理
        $patterns = [
            '/<p class=\"pictureDesc\"[^>]*>/',
            '/<\/body>|<\/html>|<\/div>/',
            '/<p class=\"picture\"[^>]*>/',
            '/<div data-fanqie-type=\"image\"[^>]*>/',
            '/<head>.*<\/h1>/',
            '/<!DOCTYPE.*<html>/',
            '/<\?xml.*\?>/',
            '/<p idx="[^"]*">/',
            '/<header>|<\/header>/',
            '/<article>|<\/article>/',
            '/<footer>|<\/footer>/',
            '/<tt_keyword[^>]*>/',
            '/<p>/'
        ];
        
        $content = preg_replace($patterns, '', $content);
        $content = preg_replace('/&amp;x/', "&x", $content);
        return preg_replace('/<\/p>/', "\n", $content);
    }
}
