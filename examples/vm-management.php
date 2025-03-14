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

    // 创建一个新的虚拟机
    $vmid = 1000; // 确保这个ID在您的集群中是唯一的
    $vmName = 'test-vm-' . time();

    echo "创建新虚拟机 (ID: {$vmid}, 名称: {$vmName})...\n";
    
    $createParams = [
        'vmid' => $vmid,
        'name' => $vmName,
        'memory' => 1024, // 1GB内存
        'cores' => 2,     // 2个CPU核心
        'ostype' => 'l26', // Linux 2.6/3.x/4.x Kernel
        'net0' => 'virtio,bridge=vmbr0',
        'ide2' => 'local:iso/debian-11.0.0-amd64-netinst.iso,media=cdrom', // 确保ISO存在
        'scsihw' => 'virtio-scsi-pci',
        'scsi0' => 'local-lvm:10,format=raw', // 10GB磁盘
        'boot' => 'cdn',
        'bootdisk' => 'scsi0',
    ];

    try {
        $result = $client->createVM($nodeName, $createParams);
        echo "虚拟机创建任务已提交: " . json_encode($result) . "\n";
        
        // 等待任务完成
        if (isset($result['upid'])) {
            $taskId = $result['upid'];
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
                            echo "虚拟机创建成功!\n";
                        } else {
                            echo "虚拟机创建失败: " . ($taskStatus['exitstatus'] ?? '未知错误') . "\n";
                        }
                    }
                }
            }
        }
        
        // 获取虚拟机状态
        $vmStatus = $client->getVMStatus($nodeName, $vmid);
        echo "虚拟机状态: " . ($vmStatus['status'] ?? '未知') . "\n";
        
        // 启动虚拟机
        echo "启动虚拟机...\n";
        $startResult = $client->startVM($nodeName, $vmid);
        echo "启动任务已提交: " . json_encode($startResult) . "\n";
        
        // 等待几秒钟
        sleep(5);
        
        // 再次获取状态
        $vmStatus = $client->getVMStatus($nodeName, $vmid);
        echo "虚拟机状态: " . ($vmStatus['status'] ?? '未知') . "\n";
        
        // 停止虚拟机
        echo "停止虚拟机...\n";
        $stopResult = $client->stopVM($nodeName, $vmid);
        echo "停止任务已提交: " . json_encode($stopResult) . "\n";
        
        // 等待几秒钟
        sleep(5);
        
        // 再次获取状态
        $vmStatus = $client->getVMStatus($nodeName, $vmid);
        echo "虚拟机状态: " . ($vmStatus['status'] ?? '未知') . "\n";
        
        // 获取虚拟机配置
        $vmConfig = $client->nodes->getVMConfig($nodeName, $vmid);
        echo "虚拟机配置:\n";
        print_r($vmConfig);
        
        // 更新虚拟机配置
        echo "更新虚拟机配置...\n";
        $updateResult = $client->nodes->updateVMConfig($nodeName, $vmid, [
            'memory' => 2048, // 增加到2GB内存
            'description' => '这是一个测试虚拟机',
        ]);
        echo "更新任务已提交: " . json_encode($updateResult) . "\n";
        
        // 获取更新后的配置
        $vmConfig = $client->nodes->getVMConfig($nodeName, $vmid);
        echo "更新后的虚拟机配置:\n";
        print_r($vmConfig);
        
        // 删除虚拟机
        echo "删除虚拟机...\n";
        $deleteResult = $client->nodes->deleteVM($nodeName, $vmid);
        echo "删除任务已提交: " . json_encode($deleteResult) . "\n";
        
    } catch (ProxmoxApiException $e) {
        echo "API错误: " . $e->getMessage() . "\n";
    }

} catch (AuthenticationException $e) {
    echo "认证错误: " . $e->getMessage() . "\n";
} catch (ProxmoxApiException $e) {
    echo "API错误: " . $e->getMessage() . "\n";
} catch (\Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
} 