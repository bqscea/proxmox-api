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
    echo "节点列表:\n";
    print_r($nodes);

    // 假设我们有一个名为'node1'的节点
    if (!empty($nodes)) {
        $nodeName = $nodes[0]['node'] ?? 'pve';
        
        // 获取节点上的虚拟机列表
        $vms = $client->getNodeVMs($nodeName);
        echo "\n虚拟机列表:\n";
        print_r($vms);

        // 如果有虚拟机，获取第一个虚拟机的状态
        if (!empty($vms)) {
            $vmid = $vms[0]['vmid'];
            $vmStatus = $client->getVMStatus($nodeName, $vmid);
            echo "\n虚拟机 {$vmid} 状态:\n";
            print_r($vmStatus);
        }

        // 获取节点上的存储列表
        $storages = $client->storage->getNodeStorages($nodeName);
        echo "\n存储列表:\n";
        print_r($storages);

        // 获取集群状态
        $clusterStatus = $client->cluster->getStatus();
        echo "\n集群状态:\n";
        print_r($clusterStatus);

        // 获取用户列表
        $users = $client->access->getUsers();
        echo "\n用户列表:\n";
        print_r($users);
    }

} catch (AuthenticationException $e) {
    echo "认证错误: " . $e->getMessage() . "\n";
} catch (ProxmoxApiException $e) {
    echo "API错误: " . $e->getMessage() . "\n";
} catch (\Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
} 