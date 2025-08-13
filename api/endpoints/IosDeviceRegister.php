<?php

// ios_device_register.php

// 禁用SSL验证警告
error_reporting(E_ALL ^ E_WARNING);

// 常量定义
define('IOS_VERSIONS', [
    "16.6.1", "17.0", "17.1", "17.2", "17.3", "17.4"
]);

// 设备型号信息
define('DEVICE_INFO', [
    [
        "model" => "iPhone14,4",
        "type" => "iPhone 13 mini",
        "resolution" => "1080*2340",
        "hardware" => "D16AP"
    ],
    [
        "model" => "iPhone14,5",
        "type" => "iPhone 13",
        "resolution" => "1170*2532",
        "hardware" => "D17AP"
    ],
    [
        "model" => "iPhone15,2",
        "type" => "iPhone 14 Pro",
        "resolution" => "1179*2556",
        "hardware" => "D73AP"
    ],
    [
        "model" => "iPhone15,3",
        "type" => "iPhone 14 Pro Max",
        "resolution" => "1290*2796",
        "hardware" => "D74AP"
    ],
    [
        "model" => "iPhone16,1",
        "type" => "iPhone 15",
        "resolution" => "1179*2556",
        "hardware" => "D27AP"
    ],
    [
        "model" => "iPhone16,2",
        "type" => "iPhone 15 Pro",
        "resolution" => "1179*2556",
        "hardware" => "D83AP"
    ]
]);

// -------------------- 工具函数 --------------------
function generate_uuid() {
    return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', 
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function current_millis() {
    return round(microtime(true) * 1000);
}

function current_time_with_ms() {
    return sprintf('%.9f', microtime(true));
}

// -------------------- 各部分数据生成函数 --------------------
function generate_device_info() {
    $device_info = DEVICE_INFO[array_rand(DEVICE_INFO)];
    $os_version = IOS_VERSIONS[array_rand(IOS_VERSIONS)];
    
    $device_id = '';
    for ($i = 0; $i < 16; $i++) {
        $device_id .= mt_rand(0, 9);
    }
    
    $install_id = '';
    for ($i = 0; $i < 16; $i++) {
        $install_id .= mt_rand(0, 9);
    }
    
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $device_token = '';
    for ($i = 0; $i < 64; $i++) {
        $device_token .= $chars[mt_rand(0, strlen($chars) - 1)];
    }
    
    return [
        "os" => "iOS",
        "os_version" => $os_version,
        "device_model" => $device_info["model"],
        "device_type" => $device_info["type"],
        "resolution" => $device_info["resolution"],
        "hardware_model" => $device_info["hardware"],
        "device_id" => $device_id,
        "old_did" => $device_id,
        "install_id" => $install_id,
        "idfa" => "00000000-0000-0000-0000-000000000000",
        "idfv" => strtoupper(generate_uuid()),
        "cdid" => strtoupper(generate_uuid()),
        "vendor_id" => strtoupper(generate_uuid()),
        "device_token" => $device_token,
    ];
}

function generate_disk_info() {
    $disk_sizes = [64, 128, 256, 512];
    $mem_sizes = [4, 6, 8];
    
    $disk_total = $disk_sizes[array_rand($disk_sizes)] * 1024 * 1024 * 1024;
    $mem_total = $mem_sizes[array_rand($mem_sizes)] * 1024 * 1024 * 1024;
    
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $disk_mount_id = '';
    for ($i = 0; $i < 64; $i++) {
        $disk_mount_id .= $chars[mt_rand(0, strlen($chars) - 1)];
    }
    $disk_mount_id .= '@/dev/disk1s1';
    
    return [
        "disk_total" => $disk_total,
        "mem_total" => $mem_total,
        "disk_mount_id" => $disk_mount_id,
    ];
}

function generate_time_info() {
    $current = microtime(true);
    
    $device_init_time = $current - mt_rand(30 * 24 * 3600, 365 * 24 * 3600);
    $boot_time = $current - mt_rand(1 * 3600, 7 * 24 * 3600);
    $mb_time = $current - mt_rand(0, 3600);
    
    return [
        "boot_time" => sprintf('%.6f', $boot_time),
        "device_init_time" => sprintf('%.9f', $device_init_time),
        "mb_time" => sprintf('%.6f', $mb_time),
    ];
}

function generate_ipv6_list() {
    $ipv6_list = [];
    for ($i = 0; $i < 4; $i++) {
        $parts = [];
        for ($j = 0; $j < 4; $j++) {
            $parts[] = dechex(mt_rand(0, 0xffff));
        }
        $ipv6 = "fe80::" . implode(':', $parts);
        $ipv6_list[] = ["type" => "client_tun", "value" => $ipv6];
    }
    
    $ipv6_list[] = [
        "type" => "client_tun", 
        "value" => "10.0." . mt_rand(0, 255) . "." . mt_rand(1, 254)
    ];
    
    return $ipv6_list;
}

function generate_custom_info() {
    $app_version = "649";
    $ipv6_addresses = [];
    
    for ($i = 0; $i < 6; $i++) {
        $parts = [];
        $parts[] = "240" . mt_rand(8, 9);
        for ($j = 0; $j < 7; $j++) {
            $parts[] = dechex(mt_rand(0, 0xffff));
        }
        $ipv6_addresses[] = implode(':', $parts);
    }
    
    $num_addresses = mt_rand(3, 6);
    $selected_addresses = array_slice($ipv6_addresses, 0, $num_addresses);
    
    $os_index = array_rand(IOS_VERSIONS);
    
    return [
        "app_version" => $app_version,
        "client_ipv4" => "",
        "web_ua" => "Mozilla/5.0 (iPhone; CPU iPhone OS " . 
                    str_replace('.', '_', IOS_VERSIONS[$os_index]) . 
                    " like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148",
        "app_real_version" => "6.4.9.33",
        "client_ipv6" => implode(',', $selected_addresses),
    ];
}

function generate_full_request_body() {
    $access_options = ["WIFI", "5G", "4G"];
    $hardware_models = ["D16AP", "D17AP", "D21AP", "D22AP"];
    $resolutions = ["1179*2556", "1125*2436", "1284*2778"];
    
    $raw_data = [
        "magic_tag" => "ss_app_log",
        "header" => [
            "region" => "CN",
            "access" => $access_options[array_rand($access_options)],
            "ipv6_list" => generate_ipv6_list(),
            "carrier" => "--",
            "sdk_version" => mt_rand(1100, 1200),
            "hardware_model" => $hardware_models[array_rand($hardware_models)],
            "user_agent" => "EggFlower 6.4.9 rv:6.4.9.33 (iPhone; iOS " . 
                            IOS_VERSIONS[array_rand(IOS_VERSIONS)] . 
                            "; zh_CN) Cronet",
            "device_platform" => "iphone",
            "tz_name" => "Asia/Shanghai",
            "cpu_num" => [4, 6, 8][array_rand([4, 6, 8])],
            "tz_offset" => 28800,
            "local_tz_name" => "Asia/Shanghai",
            "carrier_region" => "--",
            "is_upgrade_user" => false,
            "mcc_mnc" => "6553565535",
            "aid" => "507427",
            "package" => "com.eggflower.read",
            "is_jailbroken" => false,
            "language" => "zh",
            "locale_language" => "zh-Hans-CN",
            "app_version" => "6.4.9",
            "resolution" => $resolutions[array_rand($resolutions)],
            "timezone" => 8,
            "appName" => "novelapp_variant_v2",
            "display_name" => "蛋花小说",
            "phone_name" => bin2hex(random_bytes(16)),
            "channel" => "App Store",
        ]
    ];
    
    $device_info = generate_device_info();
    $disk_info = generate_disk_info();
    $time_info = generate_time_info();
    $custom_info = generate_custom_info();
    
    $raw_data["header"] = array_merge(
        $raw_data["header"], 
        $device_info, 
        $disk_info, 
        $time_info
    );
    $raw_data["header"]["custom"] = $custom_info;
    
    return $raw_data;
}

// -------------------- ttEncrypt.php 功能 --------------------
function get_hash_key() {
    $key1 = [
        0x1F, 0xDD, 0xA8, 0x33, 0x88, 0x07, 0xC7, 0x31, 0xB1, 0x12, 0x10, 0x59, 0x27, 0x80, 0xEC, 0x5F,
        0x60, 0x51, 0x7F, 0xA9, 0x19, 0xB5, 0x4A, 0x0D, 0x2D, 0xE5, 0x7A, 0x9F, 0x93, 0xC9, 0x9C, 0xEF,
        0xA0, 0xE0, 0x3B, 0x4D, 0xAE, 0x2A, 0xF5, 0xB0, 0xC8, 0xEB, 0xBB, 0x3C, 0x83, 0x53, 0x99, 0x61,
        0x17, 0x2B, 0x04, 0x7E, 0xBA, 0x77, 0xD6, 0x26, 0xE1, 0x69, 0x14, 0x63, 0x55, 0x21, 0x0C, 0x7D
    ];
  
    $key2 = [
        0x52, 0x09, 0x6A, 0xD5, 0x30, 0x36, 0xA5, 0x38, 0xBF, 0x40, 0xA3, 0x9E, 0x81, 0xF3, 0xD7, 0xFB,
        0x7C, 0xE3, 0x39, 0x82, 0x9B, 0x2F, 0xFF, 0x87, 0x34, 0x8E, 0x43, 0x44, 0xC4, 0xDE, 0xE9, 0xCB,
        0x54, 0x7B, 0x94, 0x32, 0xA6, 0xC2, 0x23, 0x3D, 0xEE, 0x4C, 0x95, 0x0B, 0x42, 0xFA, 0xC3, 0x4E,
        0x08, 0x2E, 0xA1, 0x66, 0x28, 0xD9, 0x24, 0xB2, 0x76, 0x5B, 0xA2, 0x49, 0x6D, 0x8B, 0xD1, 0x25
    ];
  
    $hash_key = '';
    for ($i = 0; $i < 64; $i++) {
        $hash_key .= chr($key1[$i] ^ $key2[$i]);
    }
    return $hash_key;
}

function sha512($data) {
    return hash('sha512', $data, true);
}

function get_aes_key_and_iv($random_data) {
    $random_data_sha512 = sha512($random_data);
    $hash_key = get_hash_key();
    $data = $random_data_sha512 . $hash_key;
    $key_iv_hash = sha512($data);
    return [substr($key_iv_hash, 0, 16), substr($key_iv_hash, 16, 16)];
}

function aes_encrypt($data, $key, $iv) {
    $block_size = 16;
    $padding = $block_size - (strlen($data) % $block_size);
    $data .= str_repeat(chr($padding), $padding);
    
    return openssl_encrypt(
        $data, 
        'AES-128-CBC', 
        $key, 
        OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, 
        $iv
    );
}

function aes_decrypt($encrypted_data, $key, $iv) {
    $decrypted = openssl_decrypt(
        $encrypted_data, 
        'AES-128-CBC', 
        $key, 
        OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, 
        $iv
    );
    
    $padding = ord($decrypted[strlen($decrypted) - 1]);
    return substr($decrypted, 0, -$padding);
}

function tt_encrypt($data) {
    $magic = "\x74\x63\x05\x10\x00\x00";
    $random_bytes = random_bytes(32);
    list($aes_key, $aes_iv) = get_aes_key_and_iv($random_bytes);
    $data_sha512 = sha512($data);
    $raw_data = $data_sha512 . $data;
    $aes_data = aes_encrypt($raw_data, $aes_key, $aes_iv);
    return $magic . $random_bytes . $aes_data;
}

function tt_decrypt($enc_data) {
    $magic = "\x74\x63\x05\x10\x00\x00";
    
    if (strlen($enc_data) < 6 + 32) {
        throw new Exception("数据长度不足，无法解析");
    }
    
    if (substr($enc_data, 0, 6) !== $magic) {
        throw new Exception("data不匹配，可能不是有效的 tt_encrypt 数据");
    }
    
    $random_bytes = substr($enc_data, 6, 32);
    $aes_data = substr($enc_data, 38);
    
    list($aes_key, $aes_iv) = get_aes_key_and_iv($random_bytes);
    $raw_data = aes_decrypt($aes_data, $aes_key, $aes_iv);
    
    if (strlen($raw_data) < 64) {
        throw new Exception("解密后数据长度不足，可能数据损坏");
    }
    
    $data_sha512 = substr($raw_data, 0, 64);
    $data = substr($raw_data, 64);
    
    if (sha512($data) !== $data_sha512) {
        throw new Exception("SHA512 校验失败，数据可能被篡改");
    }
    
    return $data;
}

function TTEncrypt($data) {
    $compressed = gzencode($data, 6);
    if (strlen($compressed) < strlen($data)) {
        return tt_encrypt($compressed);
    }
    return tt_encrypt($data);
}

function TTDecrypt($enc_data) {
    $decrypted = tt_decrypt($enc_data);
    if (substr($decrypted, 0, 2) === "\x1f\x8b") { // GZIP magic header
        return gzdecode($decrypted);
    }
    return $decrypted;
}

// -------------------- 主逻辑函数 --------------------
function try_activate_privilege($header) {
    $params_dict = [];
    $params = explode('&', $header);
    foreach ($params as $param) {
        if (strpos($param, '=') !== false) {
            list($key, $value) = explode('=', $param, 2);
            $params_dict[$key] = $value;
        }
    }
    
    $base_url = "https://reading.snssdk.com/reading/user/privilege/add/v/?";
    
    $query_params = [
        'aid' => '1967',
        'app_name' => 'novelapp',
        'channel' => '0',
        'device_platform' => 'iphone',
        'os_version' => $params_dict['os_version'] ?? '0',
        'version_code' => '64933',
        'manifest_version_code' => '64933',
        'update_version_code' => '64933',
        'device_id' => $params_dict['device_id'] ?? '',
        'device_type' => $params_dict['device_type'] ?? '',
        'iid' => $params_dict['iid'] ?? '',
        'version_name' => $params_dict['version_name'] ?? '6.4.9'
    ];
    
    $request_body = [
        "add_count_daily" => 0,
        "amount" => 2592000,
        "privilege_id" => 7210376203117531962,
        "from" => 8,
        "unique_key" => (string)round(microtime(true) * 1000)
    ];
    
    $os_version = $params_dict['os_version'] ?? '17.0';
    $headers = [
        'content-type: application/json',
        'user-agent: EggFlower 6.4.9 rv:6.4.9.33 (iPhone; iOS ' . $os_version . '; zh_CN) Cronet'
    ];
    
    $url = $base_url . http_build_query($query_params);
    
    echo "尝试激活设备特权...\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_body));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        $res_data = json_decode($response, true);
        if (isset($res_data['code']) && $res_data['code'] == 0) {
            echo "已成功激活设备特权\n";
            return true;
        } else {
            echo "设备特权激活失败: " . print_r($res_data, true) . "\n";
            return false;
        }
    } else {
        echo "设备特权激活请求失败，状态码: $http_code\n";
        return false;
    }
}

function reverse_hex($num_str) {
    $hex_str = str_pad(dechex($num_str), 32, '0', STR_PAD_LEFT);
    $result = '';
    for ($i = 30; $i >= 0; $i -= 2) {
        $result .= substr($hex_str, $i, 2);
    }
    return $result;
}

function hex_to_bytes($hex_str) {
    return hex2bin($hex_str);
}

function decrypt_register_key($key_str) {
    // echo "开始解密注册密钥\n";
    
    $aes_key = base64_decode("rCXGfd2POMGzeiNIgo4iLg==");
    $key_bytes = base64_decode($key_str);
    
    $iv = substr($key_bytes, 0, 16);
    $encrypted_data = substr($key_bytes, 16);
    
    $cipher = openssl_decrypt(
        $encrypted_data, 
        'AES-128-CBC', 
        $aes_key, 
        OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, 
        $iv
    );
    
    $padding = ord($cipher[strlen($cipher) - 1]);
    $unpadded_key = substr($cipher, 0, -$padding);
    
    // echo "密钥解密成功\n";
    return base64_encode($unpadded_key);
}

function get_register_key($device_id) {
    // echo "开始获取注册密钥，设备ID: $device_id\n";
    
    $key = base64_decode("rCXGfd2POMGzeiNIgo4iLg==");
    $iv = substr(str_replace('-', '', generate_uuid()), 0, 16);
    
    $reversed_hex_did = reverse_hex($device_id);
    $data = hex_to_bytes($reversed_hex_did);
    
    $padding = 16 - (strlen($data) % 16);
    $data .= str_repeat(chr($padding), $padding);
    
    $encrypted_data = openssl_encrypt(
        $data, 
        'AES-128-CBC', 
        $key, 
        OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, 
        $iv
    );
    
    $combined = $iv . $encrypted_data;
    $content = base64_encode($combined);
    
    $request_body = [
        "content" => $content,
        "keyver" => 1
    ];
    
    $url = "https://api5-normal-sinfonlinec.fqnovel.com/reading/crypt/registerkey";
    
    $device_info = DEVICE_INFO[array_rand(DEVICE_INFO)];
    $os_version = IOS_VERSIONS[array_rand(IOS_VERSIONS)];
    $cdid = strtoupper(generate_uuid());
    
    $query_params = [
        "version_code" => "649",
        "app_name" => "novelapp_variant_v2",
        "device_id" => $device_id,
        "channel" => "App Store",
        "resolution" => $device_info["resolution"],
        "aid" => "507427",
        "version_name" => "6.4.9.33",
        "klink_egdi" => "AAIrUalHeuqAS-8H03FpamvSihyJTxCouo1NLfOqaUahQBEdh7z5T42j",
        "update_version_code" => "64933",
        "cdid" => $cdid,
        "ac" => ["wifi", "4g", "5g"][array_rand(["wifi", "4g", "5g"])],
        "os_version" => $os_version,
        "device_model" => $device_info["model"],
        "compliance_status" => "0",
        "ssmix" => "a",
        "device_platform" => "iphone",
        "iid" => str_pad(mt_rand(0, 9999999999999999), 16, '0', STR_PAD_LEFT),
        "device_type" => str_replace(' ', '%20', $device_info["type"])
    ];
    
    $headers = [
        "user-agent: EggFlower 6.4.9 rv:6.4.9.33 (iPhone; iOS $os_version; zh_CN) Cronet",
        "content-type: application/json",
    ];
    
    try {
        $full_url = $url . '?' . http_build_query($query_params);
        // echo "发送注册密钥请求: $full_url\n";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $full_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200) {
            $res_data = json_decode($response, true);
            if (isset($res_data["code"]) && $res_data["code"] == 0 && isset($res_data["data"])) {
                // echo "获取注册密钥成功\n";
                
                if (isset($res_data["data"]["key"])) {
                    try {
                        $decrypted_key = decrypt_register_key($res_data["data"]["key"]);
                        // echo "解密后的密钥: " . bin2hex(base64_decode($decrypted_key)) . "\n";
                        $res_data["data"]["decrypted_key"] = bin2hex(base64_decode($decrypted_key));
                    } catch (Exception $e) {
                        // echo "密钥解密失败: " . $e->getMessage() . "\n";
                    }
                }
                
                return $res_data["data"];
            } else {
                // echo "获取注册密钥失败，服务器返回: " . print_r($res_data, true) . "\n";
                throw new Exception("获取注册密钥失败");
            }
        } else {
            // echo "获取注册密钥请求失败，状态码: $http_code\n";
            throw new Exception("获取注册密钥请求失败");
        }
    } catch (Exception $e) {
        // echo "获取注册密钥异常: " . $e->getMessage() . "\n";
        throw $e;
    }
}

function register() {
    // echo "正在注册iOS设备...\n";
    
    $data = generate_full_request_body();
    // echo "生成的设备数据: " . print_r($data, true) . "\n";
    
    $device_id = $data["header"]["device_id"];
    $json_data = json_encode($data, JSON_UNESCAPED_UNICODE);
    $encrypted_json = TTEncrypt($json_data);
    
    $device_info = $data["header"];
    $device_model = $device_info["device_model"];
    $device_type = $device_info["device_type"];
    $install_id = $device_info["install_id"];
    $idfv = $device_info["idfv"];
    $os_version = $device_info["os_version"];
    $resolution = $device_info["resolution"];
    $access = strtolower($device_info["access"]);
    
    $base_url = "https://i.snssdk.com/service/2/device_register/";
    
    $query_params = [
        "tt_data" => "a",
        "device_id" => $device_id,
        "is_activated" => "1",
        "aid" => "507427",
        "caid1" => bin2hex(random_bytes(16)),
        "version_code" => "649",
        "app_name" => "novelapp_variant_v2",
        "vid" => $idfv,
        "channel" => "App Store",
        "resolution" => $resolution,
        "version_name" => "6.4.9",
        "update_version_code" => "64933",
        "gender" => "2",
        "cdid" => $data["header"]["cdid"],
        "idfv" => $idfv,
        "ac" => $access,
        "os_version" => $os_version,
        "device_model" => $device_model,
        "compliance_status" => "0",
        "ssmix" => "a",
        "caid2" => bin2hex(random_bytes(16)),
        "device_platform" => "iphone",
        "iid" => $install_id,
        "device_type" => str_replace(' ', '%20', $device_type),
        "idfa" => "00000000-0000-0000-0000-000000000000"
    ];
    
    $headers = [
        "user-agent: EggFlower 6.4.9 rv:6.4.9.33 (iPhone; iOS $os_version; zh_CN) Cronet",
        "content-type: application/octet-stream; tt-data=a",
    ];
    
    $urls_to_try = [
        "https://log.snssdk.com/service/2/device_register/",
    ];
    
    $response = null;
    $http_code = 0;
    
    foreach ($urls_to_try as $url) {
        try {
            // echo "尝试URL: $url\n";
            
            $full_url = $url . '?' . http_build_query($query_params);
            // echo "完整URL: $full_url\n";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $full_url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $encrypted_json);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code == 200) {
                // echo "请求成功，状态码: $http_code\n";
                break;
            } else {
                // echo "请求失败，状态码: $http_code，尝试下一个URL\n";
            }
        } catch (Exception $e) {
            // echo "请求URL $url 失败: " . $e->getMessage() . "，尝试下一个URL\n";
        }
    }
    
    if ($http_code != 200) {
        // echo "所有URL均请求失败\n";
        throw new Exception("所有URL均请求失败");
    }
    
    // echo "响应内容: " . substr($response, 0, 500) . "\n";
    
    $res_data = json_decode($response, true);
    if (isset($res_data['device_id']) && $res_data['device_id'] != 0) {
        $device_id_str = $res_data['device_id_str'] ?? (string)$res_data['device_id'];
        $install_id_str = $res_data['install_id_str'] ?? (string)$res_data['install_id'];
        
        $header = "device_id=$device_id_str" . 
                  "&device_type=$device_type" . 
                  "&iid=$install_id_str" . 
                  "&version_name=6.4.9" . 
                  "&os_version=$os_version";
        
        // 先获取decrypted_key
        $decrypted_key = '';
        try {
            $register_key = get_register_key($device_id_str);
            if (isset($register_key['decrypted_key'])) {
                $decrypted_key = $register_key['decrypted_key'];
            }
            // echo "成功获取注册密钥: " . print_r($register_key, true) . "\n";
        } catch (Exception $e) {
            // echo "获取注册密钥失败: " . $e->getMessage() . "\n";
        }
        
        // 写入key.json，格式为decrypted_key@device_id，decrypted_key需大写
        $key_json_data = [
            'key' => strtoupper($decrypted_key) . '@' . $device_id_str,
            'update_time' => date('Y-m-d H:i:s')
        ];
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/ios_key.json', json_encode($key_json_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        
        // 返回与安卓注册一致的结构
        return [
            'device_id' => $device_id_str,
            'install_id' => $install_id_str,
            'message' => '设备注册成功', // 统一为“设备注册成功”
            'status' => 'active'
        ];
    } else {
        // echo "iOS设备注册失败，服务器返回: " . print_r($res_data, true) . "\n";
        throw new Exception('device register error');
    }
}

// 主程序入口
// try {
//     $result = register();
//     echo "注册结果: " . print_r($result, true) . "\n";
// } catch (Exception $e) {
//     echo "注册失败: " . $e->getMessage() . "\n";
// }
?>