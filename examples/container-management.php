<?php

require_once __DIR__ . '/../vendor/autoload.php';

use ProxmoxApi\Client;
use ProxmoxApi\Exception\AuthenticationException;
use ProxmoxApi\Exception\ProxmoxApiException;

// 创建客户端实例
try {
    $client = new Client([
        'hostname' => 'your-proxmox-server.com',
        'username' => 'root',
        'password' => 'your-password',
        'realm' => 'pam',
        'port' => 8006,
        'verify' => false, // 在生产环境中应设置为true
    ]);

    // 获取节点列表
    $nodes = $client->getNodes();
    
    if (empty($nodes)) {
        throw new \Exception('没有可用的节点');
    }

    // 使用第一个节点
    $nodeName = $nodes[0]['node'];
    echo "使用节点: {$nodeName}\n";

    // 获取节点上的容器列表
    $containers = $client->nodes->getContainers($nodeName);
    echo "容器列表:\n";
    print_r($containers);

    // 创建一个新的容器
    $ctid = 200; // 确保这个ID在您的集群中是唯一的
    $ctName = 'test-ct-' . time();

    echo "创建新容器 (ID: {$ctid}, 名称: {$ctName})...\n";
    
    // 确保您有一个有效的容器模板
    $templates = $client->storage->getContainerTemplates($nodeName, 'local');
    echo "可用模板:\n";
    print_r($templates);
    
    if (empty($templates)) {
        echo "没有可用的容器模板，跳过容器创建\n";
    } else {
        // 使用第一个可用的模板
        $template = $templates[0]['volid'] ?? '';
        
        if (empty($template)) {
            echo "无法获取有效的模板，跳过容器创建\n";
        } else {
            echo "使用模板: {$template}\n";
            
            $createParams = [
                'ostemplate' => $template,
                'vmid' => $ctid,
                'hostname' => $ctName,
                'memory' => 512, // 512MB内存
                'swap' => 512,   // 512MB交换空间
                'cores' => 1,    // 1个CPU核心
                'net0' => 'name=eth0,bridge=vmbr0,ip=dhcp',
                'storage' => 'local-lvm',
                'rootfs' => 'local-lvm:8', // 8GB根文件系统
            ];

            try {
                $result = $client->post("nodes/{$nodeName}/lxc", $createParams)->toArray();
                echo "容器创建任务已提交: " . json_encode($result) . "\n";
                
                // 等待任务完成
                if (isset($result['data'])) {
                    $taskId = $result['data'];
                    echo "等待任务 {$taskId} 完成...\n";
                    
                    $isRunning = true;
                    while ($isRunning) {
                        sleep(2);
                        $taskStatus = $client->nodes->getTaskStatus($nodeName, $taskId);
                        
                        if (isset($taskStatus['status'])) {
                            echo "任务状态: " . $taskStatus['status'] . "\n";
                            
                            if ($taskStatus['status'] === 'stopped') {
                                $isRunning = false;
                                
                                if (isset($taskStatus['exitstatus']) && $taskStatus['exitstatus'] === 'OK') {
                                    echo "容器创建成功!\n";
                                } else {
                                    echo "容器创建失败: " . ($taskStatus['exitstatus'] ?? '未知错误') . "\n";
                                }
                            }
                        }
                    }
                }
                
                // 获取容器状态
                $ctStatus = $client->nodes->getContainerStatus($nodeName, $ctid);
                echo "容器状态: " . ($ctStatus['status'] ?? '未知') . "\n";
                
                // 启动容器
                echo "启动容器...\n";
                $startResult = $client->post("nodes/{$nodeName}/lxc/{$ctid}/status/start")->toArray();
                echo "启动任务已提交: " . json_encode($startResult) . "\n";
                
                // 等待几秒钟
                sleep(5);
                
                // 再次获取状态
                $ctStatus = $client->nodes->getContainerStatus($nodeName, $ctid);
                echo "容器状态: " . ($ctStatus['status'] ?? '未知') . "\n";
                
                // 停止容器
                echo "停止容器...\n";
                $stopResult = $client->post("nodes/{$nodeName}/lxc/{$ctid}/status/stop")->toArray();
                echo "停止任务已提交: " . json_encode($stopResult) . "\n";
                
                // 等待几秒钟
                sleep(5);
                
                // 再次获取状态
                $ctStatus = $client->nodes->getContainerStatus($nodeName, $ctid);
                echo "容器状态: " . ($ctStatus['status'] ?? '未知') . "\n";
                
                // 获取容器配置
                $ctConfig = $client->get("nodes/{$nodeName}/lxc/{$ctid}/config")->toArray();
                echo "容器配置:\n";
                print_r($ctConfig);
                
                // 更新容器配置
                echo "更新容器配置...\n";
                $updateResult = $client->put("nodes/{$nodeName}/lxc/{$ctid}/config", [
                    'memory' => 1024, // 增加到1GB内存
                    'description' => '这是一个测试容器',
                ])->toArray();
                echo "更新任务已提交: " . json_encode($updateResult) . "\n";
                
                // 获取更新后的配置
                $ctConfig = $client->get("nodes/{$nodeName}/lxc/{$ctid}/config")->toArray();
                echo "更新后的容器配置:\n";
                print_r($ctConfig);
                
                // 删除容器
                echo "删除容器...\n";
                $deleteResult = $client->delete("nodes/{$nodeName}/lxc/{$ctid}")->toArray();
                echo "删除任务已提交: " . json_encode($deleteResult) . "\n";
                
            } catch (ProxmoxApiException $e) {
                echo "API错误: " . $e->getMessage() . "\n";
            }
        }
    }

} catch (AuthenticationException $e) {
    echo "认证错误: " . $e->getMessage() . "\n";
} catch (ProxmoxApiException $e) {
    echo "API错误: " . $e->getMessage() . "\n";
} catch (\Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
} 