<?php
/**
 * Full API端点
 * 处理full相关请求，完全复制原来的full.php功能
 */

class FullEndpoint extends BaseEndpoint
{
    private $baseUrl = 'https://novelfm-hl.snssdk.com/novelfm/playerapi/full/mget/v1/?aid=3040';

    public function handle()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        
        if ($method === 'GET') {
            $this->handleGetRequest();
        } elseif ($method === 'POST') {
            $this->handlePostRequest();
        } else {
            $this->sendError(405, '不支持的请求方法');
        }
    }

    /**
     * 处理GET请求
     */
    private function handleGetRequest()
    {
        $bookId = $_GET['book_id'] ?? '';
        $itemIds = $_GET['item_ids'] ?? $_GET['item_id'] ?? '';
        
        if (empty($bookId)) {
            $this->sendError(400, '缺少必要参数: book_id');
        }
        
        if (empty($itemIds)) {
            $this->sendError(400, '缺少必要参数: item_ids');
        }
        
        // 解析item_ids参数（支持逗号分隔的字符串或JSON数组）
        if (is_string($itemIds)) {
            if (strpos($itemIds, '[') === 0) {
                // JSON数组格式
                $itemIdsArray = json_decode($itemIds, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->sendError(400, 'item_ids参数格式错误');
                }
            } else {
                // 逗号分隔格式
                $itemIdsArray = array_filter(array_map('trim', explode(',', $itemIds)));
            }
        } else {
            $itemIdsArray = $itemIds;
        }
        
        if (empty($itemIdsArray)) {
            $this->sendError(400, 'item_ids参数不能为空');
        }
        
        $result = $this->getChapters($bookId, $itemIdsArray);
        
        if (isset($result['error'])) {
            $this->sendError(500, $result['error']);
        }
        
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * 处理POST请求
     */
    private function handlePostRequest()
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendError(400, '请求体必须是有效的JSON格式');
        }
        
        $bookId = $data['book_id'] ?? '';
        $itemIds = $data['item_ids'] ?? [];
        
        if (empty($bookId)) {
            $this->sendError(400, '缺少必要参数: book_id');
        }
        
        if (empty($itemIds) || !is_array($itemIds)) {
            $this->sendError(400, '缺少必要参数: item_ids (必须是数组)');
        }
        
        $result = $this->getChapters($bookId, $itemIds);
        
        if (isset($result['error'])) {
            $this->sendError(500, $result['error']);
        }
        
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * 获取章节内容
     */
    private function getChapters($bookId, $itemIds)
    {
        // 验证参数
        if (empty($bookId)) {
            return ['error' => 'book_id参数不能为空'];
        }

        if (empty($itemIds) || !is_array($itemIds)) {
            return ['error' => 'item_ids参数必须是数组且不能为空'];
        }

        // 限制单次请求的章节数量
        if (count($itemIds) > 300) {
            return ['error' => '单次请求章节数量不能超过300个'];
        }

        $requestData = [
            'book_id' => $bookId,
            'item_ids' => $itemIds
        ];

        $result = $this->postHandshake($requestData);
        
        if (isset($result['error'])) {
            return $result;
        }

        $response = $result['data'];
        $cm = $result['cm'];

        $chapters = [];
        if (!empty($response['data']['item_infos'])) {
            foreach ($response['data']['item_infos'] as $item) {
                $decryptedContent = $cm->decrypt($item['key'] ?? '', $item['content'] ?? '');
                
                // 清理和格式化内容
                $formattedContent = $this->processFullContent($decryptedContent);
                
                // 添加配置文本
                if (isset($this->config['zwsm'])) {
                    $formattedContent .= $this->config['zwsm'];
                }
                
                $chapters[] = [
                    'title' => $item['title'] ?? '',
                    'content' => $formattedContent
                ];
            }
        }

        return $chapters;
    }

    /**
     * 发送握手请求
     */
    private function postHandshake($jsonData)
    {
        $cm = new CM();
        $jsonData['key'] = $cm->clientHandshake();

        $response = $this->curlRequest($this->baseUrl, [
            'Content-Type: application/json; charset=utf-8',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36'
        ], json_encode($jsonData), 30);

        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'JSON解析失败: ' . json_last_error_msg()];
        }

        return ['data' => $decodedResponse, 'cm' => $cm];
    }

    /**
     * 处理full内容格式化
     */
    private function processFullContent($content)
    {
        // 1. 先把<p>、<br>等替换为换行
        $content = preg_replace('/<\s*br\s*\/?>/i', "\n", $content);
        $content = preg_replace('/<\s*p[^>]*>/i', "\n", $content);
        $content = preg_replace('/<\s*\/p\s*>/i', "\n", $content);

        // 2. 再去除其它所有HTML标签
        $content = strip_tags($content);

        // 3. 其余正则清理
        $patterns = [
            '/<p class=\\"pictureDesc\\" group-id=\\"\\d+\\" idx=\\"\\d+">/',
            '/<\\/body>|<\\/html>|<\\/div>/',
            '/<p class=\\"picture\\" group-id=\\"\\d+">/',
            '/<div data-fanqie-type=\\"image\\" source=\\"user\\">/',
            '/<head>.*<\\/h1>/',
            '/<!DOCTYPE.*<html>/',
            '/<\\?xml.*\\?>/',
            '/<p idx=\\"\\d+\\">/',
            '/<header>|<\\/header>/',
            '/<article>|<\\/article>/',
            '/<footer>|<\\/footer>/',
            '/<tt_keyword.*keyword_ad>/',
            '/<p>/'
        ];
        $content = preg_replace($patterns, '', $content);
        $content = preg_replace('/&amp;x/', "&x", $content);

        // 4. 多余空行合并
        $content = preg_replace('/\n{2,}/', "\n", $content);
        $content = trim($content);
        return $content;
    }
}

/**
 * CM加密类 - 复制自原full.php
 */
class CM {
    private $prime;
    private $base;
    private $aesKey;
    private $privateKey;
    private $publicKey;
    private $iv;

    public function __construct() {
        $this->prime = gmp_init("ffffffffffffffffc90fdaa22168c234c4c6628b80dc1cd129024e088a67cc74020bbea63b139b22514a08798e3404ddef9519b3cd3a431b302b0a6df25f14374fe1356d6d51c245e485b576625e7ec6f44c42e9a637ed6b0bff5cb6f406b7edee386bfb5a899fa5ae9f24117c4b1fe649286651ece45b3dc2007cb8a163bf0598da48361c55d39a69163fa8fd24cf5f83655d23dca3ad961c62f356208552bb9ed529077096966d670c354e4abc9804f1746c08ca237327ffffffffffffffff", 16);
        $this->base = gmp_init("2", 16);
        $this->aesKey = base64_decode("rCXGfd2POMGzeiNIgo4iLg==");
        $this->iv = openssl_random_pseudo_bytes(16);

        $this->privateKey = gmp_mod(gmp_import(openssl_random_pseudo_bytes(32)), gmp_sub($this->prime, 1));
        $this->publicKey = gmp_powm($this->base, $this->privateKey, $this->prime);
    }

    private function gmpToBytes($gmpNum) {
        $hex = gmp_strval($gmpNum, 16);
        if (strlen($hex) % 2 !== 0) {
            $hex = '0' . $hex;
        }
        return hex2bin($hex);
    }

    private function pkcs7Pad($data, $blockSize) {
        $padLength = $blockSize - (strlen($data) % $blockSize);
        return $data . str_repeat(chr($padLength), $padLength);
    }

    private function pkcs7Unpad($data) {
        if (empty($data)) return $data;
        $padLength = ord($data[strlen($data) - 1]);
        if ($padLength > strlen($data)) {
            return $data;
        }
        return substr($data, 0, -$padLength);
    }

    public function clientHandshake() {
        $yBytes = $this->gmpToBytes($this->publicKey);
        $padded = $this->pkcs7Pad($yBytes, 16);
        $encrypted = openssl_encrypt(
            $padded, 
            'AES-128-CBC', 
            $this->aesKey, 
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, 
            $this->iv
        );
        return base64_encode($this->iv . $encrypted);
    }

    public function decrypt($serverKey, $content) {
        if (empty($serverKey) || empty($content)) {
            return "密钥或内容为空";
        }

        $decodedContent = base64_decode($content);
        if ($decodedContent === false) {
            return "内容解码失败";
        }

        $iv = substr($decodedContent, 0, 16);
        $ciphertext = substr($decodedContent, 16);

        $serverKeyLong = gmp_init(bin2hex(base64_decode($serverKey)), 16);
        $sharedSecret = gmp_powm($serverKeyLong, $this->privateKey, $this->prime);

        $aesKey = substr($this->gmpToBytes($sharedSecret), 0, 32);

        $decrypted = openssl_decrypt(
            $ciphertext,
            'AES-256-CBC',
            $aesKey,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            $iv
        );

        if ($decrypted === false) {
            return "解密失败: " . openssl_error_string();
        }

        return $this->pkcs7Unpad($decrypted);
    }
}
