<?php

require_once __DIR__ . '/../vendor/autoload.php';

use ProxmoxApi\Client;
use ProxmoxApi\Exception\AuthenticationException;
use ProxmoxApi\Exception\ProxmoxApiException;
use Swoole\Coroutine\WaitGroup;
use Swoole\Coroutine;

// 确保已安装 hyperf/guzzle 组件
// composer require hyperf/guzzle

// 在协程环境中运行
Coroutine\run(function () {
    try {
        // 创建客户端实例，启用协程支持
        $client = new Client([
            'hostname' => 'your-proxmox-server.com',
            'username' => 'root',
            'password' => 'your-password',
            'realm' => 'pam',
            'port' => 8006,
            'verify' => false, // 在生产环境中应设置为true
            'use_coroutine' => true, // 启用协程支持
        ]);

        // 并发获取多个节点的信息
        $wg = new WaitGroup();
        $results = [];

        // 获取节点列表
        $nodes = $client->getNodes();
        
        if (empty($nodes)) {
            throw new \Exception('没有可用的节点');
        }

        echo "找到 " . count($nodes) . " 个节点，开始并发获取信息...\n";

        // 为每个节点创建一个协程
        foreach ($nodes as $node) {
            $nodeName = $node['node'];
            $wg->add();

            // 在协程中获取节点信息
            Coroutine::create(function () use ($client, $nodeName, &$results, $wg) {
                try {
                    echo "开始获取节点 {$nodeName} 的信息...\n";
                    
                    // 获取节点上的虚拟机列表
                    $vms = $client->getNodeVMs($nodeName);
                    
                    // 获取节点上的容器列表
                    $containers = $client->nodes->getContainers($nodeName);
                    
                    // 获取节点上的存储列表
                    $storages = $client->storage->getNodeStorages($nodeName);
                    
                    $results[$nodeName] = [
                        'vms' => count($vms),
                        'containers' => count($containers),
                        'storages' => count($storages),
                    ];
                    
                    echo "节点 {$nodeName} 信息获取完成\n";
                } catch (\Exception $e) {
                    $results[$nodeName] = ['error' => $e->getMessage()];
                    echo "节点 {$nodeName} 信息获取失败: " . $e->getMessage() . "\n";
                } finally {
                    $wg->done();
                }
            });
        }

        // 等待所有协程完成
        $wg->wait();

        echo "所有节点信息获取完成，结果如下：\n";
        print_r($results);

    } catch (AuthenticationException $e) {
        echo "认证失败: " . $e->getMessage() . "\n";
    } catch (ProxmoxApiException $e) {
        echo "API请求失败: " . $e->getMessage() . "\n";
    } catch (\Exception $e) {
        echo "发生错误: " . $e->getMessage() . "\n";
    }
}); 