<?php
/**
 * 视频API端点
 * 处理短剧视频相关的请求
 * 整合了原来的video.php的功能
 */

class VideoEndpoint extends BaseEndpoint
{
    public function handle()
    {
        // 验证必要参数
        if (!isset($_GET['ts'])) {
            $this->sendError(400, '缺少参数: ts');
        }

        if (!isset($_GET['item_id'])) {
            $this->sendError(400, '缺少参数: item_id');
        }

        // 仅处理短剧请求
        if ($_GET['ts'] === "短剧") {
            try {
                $this->handleVideoRequest($_GET['item_id']);
            } catch (Exception $e) {
                $this->sendError(500, $e->getMessage());
            }
        } else {
            $this->sendError(400, '不支持的请求类型', '仅支持短剧类型');
        }
    }

    /**
     * 处理短剧视频请求
     */
    private function handleVideoRequest($videoId)
    {
        $apiUrl = 'https://reading.snssdk.com/novel/player/multi_video_model/v1/?aid=1967';
        
        // 构造请求数据
        $postData = [
            'biz_param' => [
                'device_level' => 3,
                'need_all_video_definition' => false,
                'need_mp4_align' => false,
                'use_os_player' => false,
                'video_platform' => 1024
            ],
            'video_id' => $videoId
        ];

        // 发送API请求
        $response = $this->curlRequest($apiUrl, [
            'Content-Type: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/97.0.4692.99 Safari/537.36'
        ], json_encode($postData), 15);

        // 解析JSON响应
        $jsonData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendError(500, 'JSON解析失败', json_last_error_msg());
        }

        // 检查视频数据是否存在
        if (empty($jsonData['data'][$videoId]['video_model'])) {
            $this->sendError(404, '视频数据不存在', $jsonData);
        }

        // 解析视频模型
        $videoModel = json_decode($jsonData['data'][$videoId]['video_model'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendError(500, '视频模型解析失败', $jsonData['data'][$videoId]['video_model']);
        }

        // 获取视频URL
        if (empty($videoModel['video_list']['video_1']['main_url'])) {
            $this->sendError(404, '视频地址不存在', $videoModel);
        }

        // 解码Base64视频地址
        $videoUrl = base64_decode($videoModel['video_list']['video_1']['main_url']);
        if (!$videoUrl) {
            $this->sendError(500, '视频地址解码失败', $videoModel['video_list']['video_1']['main_url']);
        }

        // 返回成功结果
        $this->sendSuccess([
            'video_id' => $videoId,
            'video_url' => $videoUrl,
            'expire_time' => date('Y-m-d H:i:s', time() + 3600) // 假设地址1小时有效
        ]);
    }
}
