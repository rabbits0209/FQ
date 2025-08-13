<?php
function generate_signature($url, $userAgent = 'com.dragon.read.oversea.gp/68132 (Linux; U; Android 12; zh_CN; ANA-AN00; Build/V417IR;tt-ok/3.12.13.4-tiktok)') {
    $headersArray = [
        'user-agent',
        $userAgent
    ];
    $headersStr = implode("\r\n", $headersArray);
    $signatureUrl = 'http://127.0.0.1:8800/api/fq-signature/generateSignature';
    $postData = json_encode(['url' => $url, 'headers' => $headersStr], JSON_UNESCAPED_UNICODE);
    $ch = curl_init($signatureUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
    $signatureResponse = curl_exec($ch);
    curl_close($ch);
    $signatureData = json_decode($signatureResponse, true);
    if (!is_array($signatureData)) {
        return [
            'error' => '签名服务返回无效数据',
            'raw' => $signatureResponse
        ];
    }
    return $signatureData;
}

function generate_x_gorgon($query_string) {
    $userAgent = 'com.dragon.read.oversea.gp/68132 (Linux; U; Android 12; zh_CN; ANA-AN00; Build/V417IR;tt-ok/3.12.13.4-tiktok)';
    $headersArray = [
        'user-agent',
        $userAgent
    ];
    $headersStr = implode("\r\n", $headersArray);
    $signatureUrl = 'http://127.0.0.1:8800/api/fq-signature/generateSignature';
    $url = 'https://api5-normal-sinfonlineb.fqnovel.com/reading/reader/batch_full/v?' . $query_string;
    $postData = json_encode(['url' => $url, 'headers' => $headersStr], JSON_UNESCAPED_UNICODE);
    $ch = curl_init($signatureUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
    $signatureResponse = curl_exec($ch);
    curl_close($ch);
    $signatureData = json_decode($signatureResponse, true);
    if (!is_array($signatureData) || !isset($signatureData['X-Gorgon']) || !isset($signatureData['X-Khronos'])) {
        return [
            'x_gorgon' => '',
            'timestamp' => ''
        ];
    }
    return [
        'x_gorgon' => $signatureData['X-Gorgon'],
        'timestamp' => $signatureData['X-Khronos']
    ];
}
?>
