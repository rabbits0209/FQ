<?php
/**
 * iOS设备注册API端点（极简版，仅调用 IosDeviceRegister.php）
 */

class IosDeviceRegisterEndpoint extends BaseEndpoint
{
    public function handle()
    {
        $action = $_GET['action'] ?? 'register';
        if ($action === 'register') {
            require_once __DIR__ . '/IosDeviceRegister.php';
            try {
                $result = register();
                $data = [
                    'device_id' => $result['device_id'],
                    'message' => $result['message'],
                    'status' => $result['status']
                ];
                echo json_encode([
                    'success' => true,
                    'data' => $data
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                exit;
            } catch (Exception $e) {
                $this->sendError(500, 'iOS设备注册失败', $e->getMessage());
            }
        } elseif ($action === 'status') {
            try {
                $deviceKeys = $this->getDeviceKeys(true);
                $this->sendSuccess([
                    'device_id' => $deviceKeys['device_id'],
                    'status' => 'active',
                    'algorithm' => $this->algorithmManager->getCurrentAlgorithm()
                ]);
            } catch (Exception $e) {
                $this->sendError(404, 'iOS设备未注册', $e->getMessage());
            }
        } else {
            $this->sendError(400, "不支持的操作: {$action}");
        }
    }
}