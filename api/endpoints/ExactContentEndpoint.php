<?php
/**
 * 完全按照原content.php逻辑的内容API端点
 */

class ExactContentEndpoint extends BaseEndpoint
{
    public function handle()
    {
        
        // 获取GET参数，完全按照原文件
        $item_id = $_GET['item_ids'] ?? null;
        $ts = $_GET['ts'] ?? null;
        $book_id = $_GET['book_id'] ?? null;
        $comment = $_GET['comment'] ?? null;

        // 处理听书请求
        if ($ts === "听书") {
            $this->handleAudioRequest($item_id);
            return;
        }

        // 处理detail请求
        if ($book_id && !$comment) {
            $this->handleDetailRequest($book_id);
            return;
        }

        // 处理comment请求
        if ($book_id && $comment === "评论") {
            $this->handleCommentRequest($book_id);
            return;
        }

        // 处理主请求
        $this->handleMainRequest($item_id);
    }

    private function handleAudioRequest($item_id)
    {
        if (!$item_id) {
            $this->sendErrorResponse('缺少item_id参数');
        }

        $url = 'https://reading.snssdk.com/reading/reader/audio/playinfo/?tone_id=1&item_ids='.$item_id.'&pv_player=-1&aid=1967';
        
        $response = $this->curlRequest($url, [
            'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1'
        ]);
        
        if (!$response) {
            $this->sendErrorResponse('请求失败');
        }
        
        $responseData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendErrorResponse('JSON解析失败');
        }
        
        $mainUrls = array_column($responseData['data'] ?? [], 'main_url');
        $content = $mainUrls[0] ?? '未找到main_url';
        
        $this->sendSuccessResponse(['content' => $content]);
    }

    private function handleDetailRequest($book_id)
    {
        if (!$book_id) {
            $this->sendErrorResponse('缺少book_id参数');
        }

        // 获取device_id，完全按照原文件逻辑
        $keyData = $this->loadJsonConfig($_SERVER['DOCUMENT_ROOT'] . '/key.json');
        if (!preg_match('/(.*)@(.*)/', $keyData['key'], $matches)) {
            throw new Exception('无效的zwkey格式，应为"密钥@device_id"');
        }
        $device_id = $matches[2];

        $url = "https://reading.snssdk.com/reading/bookapi/detail/v/?aid=1967&app_name=novelapp&book_id={$book_id}&channel=0&device_id={$device_id}&device_platform=android&device_type=Honor10&os_version=0&version_code=66.9&version_name=5.8.9.32";

        // 加载签名生成文件，完全按照原文件
        $config = $this->loadJsonConfig($_SERVER['DOCUMENT_ROOT'] . '/config.json');
        require_once $_SERVER['DOCUMENT_ROOT'] .'/config/'.$config['zwsf'].'.php';
        $query_string = parse_url($url, PHP_URL_QUERY);
        $xg_data = generate_x_gorgon($query_string);
        
        $response = $this->curlRequest($url, [
            'X-Gorgon: '.$xg_data['x_gorgon'],
            'X-Khronos: '.$xg_data['timestamp'],
            'User-Agent: com.dragon.read'
        ]);
        
        $responseData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendErrorResponse('JSON解析失败: ' . json_last_error_msg());
        }

        $this->sendSuccessResponse($responseData);
    }

    private function handleCommentRequest($book_id)
    {
        if (!$book_id) {
            $this->sendErrorResponse('缺少book_id参数');
        }

        // 获取device_id，完全按照原文件逻辑
        $keyData = $this->loadJsonConfig($_SERVER['DOCUMENT_ROOT'] . '/key.json');
        if (!preg_match('/(.*)@(.*)/', $keyData['key'], $matches)) {
            throw new Exception('无效的zwkey格式，应为"密钥@device_id"');
        }
        $device_id = $matches[2];

        // 获取count参数，默认1
        $count = $_GET['count'] ?? 1;
        $offset = $_GET['offset'] ?? 0;

        $url = "https://api3-normal-sinfonlineb.fqnovel.com/reading/ugc/novel_comment/book/v/?app_name=novelapp&channel=0&book_id={$book_id}&device_type=Honor10&aid=1967&version_name=5.1.5.32&count={$count}&os_version=9.3.5&device_platform=android&version_code=515&device_id={$device_id}&offset={$offset}";

        // 加载签名生成文件，完全按照原文件
        $config = $this->loadJsonConfig($_SERVER['DOCUMENT_ROOT'] . '/config.json');
        require_once $_SERVER['DOCUMENT_ROOT'] .'/config/'.$config['zwsf'].'.php';
        $query_string = parse_url($url, PHP_URL_QUERY);
        $xg_data = generate_x_gorgon($query_string);
        
        $response = $this->curlRequest($url, [
            'X-Gorgon: '.$xg_data['x_gorgon'],
            'X-Khronos: '.$xg_data['timestamp'],
            'User-Agent: com.dragon.read'
        ]);
        
        $responseData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendErrorResponse('JSON解析失败: ' . json_last_error_msg());
        }

        $this->sendSuccessResponse($responseData);
    }

    /**
     * 处理主请求 - 完全按照原content.php的handleMainRequest函数
     */
    private function handleMainRequest($item_id)
    {
        try {
            // 检查必要参数
            if (!$item_id) {
                $this->sendErrorResponse('缺少item_ids参数');
                return;
            }

            $keyData = $this->loadJsonConfig($_SERVER['DOCUMENT_ROOT'] . '/key.json');
            if (!preg_match('/(.*)@(.*)/', $keyData['key'], $matches)) {
                throw new Exception('无效的zwkey格式，应为"密钥@device_id"');
            }
            [$zwkey1, $zwkey2] = [$matches[1], $matches[2]];

            // 关键：这里要和原文件保持一致！
            $api_type = $_GET['api_type'] ?? 'full'; // 默认full，支持batch
            $custom_url = $_GET['custom_url'] ?? '';
            $item_ids = $item_id; // 支持多章，逗号分隔

            if ($custom_url) {
                // 用户自定义url，变量替换
                $url = str_replace([
                    '{$zwkey2}', '{$item_id}'
                ], [
                    $zwkey2, $item_ids
                ], $custom_url);
            } else if ($api_type === 'full') {
                // 官方full/v接口
                $url = "https://reading.snssdk.com/reading/reader/full/v/?aid=1967&app_name=novelapp&channel=0&device_platform=android&device_id={$zwkey2}&device_type=Honor10&item_id={$item_ids}&os_version=0&version_code=66.9";
            } else {
                // 默认batch_full/v接口，支持多章 - 修改返回格式为content拼接
                $url = "https://api5-normal-sinfonlineb.fqnovel.com/reading/reader/batch_full/v?aid=1967&app_name=novelapp&channel=0&device_platform=android&device_id={$zwkey2}&device_type=Honor10&os_version=0&version_code=66.9&book_id=0&item_ids={$item_ids}&novel_text_type=1&req_type=1";
            }

            // 加载签名生成文件，完全按照原文件
            $config = $this->loadJsonConfig($_SERVER['DOCUMENT_ROOT'] . '/config.json');
            require_once $_SERVER['DOCUMENT_ROOT'] .'/config/'.$config['zwsf'].'.php';
            $query_string = parse_url($url, PHP_URL_QUERY);
            $xg_data = generate_x_gorgon($query_string);
            
            $response = $this->curlRequest($url, [
                'X-Gorgon: '.$xg_data['x_gorgon'],
                'X-Khronos: '.$xg_data['timestamp'],
                'User-Agent: com.dragon.read'
            ]);
            
            $responseData = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('JSON解析失败: ' . json_last_error_msg());
            }
            
            // full/v接口返回结构不同，兼容处理 - 完全按照原文件
            if ($api_type === 'full' || strpos($url, '/full/v/') !== false) {
                if (empty($responseData['data']['content'])) {
                    throw new Exception('响应中缺少data.content字段');
                }
                $decrypted_content = $this->decrypt($responseData['data']['content'], $zwkey1);
                $processed_content = $this->processContent($decrypted_content);
                $processed_content .= $config['zwsm'];
                $this->sendSuccessResponse([
                    'content' => $processed_content
                ]);
            } else {
                // 默认batch_full/v接口，支持多章 - 返回每章一个对象，含title和content
                if (empty($responseData['data']) || !is_array($responseData['data'])) {
                    $this->sendErrorResponse('响应中缺少data字段或格式不正确', $responseData);
                }
                $chapter_list = [];
                foreach ($responseData['data'] as $chapterInfo) {
                    if (empty($chapterInfo['content'])) continue;
                    $decrypted = $this->decrypt($chapterInfo['content'], $zwkey1);
                    $chapter_content = $this->processContent($decrypted);
                    $chapter_title = $chapterInfo['title'] ?? ($chapterInfo['novel_data']['title_from_article'] ?? '');
                    $chapter_list[] = [
                        'title' => $chapter_title,
                        'content' => $chapter_content
                    ];
                }
                if (empty($chapter_list)) {
                    $this->sendErrorResponse('所有章节均无内容', $responseData);
                }
                $this->sendSuccessResponse($chapter_list);
                return;
            }
            
        } catch (Exception $e) {
            http_response_code(500);
            $this->sendErrorResponse($e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * 解密内容 - 完全按照原content.php的decrypt函数
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
     * 处理内容 - 完全按照原content.php的processContent函数
     */
    protected function processContent($content)
    {
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
     * 加载JSON配置 - 完全按照原content.php的loadJsonConfig函数
     */
    private function loadJsonConfig($path)
    {
        $content = file_get_contents($path);
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON配置解析失败: ' . json_last_error_msg());
        }
        
        return $data;
    }

    /**
     * 刷新页面 - 改进的版本，适用于API
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

        // API环境下不能直接redirect，改为抛出异常
        throw new Exception('设备密钥需要刷新，已尝试刷新，请重试');
    }

    /**
     * CURL请求 - 兼容BaseEndpoint的参数签名
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
        curl_close($ch);
        return $response;
    }

    /**
     * 发送成功响应 - 完全按照原content.php的sendSuccessResponse函数
     */
    private function sendSuccessResponse($data)
    {
        echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * 发送错误响应 - 完全按照原content.php的sendErrorResponse函数
     */
    private function sendErrorResponse($message, $trace = null)
    {
        http_response_code(500);
        echo json_encode([
            'error' => $message,
            'trace' => $trace
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
