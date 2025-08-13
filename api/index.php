<?php
/**
 * API统一入口文件
 * 根据路由分发到不同的API处理器
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// 自动加载核心类
spl_autoload_register(function ($class) {
    $dirs = ['core', 'auth', 'algorithms', 'endpoints', 'devices'];
    foreach ($dirs as $dir) {
        $file = __DIR__ . "/{$dir}/{$class}.php";
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

try {
    // 解析路由
    $router = new ApiRouter();
    $router->route();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'get_params' => $_GET
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
