<?php
/**
 * 搜索API端点
 * 处理搜索相关的请求
 * 整合了原来的search.php的功能
 */

class SearchEndpoint extends BaseEndpoint
{
    public function handle()
    {
        // 获取GET参数
        $keyWord = $_GET['key'] ?? null;
        $offset = $_GET['offset'] ?? 0;
        $tabType = $_GET['tab_type'] ?? null;

        if (!$keyWord) {
            $this->sendError(400, '缺少搜索关键词参数key');
        }

        if (!$tabType) {
            $this->sendError(400, '缺少tab_type参数');
        }

        try {
            $this->handleSearchRequest($keyWord, $offset, $tabType);
        } catch (Exception $e) {
            $this->sendError(500, $e->getMessage());
        }
    }

    /**
     * 处理搜索请求
     */
    private function handleSearchRequest($keyWord, $offset, $tabType)
    {
        $deviceKeys = $this->getDeviceKeys();
        $deviceId = $deviceKeys['device_id'];
        
        $encodedKeyword = rawurlencode($keyWord);
        
        $url = "https://reading.snssdk.com/reading/bookapi/search/tab/v/?aid=1967"
             . "&app_name=novelapp&channel=0&device_platform=android"
             . "&device_id={$deviceId}&device_type=Honor10"
             . "&tab_type={$tabType}&query={$encodedKeyword}"
             . "&offset={$offset}&os_version=0&version_code=66.9&update_version_code=58932";
        
        $queryString = parse_url($url, PHP_URL_QUERY);
        $xgData = $this->algorithmManager->generateXGorgon($queryString);
        
        $response = $this->curlRequest($url, [
            'X-Gorgon: '.$xgData['x_gorgon'],
            'X-Khronos: '.$xgData['timestamp'],
            'User-Agent: com.dragon.read'
        ]);
        
        $responseData = json_decode($response, true);
        if (!$responseData || json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('API响应解析失败');
        }

        $this->sendSuccess($responseData);
    }
}
