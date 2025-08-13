<?php
/**
 * 书籍信息API端点
 * 处理书籍目录相关的请求
 * 整合了原来的book.php的功能
 */

class BookEndpoint extends BaseEndpoint
{
    public function handle()
    {
        $bookId = $_GET['bookId'] ?? null;
        if (!$bookId) {
            $this->sendError(400, '缺少bookId参数');
        }

        try {
            $this->getBookDirectory($bookId);
        } catch (Exception $e) {
            $this->sendError(500, $e->getMessage());
        }
    }

    /**
     * 获取书籍目录
     */
    private function getBookDirectory($bookId)
    {
        // 生成msToken
        $msToken = $this->generateMsToken();
        
        // 构建查询字符串
        $queryString = "bookId=" . $bookId;
        $userAgent = "Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Mobile Safari/537.36";
        
        // 使用新的ABogusManager生成a_bogus
        $abogusManager = new ABogusManager();
        $aBogus = $abogusManager->generateABogus($queryString, $userAgent);
        
        // 构建请求URL
        $url = "https://fanqienovel.com/api/reader/directory/detail?{$queryString}&msToken={$msToken}&a_bogus={$aBogus}";
        
        // 发送请求
        $response = $this->curlRequest($url, [
            "User-Agent: {$userAgent}",
            "Accept: application/json"
        ]);

        // 直接返回响应
        header('Content-Type: application/json');
        echo $response;
        exit;
    }

    /**
     * 生成msToken
     */
    private function generateMsToken($length = 182)
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
        $result = '';
        $charLength = strlen($chars);
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[mt_rand(0, $charLength - 1)];
        }
        return urlencode($result);
    }
}
