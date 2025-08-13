<?php
/**
 * 目录API端点
 * 处理目录相关请求，对应原来的directory.php功能
 */

class DirectoryEndpoint extends BaseEndpoint
{
    public function handle()
    {
        // 获取fq_id参数（对应原directory.php的参数名）
        $fqId = $_GET['fq_id'] ?? $_GET['bookId'] ?? null;
        
        if (!$fqId) {
            $this->sendError(400, '缺少fq_id参数', '请提供小说ID');
        }

        try {
            $this->getDirectoryItems($fqId);
        } catch (Exception $e) {
            $this->sendError(500, $e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * 获取目录项目列表
     * 对应原directory.php的功能
     */
    private function getDirectoryItems($fqId)
    {
        $deviceKeys = $this->getDeviceKeys();
        $deviceId = $deviceKeys['device_id'];

        // 使用原directory.php相同的API
        $url = "https://reading.snssdk.com/reading/bookapi/directory/all_items/v/?aid=1967&app_name=novelapp&book_id={$fqId}&channel=0&device_id={$deviceId}&device_platform=android&device_type=0&os_version=0&version_code=99999";

        $queryString = parse_url($url, PHP_URL_QUERY);
        $xgData = $this->algorithmManager->generateXGorgon($queryString);
        
        $response = $this->curlRequest($url, [
            'X-Gorgon: '.$xgData['x_gorgon'],
            'X-Khronos: '.$xgData['timestamp'],
            'User-Agent: Mozilla/5.0 (Linux; Android 13)'
        ]);
        
        $responseData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON解析失败: ' . json_last_error_msg());
        }

        // 提取item_id和version信息，保持与原directory.php相同的格式
        $result = [
            'lists' => []
        ];

        if (isset($responseData['data']['item_data_list'])) {
            foreach ($responseData['data']['item_data_list'] as $item) {
                $result['lists'][] = [
                    'title' => $item['title'] ?? '',
                    'item_id' => $item['item_id'] ?? '',
                    'version' => $item['version'] ?? ''
                ];
            }
        }

        $this->sendSuccess($result);
    }
}
