<?php
/**
 * 算法管理器
 * 根据配置动态切换算法（支持8404、0404、8402）
 */

class AlgorithmManager
{
    private $config;
    private $supportedAlgorithms = ['8404', '0404', '8402'];

    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * 生成X-Gorgon签名
     * @param string $queryString 查询字符串
     * @param bool $isIos 是否为iOS请求
     * @return array 包含x_gorgon和timestamp的数组
     */
    public function generateXGorgon($queryString, $isIos = false)
    {
        // iOS强制使用8402算法
        if ($isIos) {
            return $this->useAlgorithm('8402', $queryString);
        }

        // 从配置获取算法类型
        $algorithm = $this->config['zwsf'] ?? '8404';
        
        // 验证算法是否支持
        if (!in_array($algorithm, $this->supportedAlgorithms)) {
            throw new Exception("不支持的算法类型: {$algorithm}");
        }

        // 如果不是8404或0404，则不能用于非iOS设备
        if (!$isIos && !in_array($algorithm, ['8404', '0404'])) {
            throw new Exception("算法 {$algorithm} 仅适用于iOS设备");
        }

        return $this->useAlgorithm($algorithm, $queryString);
    }

    /**
     * 使用指定算法生成签名
     */
    private function useAlgorithm($algorithm, $queryString)
    {
        $algorithmFile = $_SERVER['DOCUMENT_ROOT'] . "/config/{$algorithm}.php";
        
        if (!file_exists($algorithmFile)) {
            throw new Exception("算法文件不存在: {$algorithm}.php");
        }

        // 包含算法文件
        require_once $algorithmFile;

        // 调用算法函数
        if (!function_exists('generate_x_gorgon')) {
            throw new Exception("算法文件 {$algorithm}.php 中缺少 generate_x_gorgon 函数");
        }

        return generate_x_gorgon($queryString);
    }

    /**
     * 获取当前配置的算法类型
     */
    public function getCurrentAlgorithm($isIos = false)
    {
        if ($isIos) {
            return '8402';
        }
        return $this->config['zwsf'] ?? '8404';
    }

    /**
     * 验证算法配置是否有效
     */
    public function validateAlgorithmConfig()
    {
        $algorithm = $this->config['zwsf'] ?? null;
        
        if (!$algorithm) {
            throw new Exception('配置文件中缺少算法设置 (zwsf)');
        }

        if (!in_array($algorithm, $this->supportedAlgorithms)) {
            throw new Exception("配置的算法 {$algorithm} 不在支持列表中: " . implode(', ', $this->supportedAlgorithms));
        }

        $algorithmFile = $_SERVER['DOCUMENT_ROOT'] . "/config/{$algorithm}.php";
        if (!file_exists($algorithmFile)) {
            throw new Exception("算法文件不存在: {$algorithm}.php");
        }

        return true;
    }

    /**
     * 获取支持的算法列表
     */
    public function getSupportedAlgorithms()
    {
        return $this->supportedAlgorithms;
    }
}
