<?php
/**
 * 项目信息API端点
 * 处理项目信息相关请求，对应原item_info.php功能
 */

class ItemInfoEndpoint extends BaseEndpoint
{
    public function handle()
    {
        // 支持item_ids（原API）和item_id（兼容）参数
        $itemIds = $_GET['item_ids'] ?? $_GET['item_id'] ?? null;
        if (!$itemIds) {
            $this->sendError(400, '缺少item_ids参数', '/api.php?item_ids=7507512821328904729,7507960973773242905');
        }

        try {
            $this->getItemInfo($itemIds);
        } catch (Exception $e) {
            $this->sendError(500, $e->getMessage());
        }
    }

    /**
     * 获取项目信息
     * 使用与原item_info.php相同的API
     */
    private function getItemInfo($itemIds)
    {
        // 清理item_ids格式
        $itemIds = preg_replace('/\s+/', '', $itemIds); // 去除所有空格
        $itemIds = trim($itemIds, ','); // 去除首尾逗号

        // 验证item_ids格式
        if (!preg_match('/^\d+(,\d+)*$/', $itemIds)) {
            $this->sendError(400, 'item_ids参数格式不正确', '逗号分隔的数字ID，如：7507512821328904729,7507960973773242905');
        }

        // 使用与原item_info.php相同的API
        $url = "https://novel.snssdk.com/api/novel/book/directory/detail/v/?aid=1319&item_ids=" . urlencode($itemIds);

        $response = $this->curlRequest($url, [
            'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 16_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.5 Mobile/15E148 Safari/604.1',
            'Accept: application/json',
            'Referer: https://novel.snssdk.com/',
            'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8'
        ]);
        
        $responseData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // JSON解析失败，返回原始响应（与原item_info.php保持一致）
            echo "JSON解析失败，原始响应：\n";
            echo $response;
            exit;
        }

        // 返回与原item_info.php相同的格式
        $this->sendSuccess([
            'http_code' => 200,
            'data' => $responseData
        ]);
    }
}
