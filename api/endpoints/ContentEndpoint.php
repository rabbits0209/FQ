<?php
/**
 * 内容API端点
 * 处理章节内容获取相关的请求
 * 整合了原来的index.php和content.php的功能
 */

class ContentEndpoint extends BaseEndpoint
{
    public function handle()
    {
        $api = $_GET['api'] ?? $this->detectApiType();
        
        if ($api === 'raw_full') {
            $this->handleRawFullApi();
            return;
        }

        if ($api === 'chapter') {
            // 处理原index.php的简单API（番茄开放SDK）
            $this->handleChapterApi();
            return;
        }

        // 以下是原content.php的复杂API逻辑，完全复制原文件的参数处理
        $item_id = $_GET['item_ids'] ?? null;  // 注意：原文件用item_ids参数名，但变量名是item_id
        $ts = $_GET['ts'] ?? null;
        $book_id = $_GET['book_id'] ?? null;
        $comment = $_GET['comment'] ?? null;

        try {
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

            // 检查item_id参数
            if (!$item_id) {
                $this->sendError(400, '缺少item_ids参数');
                return;
            }

            // 处理主请求（章节内容）
            $this->handleMainRequest($item_id);

        } catch (Exception $e) {
            $this->sendError(500, $e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * 检测API类型
     */
    private function detectApiType()
    {
        $path = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($path, '/index.php') !== false) {
            return 'chapter';
        }
        return 'content';
    }

    /**
     * 处理章节API（原index.php的逻辑）
     * 使用番茄开放SDK的简单API
     */
    private function handleChapterApi()
    {
        $itemId = $_GET['item_id'] ?? '';
        
        if (empty($itemId)) {
            $this->sendError(400, '请提供 item_id 参数');
        }

        try {
            $url = 'https://sdkapi.fanqieopen.com/open_sdk/reader/content/v1?sdk_type=4&novelsdk_aid=638505';
            $data = [
                'item_id' => $itemId,
                'need_book_info' => 1,
                'show_picture' => 1,
                'sdk_type' => 1
            ];

            $headers = [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36',
                'Content-Type: application/json'
            ];

            $response = $this->curlPostRequest($url, json_encode($data), $headers);
            $result = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('JSON解析失败: ' . json_last_error_msg());
            }

            // 处理内容，去除HTML标签并添加配置文本
            $content = '';
            if (isset($result['data']['content'])) {
                // 先将HTML中的段落标签转换为换行符
                $contentWithNewlines = str_replace(['</p>', '</div>', '</br>', '<br>', '<br/>'], "\n", $result['data']['content']);
                // 去除HTML标签，只保留纯文本
                $cleanContent = strip_tags($contentWithNewlines);
                // 将连续的空白字符替换为单个空格，但保留换行符
                $cleanContent = preg_replace('/[ \t]+/', ' ', $cleanContent);
                // 去除开头和结尾的空白字符
                $cleanContent = trim($cleanContent);
                $content = $cleanContent;
            }
            
            // 添加配置文本
            $content .= "\n" . $this->config['zwsm'];
            
            // 返回简化格式（与原index.php一致）
            $this->sendSuccess(['content' => $content]);

        } catch (Exception $e) {
            $this->sendError(500, '获取内容失败: ' . $e->getMessage());
        }
    }

    /**
     * 处理音频请求
     */
    private function handleAudioRequest($itemId)
    {
        if (!$itemId) {
            $this->sendError(400, '缺少item_id参数');
        }

        $url = 'https://reading.snssdk.com/reading/reader/audio/playinfo/?tone_id=1&item_ids='.$itemId.'&pv_player=-1&aid=1967';
        
        $response = $this->curlRequest($url, [
            'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1'
        ]);
        
        $responseData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendError(500, 'JSON解析失败');
        }
        
        $mainUrls = array_column($responseData['data'] ?? [], 'main_url');
        $content = $mainUrls[0] ?? '未找到main_url';
        
        $this->sendSuccess(['content' => $content]);
    }

    /**
     * 处理detail请求
     */
    private function handleDetailRequest($bookId)
    {
        if (!$bookId) {
            $this->sendError(400, '缺少book_id参数');
        }

        $deviceKeys = $this->getDeviceKeys();
        $deviceId = $deviceKeys['device_id'];

        $url = "https://reading.snssdk.com/reading/bookapi/detail/v/?aid=1967&app_name=novelapp&book_id={$bookId}&channel=0&device_id={$deviceId}&device_platform=android&device_type=Honor10&os_version=0&version_code=66.9&version_name=5.8.9.32";

        $queryString = parse_url($url, PHP_URL_QUERY);
        $xgData = $this->algorithmManager->generateXGorgon($queryString);
        
        $response = $this->curlRequest($url, [
            'X-Gorgon: '.$xgData['x_gorgon'],
            'X-Khronos: '.$xgData['timestamp'],
            'User-Agent: com.dragon.read'
        ]);
        
        $responseData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendError(500, 'JSON解析失败: ' . json_last_error_msg());
        }

        $this->sendSuccess($responseData);
    }

    /**
     * 处理comment请求
     */
    private function handleCommentRequest($bookId)
    {
        if (!$bookId) {
            $this->sendError(400, '缺少book_id参数');
        }

        $deviceKeys = $this->getDeviceKeys();
        $deviceId = $deviceKeys['device_id'];

        // 获取count参数，默认1
        $count = $_GET['count'] ?? 1;
        $offset = $_GET['offset'] ?? 0;

        $url = "https://api3-normal-sinfonlineb.fqnovel.com/reading/ugc/novel_comment/book/v/?app_name=novelapp&channel=0&book_id={$bookId}&device_type=Honor10&aid=1967&version_name=5.1.5.32&count={$count}&os_version=9.3.5&device_platform=android&version_code=515&device_id={$deviceId}&offset={$offset}";

        $queryString = parse_url($url, PHP_URL_QUERY);
        $xgData = $this->algorithmManager->generateXGorgon($queryString);
        
        $response = $this->curlRequest($url, [
            'X-Gorgon: '.$xgData['x_gorgon'],
            'X-Khronos: '.$xgData['timestamp'],
            'User-Agent: com.dragon.read'
        ]);
        
        $responseData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendError(500, 'JSON解析失败: ' . json_last_error_msg());
        }

        $this->sendSuccess($responseData);
    }

    /**
     * 处理主请求（章节内容）
     * 完全复制原content.php的handleMainRequest函数逻辑
     */
    private function handleMainRequest($item_id)
    {
        $deviceKeys = $this->getDeviceKeys();
        $zwkey1 = $deviceKeys['secret_key'];  // 密钥
        $zwkey2 = $deviceKeys['device_id'];   // 设备ID

        $api_type = $_GET['api_type'] ?? 'full'; // 默认full接口
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
            // 默认batch_full/v接口，支持多章（包括没有api_type参数的情况）
            $url = "https://api5-normal-sinfonlineb.fqnovel.com/reading/reader/batch_full/v?aid=1967&app_name=novelapp&channel=0&device_platform=android&device_id={$zwkey2}&device_type=Honor10&os_version=0&version_code=66.9&book_id=0&item_ids={$item_ids}&novel_text_type=1&req_type=1";
        }

        $query_string = parse_url($url, PHP_URL_QUERY);
        $xg_data = $this->algorithmManager->generateXGorgon($query_string);
        
        $response = $this->curlRequest($url, [
            'X-Gorgon: '.$xg_data['x_gorgon'],
            'X-Khronos: '.$xg_data['timestamp'],
            'User-Agent: com.dragon.read'
        ]);
        
        $responseData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON解析失败: ' . json_last_error_msg());
        }
        
        // full/v接口返回结构不同，兼容处理
        if ($api_type === 'full' || strpos($url, '/full/v/') !== false) {
            if (empty($responseData['data']['content'])) {
                throw new Exception('响应中缺少data.content字段');
            }
            $decrypted_content = $this->decrypt($responseData['data']['content'], $zwkey1);
            $processed_content = $this->processContent($decrypted_content);
            $processed_content .= $this->config['zwsm'];
            $this->sendSuccess(['content' => $processed_content]);
        } else {
            // batch_full/v接口，支持多章
            if (empty($responseData['data']) || !is_array($responseData['data'])) {
                $this->sendError(500, '响应中缺少data字段或格式不正确', $responseData);
            }
            $chapter_list = [];
            foreach ($responseData['data'] as $chapterInfo) {
                if (empty($chapterInfo['content'])) continue;
                $decrypted = $this->decrypt($chapterInfo['content'], $zwkey1);
                $chapterInfo['content'] = $this->processContent($decrypted);
                $chapter_list[] = $chapterInfo;
            }
            if (empty($chapter_list)) {
                $this->sendError(500, '所有章节均无内容', $responseData);
            }
            $this->sendSuccess(['chapters' => $chapter_list]);
        }
    }

    /**
     * 新增：处理原始full API并解密图片内容
     * 访问方式：api.php?api=raw_full&item_id=xxx
     */
    public function handleRawFullApi()
    {
        $item_id = $_GET['item_id'] ?? null;
        if (!$item_id) {
            $this->sendError(400, '缺少item_id参数');
        }
        $deviceKeys = $this->getDeviceKeys();
        $zwkey1 = $deviceKeys['secret_key'];  // 密钥
        $zwkey2 = $deviceKeys['device_id'];   // 设备ID
        $url = "https://reading.snssdk.com/reading/reader/full/v/?aid=1967&app_name=novelapp&channel=0&device_platform=android&device_id={$zwkey2}&device_type=Honor10&item_id={$item_id}&os_version=0&version_code=66.9";
        $query_string = parse_url($url, PHP_URL_QUERY);
        $xg_data = $this->algorithmManager->generateXGorgon($query_string);
        $response = $this->curlRequest($url, [
            'X-Gorgon: '.$xg_data['x_gorgon'],
            'X-Khronos: '.$xg_data['timestamp'],
            'User-Agent: com.dragon.read'
        ]);
        $responseData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendError(500, 'JSON解析失败: ' . json_last_error_msg());
        }
        if (empty($responseData['data']['content'])) {
            $this->sendError(500, '响应中缺少data.content字段');
        }
        $output = [
            'data' => [
                'content' => $this->decrypt($responseData['data']['content'], $zwkey1)
            ]
        ];
        // 以下为漫画new.txt的图片处理逻辑
        if (stripos($output['data']['content'], 'picInfos')!== false) {
            // 1. 从内容中提取所有MD5值（格式为"md5":"xxx"）
            preg_match_all('/"md5":"([a-f0-9]+)"/', $output['data']['content'], $matches);
            $md5_values = $matches[1];
            // 2. 获取第一个MD5值用于路径验证
            $firstMd5 = $md5_values[0];
            // 3. 定义基础域名和初始路径
            $baseDomain = "https://p6-novel.byteimg.com/origin/";
            $currentPath = "novel-images/"; // 优先尝试images路径
            $testUrl = $baseDomain. $currentPath. $firstMd5; // 拼接测试URL
            // 4. 使用curl请求测试URL并获取响应
            $curl = curl_init($testUrl);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); // 响应内容返回而非输出
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // 测试用，生产环境需开启SSL验证
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 8); // 8秒超时
            $response = curl_exec($curl);
            curl_close($curl);
            // 5. 验证响应是否为JSON格式（无效路径通常返回JSON错误信息）
            $isJsonResponse = json_decode($response)!== null;
            if ($isJsonResponse) {
                $currentPath = "novel-pic/"; // 切换到pic路径
            }
            // 6. 确定有效路径前缀
            $validUrlPrefix = $baseDomain. $currentPath;
            // 7. 生成所有图片标签
            for ($i = 0; $i < count($md5_values); $i++) {
                $md5_values[$i] = '<img src="'. $validUrlPrefix. $md5_values[$i]. '" >';
            }
            // 8. 构建输出结果
            $output = array(
                'data' => array(
                    'content' => implode('', $md5_values)
                )
            );
        }
        $this->sendSuccess($output['data']);
    }
}
