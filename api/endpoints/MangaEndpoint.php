<?php
/**
 * 漫画API端点
 * 调用content的full接口，获取原始返回信息，并解析漫画图片
 */

require_once dirname(__DIR__) . '/core/BaseEndpoint.php';
require_once dirname(__DIR__) . '/endpoints/ExactContentEndpoint.php';

// 先定义DomainImageDecryptor类（放在文件顶部或底部，不能嵌套在方法内）
class DomainImageDecryptor {
    private $deleteDelay = 60;
    private $srcDir;
    private $imageFiles = [];
    private $curlHandle;
    private $currentDomain; // 当前域名

    public function __construct() {
        // 获取当前域名（自动适配HTTP/HTTPS）
        $this->currentDomain = $this->getCurrentDomain();
        // 定义src目录（web根目录下src/）
        $this->srcDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/src/';
        if (!is_dir($this->srcDir)) {
            if (!@mkdir($this->srcDir, 0755, true)) {
                throw new \Exception('无法创建图片存储目录: ' . $this->srcDir);
            }
        }
        if (!is_writable($this->srcDir)) {
            throw new \Exception('图片存储目录不可写: ' . $this->srcDir);
        }
        // 初始化CURL
        $this->initCurl();
    }
    private function getCurrentDomain() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return rtrim("{$protocol}://{$host}", '/');
    }
    private function initCurl() {
        $this->curlHandle = curl_init();
        curl_setopt_array($this->curlHandle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0
        ]);
    }
    public function __destruct() {
        if ($this->curlHandle) curl_close($this->curlHandle);
    }
    public function decryptAndReturnJson($jsonStr, $showHtml = false) {
        $data = json_decode($jsonStr, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['picInfos'], $data['encrypt_key'])) {
            return $this->outputJson($showHtml ? '' : []);
        }
        $keyBytes = @hex2bin($data['encrypt_key']);
        if (!$keyBytes) return $this->outputJson($showHtml ? '' : []);
        if (!@is_dir($this->srcDir) || !@is_writable($this->srcDir)) {
            return $this->outputJson($showHtml ? '' : []);
        }
        $imgUrls = [];
        $html = '';
        $picInfos = $data['picInfos'] ?? [];
        foreach ($picInfos as $index => $pic) {
            $encryptedData = $this->downloadWithRetry($pic['picUrl'] ?? '', 2);
            if (!$encryptedData) continue;
            $decryptedData = $this->decryptImage($encryptedData, $keyBytes);
            if (!$decryptedData) continue;
            $format = $this->getOriginalFormat($decryptedData, $pic['picUrl'] ?? '');
            $originalName = $this->getOriginalFileName($pic['picUrl'] ?? '', $format);
            if (!$originalName) $originalName = "img_{$index}.{$format}";
            $filePath = $this->srcDir . $originalName;
            if (@file_put_contents($filePath, $decryptedData) && filesize($filePath) > 0) {
                $this->imageFiles[] = $filePath;
                $fullImageUrl = $this->currentDomain . '/src/' . $originalName;
                $imgUrls[] = $fullImageUrl;
                $html .= '<img src="' . htmlspecialchars($fullImageUrl, ENT_QUOTES) . '" width="' . $pic['width'] . '" height="' . $pic['height'] . '" alt="图片' . $index . '">\n';
            }
        }
        $this->scheduleDeletion();
        return $this->outputJson($showHtml ? $html : $imgUrls, $showHtml);
    }
    private function downloadWithRetry($url, $maxRetries) {
        $retry = 0;
        while ($retry <= $maxRetries) {
            $data = $this->fastDownloadImage($url);
            if ($data !== false && strlen($data) > 100) {
                return $data;
            }
            $retry++;
            usleep(500000);
        }
        return false;
    }
    private function fastDownloadImage($url) {
        if (!$url) return false;
        curl_setopt($this->curlHandle, CURLOPT_URL, $url);
        $response = curl_exec($this->curlHandle);
        return curl_errno($this->curlHandle) === CURLE_OK ? $response : false;
    }
    private function decryptImage($encryptedData, $keyBytes) {
        $len = strlen($encryptedData);
        if ($len < 28) return false;
        $iv = substr($encryptedData, 0, 12);
        $tag = substr($encryptedData, -16);
        $ciphertext = substr($encryptedData, 12, $len - 28);
        $cipher = strlen($keyBytes) == 32 ? "aes-256-gcm" : "aes-128-gcm";
        return openssl_decrypt($ciphertext, $cipher, $keyBytes, OPENSSL_RAW_DATA, $iv, $tag);
    }
    private function getOriginalFormat($data, $url) {
        $path = parse_url($url, PHP_URL_PATH);
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            return strtolower($ext);
        }
        $header = substr($data, 0, 16);
        if (substr($header, 0, 3) === "\xff\xd8\xff") return 'jpg';
        if (substr($header, 0, 4) === "\x89PNG") return 'png';
        if (substr($header, 0, 4) === "GIF8") return 'gif';
        if (substr($header, 0, 4) === "RIFF" && substr($header, 8, 4) === "WEBP") return 'webp';
        return 'png';
    }
    private function getOriginalFileName($url, $format) {
        $path = parse_url($url, PHP_URL_PATH);
        $baseName = pathinfo($path, PATHINFO_FILENAME);
        return $baseName ? "{$baseName}.{$format}" : '';
    }
    private function scheduleDeletion() {
        if (function_exists('exec') && !empty($this->imageFiles)) {
            $files = implode(' ', array_map('escapeshellarg', $this->imageFiles));
            exec("(sleep {$this->deleteDelay}; rm -f {$files}) > /dev/null 2>&1 &");
        }
    }
    private function outputJson($data, $showHtml = false) {
        if ($showHtml) {
            return json_encode(["data" => ["content" => $data]]);
        } else {
            return json_encode(["data" => ["images" => $data]]);
        }
    }
}

class MangaEndpoint extends BaseEndpoint
{
    public function handle()
    {
        try {
            // 获取item_ids参数
            $item_id = $_GET['item_ids'] ?? null;
            if (!$item_id) {
                $this->sendError(400, '缺少item_ids参数');
            }

            // 获取原始full接口内容
            $showHtml = isset($_GET['show_html']) && $_GET['show_html'] == '1';
            $rawContent = $this->getRawFullContent($item_id);

            if (isset($rawContent['data']['content'])) {
                // 先解密content
                $keyData = $this->loadJsonConfig($_SERVER['DOCUMENT_ROOT'] . '/key.json');
                preg_match('/(.*)@(.*)/', $keyData['key'], $matches);
                $zwkey1 = $matches[1];

                $decrypted = $this->decrypt($rawContent['data']['content'], $zwkey1);

                // 判断是否为漫画JSON
                if (strpos($decrypted, 'picInfos') !== false) {
                    $result = $this->parseMangaImages($decrypted, $showHtml);
                    if ($showHtml) {
                        header('Content-Type: text/html; charset=utf-8');
                        echo $result;
                        exit;
                    } else {
                        $this->sendSuccess(['images' => $result]);
                    }
                } else {
                    // 普通章节，走内容处理
                    $processed = $this->processContent($decrypted);
                    if ($showHtml) {
                        header('Content-Type: text/html; charset=utf-8');
                        echo nl2br(htmlspecialchars($processed));
                        exit;
                    } else {
                        $this->sendSuccess(['content' => $processed]);
                    }
                }
            } else {
                // 其他情况原样返回
                $this->sendSuccess($rawContent['data']);
            }
        } catch (\Exception $e) {
            $this->sendError(500, '处理失败: ' . $e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * 完全复制full接口主流程，获取原始加密内容
     */
    private function getRawFullContent($item_id)
    {
        try {
            if (!$item_id) {
                throw new \Exception('缺少item_ids参数');
            }
            $keyData = $this->loadJsonConfig($_SERVER['DOCUMENT_ROOT'] . '/key.json');
            if (!preg_match('/(.*)@(.*)/', $keyData['key'], $matches)) {
                throw new \Exception('无效的zwkey格式，应为"密钥@device_id"');
            }
            [$zwkey1, $zwkey2] = [$matches[1], $matches[2]];
            $api_type = $_GET['api_type'] ?? 'full';
            $custom_url = $_GET['custom_url'] ?? '';
            $item_ids = $item_id;
            if ($custom_url) {
                $url = str_replace([
                    '{$zwkey2}', '{$item_id}'
                ], [
                    $zwkey2, $item_ids
                ], $custom_url);
            } else if ($api_type === 'full') {
                $url = "https://reading.snssdk.com/reading/reader/full/v/?aid=1967&app_name=novelapp&channel=0&device_platform=android&device_id={$zwkey2}&device_type=Honor10&item_id={$item_ids}&os_version=0&version_code=66.9";
            } else {
                $url = "https://api5-normal-sinfonlineb.fqnovel.com/reading/reader/batch_full/v?aid=1967&app_name=novelapp&channel=0&device_platform=android&device_id={$zwkey2}&device_type=Honor10&os_version=0&version_code=66.9&book_id=0&item_ids={$item_ids}&novel_text_type=1&req_type=1";
            }
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
                throw new \Exception('JSON解析失败: ' . json_last_error_msg());
            }
            return $responseData;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * 依赖方法：加载JSON配置
     */
    protected function loadJsonConfig($path)
    {
        $content = file_get_contents($path);
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('JSON配置解析失败: ' . json_last_error_msg());
        }
        return $data;
    }

    /**
     * 依赖方法：CURL请求
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
     * 解析漫画图片内容
     */
    private function parseMangaImages($content, $showHtml = false)
    {
        $jsonStart = strpos($content, '{');
        $jsonEnd = strrpos($content, '}');
        if ($jsonStart === false || $jsonEnd === false) {
            return $showHtml ? '' : [];
        }
        $jsonStr = substr($content, $jsonStart, $jsonEnd - $jsonStart + 1);
        $data = json_decode($jsonStr, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($data['picInfos']) || empty($data['encrypt_key'])) {
            return $showHtml ? '' : [];
        }
        $decryptor = new DomainImageDecryptor();
        $json = $decryptor->decryptAndReturnJson(json_encode($data), $showHtml);
        $result = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $showHtml ? '' : [];
        }
        return $showHtml ? ($result['data']['content'] ?? '') : ($result['data']['images'] ?? []);
    }

    /**
     * 解密内容 - 完全按照原content.php的decrypt函数
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
     * 处理内容 - 完全按照原content.php的processContent函数
     */
    protected function processContent($content)
    {
        $patterns = [
            '/<p class=\\"pictureDesc\\" group-id=\\"\\d+\" idx=\\"\\d+">/',
            '/<\\/body>|<\\/html>|<\\/div>/',
            '/<p class=\\"picture\\" group-id=\\"\\d+\\">/',
            '/<div data-fanqie-type=\\"image\\" source=\\"user\\">/',
            '/<head>.*<\\/h1>/',
            '/<!DOCTYPE.*<html>/',
            '/<\\?xml.*\\?>/',
            '/<p idx=\"\\d+\">/',
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