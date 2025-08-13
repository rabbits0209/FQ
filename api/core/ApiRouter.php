<?php
/**
 * API路由器
 * 负责分发请求到对应的处理器
 */

class ApiRouter
{
    private $routes = [
        // 内容类API
        'content' => 'ExactContentEndpoint',  // 使用完全按照原文件逻辑的版本
        'chapter' => 'ContentEndpoint', // index.php的功能
        'manga' => 'MangaEndpoint', // 新增漫画接口
        'raw_full' => 'ContentEndpoint', // 新增raw_full接口，分发到ContentEndpoint
        
        // 信息类API  
        'book' => 'BookEndpoint',
        'directory' => 'DirectoryEndpoint',
        'search' => 'SearchEndpoint',
        'item_info' => 'ItemInfoEndpoint',
        'full' => 'FullEndpoint',
        
        // 媒体类API
        'video' => 'VideoEndpoint',
        
        // 设备类API
        'device_register' => 'DeviceRegisterEndpoint',
        'device_manage' => 'DeviceManageEndpoint',
        
        // iOS专用API
        'ios_content' => 'IosContentEndpoint',
        'ios_register' => 'IosDeviceRegisterEndpoint'
    ];

    public function route()
    {
        // 获取API类型
        $api = $_GET['api'] ?? $this->detectApiFromPath();
        
        if (!isset($this->routes[$api])) {
            throw new Exception("不支持的API类型: {$api}");
        }

        $endpointClass = $this->routes[$api];
        
        // 检查类是否存在
        if (!class_exists($endpointClass)) {
            throw new Exception("API处理器类不存在: {$endpointClass}");
        }

        $endpoint = new $endpointClass();
        
        // 执行API处理
        $endpoint->handle();
    }

    private function detectApiFromPath()
    {
        $path = $_SERVER['REQUEST_URI'] ?? '';
        $pathInfo = pathinfo($path);
        $filename = $pathInfo['filename'] ?? '';
        
        // 根据原始文件名映射到新的API类型
        $fileMapping = [
            'index' => 'chapter',
            'content' => 'content', 
            'book' => 'book',
            'search' => 'search',
            'video' => 'video',
            'directory' => 'directory',
            'item_info' => 'item_info',
            'full' => 'full',
            'mygx' => 'device_manage',
            'ios_content' => 'ios_content',
            'ios_device_register' => 'ios_register'
        ];

        return $fileMapping[$filename] ?? 'content';
    }

    /**
     * 获取所有可用的路由
     */
    public function getAvailableRoutes()
    {
        return array_keys($this->routes);
    }

    /**
     * 检查路由是否存在
     */
    public function routeExists($api)
    {
        return isset($this->routes[$api]);
    }
}
