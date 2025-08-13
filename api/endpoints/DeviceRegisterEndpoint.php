<?php
/**
 * 设备注册API端点
 * 处理设备注册相关的请求
 * 整合了原来的mygx.php的功能
 */

class DeviceRegisterEndpoint extends BaseEndpoint
{
    public function handle()
    {
        $action = $_GET['action'] ?? 'register';
        if (!in_array($action, ['register', 'status', 'refresh'])) {
            $authFile = $_SERVER['DOCUMENT_ROOT'] . '/auth/auth.json';
            if (!file_exists($authFile)) {
                $this->sendError(401, '授权文件不存在');
            }
            $authData = json_decode(file_get_contents($authFile), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->sendError(401, '授权文件解析失败');
            }
            $validator = new \AuthValidator();
            $authResult = $validator->validateAuthAll($authData);
            if (!$authResult['valid']) {
                $this->sendError(401, '授权验证失败', $authResult['message']);
            }
        }
        
        try {
            switch ($action) {
                case 'register':
                    $this->registerDevice();
                    break;
                case 'status':
                    $this->getDeviceStatus();
                    break;
                case 'refresh':
                    $this->refreshDeviceKey();
                    break;
                default:
                    $this->sendError(400, "不支持的操作: {$action}");
            }
        } catch (Exception $e) {
            $this->sendError(500, $e->getMessage());
        }
    }

    /**
     * 注册新设备
     */
    private function registerDevice()
    {
        try {
            $header = $this->androidDeviceRegisterAndActivate();
            if ($header) {
                // 解析device_id
                $parts = explode('&', $header);
                $deviceId = null;
                foreach ($parts as $part) {
                    if (strpos($part, 'device_id=') === 0) {
                        $deviceId = explode('=', $part)[1];
                    }
                }
                $this->sendSuccess([
                    'device_id' => $deviceId,
                    'message' => '设备注册成功',
                    'status' => 'active'
                ]);
            } else {
                $this->sendError(500, '设备注册失败', '注册过程未成功完成');
            }
        } catch (Exception $e) {
            $this->sendError(500, '设备注册失败', $e->getMessage());
        }
    }

    /**
     * 获取设备状态
     */
    private function getDeviceStatus()
    {
        try {
            $deviceKeys = $this->getDeviceKeys();
            $this->sendSuccess([
                'device_id' => $deviceKeys['device_id'],
                'status' => 'active',
                'algorithm' => $this->algorithmManager->getCurrentAlgorithm()
            ]);
        } catch (Exception $e) {
            $this->sendError(404, '设备未注册', $e->getMessage());
        }
    }

    /**
     * 刷新设备密钥
     */
    private function refreshDeviceKey()
    {
        try {
            $oldKeys = $this->getDeviceKeys();
            $newSecretKey = $this->generateSecretKey();
            
            $keyData = [
                'key' => $newSecretKey . '@' . $oldKeys['device_id'],
                'update_time' => date('Y-m-d H:i:s'),
                'status' => 'active'
            ];
            
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/key.json', json_encode($keyData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            
            $this->sendSuccess([
                'device_id' => $oldKeys['device_id'],
                'message' => '密钥刷新成功',
                'status' => 'active'
            ]);
        } catch (Exception $e) {
            $this->sendError(404, '设备未注册', $e->getMessage());
        }
    }

    /**
     * 安卓设备注册和激活主流程（迁移自mygx.php）
     * @return string|false header字符串 或 false
     */
    private function androidDeviceRegisterAndActivate()
    {
        // 生成随机udid和model
        $char = '0123456789ABCDEF';
        $udid = '';
        for ($i = 0; $i < 16; $i++) {
            $udid .= $char[rand(0, strlen($char) - 1)];
        }
        $udid = strtolower($udid);
        $char .= 'GHIJKLMNOPQRSTUVWXYZ';
        $model = '';
        for ($i = 0; $i < 8; $i++) {
            if ($i == 3) {
                $model .= '-';
            }
            $model .= $char[rand(0, strlen($char) - 1)];
        }
        $url = "https://i.snssdk.com/service/2/device_register/";
        $data = json_encode([
            "header" => [
                "package" => "com.dragon.read",
                "openudid" => $udid,
                "device_model" => $model
            ]
        ]);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        // 可选：代理配置（如有需要可补充）
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($statusCode === 200) {
            $responseData = json_decode($response, true);
            $header = "device_id={$responseData['device_id']}&device_type=$model&iid={$responseData['install_id']}&version_name=5.8.9.32";
            // 激活会员
            $this->androidTryActivatePremium($header);
            // 获取密钥并写入key.json
            $this->androidRegisterKeyAndSave($responseData['device_id'], $responseData['install_id']);
            return $header;
        } else {
            return false;
        }
    }

    /**
     * 安卓激活会员（迁移自mygx.php）
     */
    private function androidTryActivatePremium($header)
    {
        $parts = explode('&', $header);
        $deviceId = null;
        $iid = null;
        foreach ($parts as $part) {
            if (strpos($part, 'device_id=') === 0) {
                $deviceId = explode('=', $part)[1];
            } elseif (strpos($part, 'iid=') === 0) {
                $iid = explode('=', $part)[1];
            }
        }
        if (!$deviceId || !$iid) return false;
        $params = [
            'aid=1967',
            'app_name=novelapp',
            'channel=0',
            'device_id=' . $deviceId,
            'device_platform=android',
            'iid=' . $iid,
            'os_version=0',
            'version_code=58932',
            'manifest_version_code=58932'
        ];
        sort($params);
        $paramString = implode('&', $params);
        $url = 'https://api5-normal-sinfonlinea.fqnovel.com/reading/user/privilege/add/v/?' . $paramString;
        $body = [
            "add_count_daily" => 0,
            "amount" => 2592000,
            "privilege_id" => 7210376203117531962,
            "from" => 8,
            "unique_key" => (string)(round(microtime(true) * 1000))
        ];
        $bodyJson = json_encode($body);
        $headers = [
            'content-type: application/json',
            'User-Agent: com.dragon.read/66732 (Linux; U; Android 10; zh_CN; Pixel 4 XL; Build/QD1A.190821.007;tt-ok/3.12.13.4-tiktok)'
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyJson);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * 安卓获取密钥并写入key.json（迁移自mygx.php）
     */
    private function androidRegisterKeyAndSave($deviceId, $installId)
    {
        $keyBase64 = "rCXGfd2POMGzeiNIgo4iLg==";
        $key = base64_decode($keyBase64);
        if (strlen($key) !== 16) return;
        $uuid = $this->androidRandomUUID();
        $iv = substr($uuid, 0, 16);
        $hexData = $this->androidReverseHex($deviceId);
        $data = hex2bin($hexData);
        $encryptedData = openssl_encrypt($data, 'aes-128-cbc', $key, OPENSSL_RAW_DATA, $iv);
        $content = base64_encode($iv . $encryptedData);
        $headers = [
            'User-Agent: com.dragon.read/66732 (Linux; U; Android 10; zh_CN; Pixel 4 XL; Build/QD1A.190821.007;tt-ok/3.12.13.4-tiktok)',
            'Content-Type: application/x-www-form-urlencoded',
        ];
        $params = http_build_query([
            'iid' => $installId,
            'device_id' => $deviceId,
            'aid' => 1967
        ]);
        $url = 'https://reading.snssdk.com/reading/crypt/registerkey?' . $params;
        $postData = json_encode([
            'content' => $content,
            'keyver' => 1
        ]);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $responseData = json_decode($response, true);
        if (isset($responseData['data']['key'])) {
            $encryptedKey = $responseData['data']['key'];
            $decryptedKey = $this->androidDecryptKey($encryptedKey);
            if ($decryptedKey !== false) {
                $hexKey = strtoupper(bin2hex($decryptedKey));
                $decryptResult = $hexKey . '@' . $deviceId;
                $jsonFile = $_SERVER['DOCUMENT_ROOT'] . '/key.json';
                $jsonData = [
                    'key' => $decryptResult,
                    'update_time' => date('Y-m-d H:i:s')
                ];
                file_put_contents($jsonFile, json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }
    }

    private function androidDecryptKey($encryptedKey)
    {
        $masterKey = 'ac25c67ddd8f38c1b37a2348828e222e';
        $key = hex2bin($masterKey);
        if (!$key) return false;
        $data = base64_decode($encryptedKey);
        if (!$data || strlen($data) < 16) return false;
        $iv = substr($data, 0, 16);
        $ciphertext = substr($data, 16);
        $plaintext = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($plaintext === false) {
            $plaintext = openssl_decrypt($ciphertext, 'AES-128-CBC', substr($key, 0, 16), OPENSSL_RAW_DATA, $iv);
            if ($plaintext === false) return false;
        }
        $padding = ord($plaintext[strlen($plaintext) - 1]);
        if ($padding > 0 && $padding <= 16) {
            $result = substr($plaintext, 0, -$padding);
        } else {
            $result = $plaintext;
        }
        return $result;
    }

    private function androidRandomUUID()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private function androidReverseHex($num)
    {
        $hex = str_pad(dechex($num), 32, '0', STR_PAD_LEFT);
        $res = '';
        for ($i = strlen($hex); $i > 0; $i -= 2) {
            $res .= substr($hex, $i - 2, 2);
        }
        return $res;
    }

    /**
     * 生成设备ID
     */
    private function generateDeviceId()
    {
        return str_pad(mt_rand(1000000000000000, 9999999999999999), 16, '0', STR_PAD_LEFT);
    }

    /**
     * 生成密钥
     */
    private function generateSecretKey()
    {
        return strtoupper(bin2hex(random_bytes(16)));
    }
}
