# Hyperf 集成指南

本文档介绍如何在 Hyperf 框架中使用 Proxmox API 客户端，以充分利用协程提高性能。

## 安装

首先，确保已安装 Proxmox API 客户端和 Hyperf Guzzle 组件：

```bash
composer require bqscea/proxmox-api
composer require hyperf/guzzle
```

## 基本用法

在 Hyperf 中使用 Proxmox API 客户端非常简单，只需在创建客户端实例时启用协程支持：

```php
<?php

namespace App\Controller;

use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use ProxmoxApi\Client;

#[Controller]
class ProxmoxController
{
    /**
     * @var Client
     */
    private $client;

    public function __construct()
    {
        $this->client = new Client([
            'hostname' => 'your-proxmox-server.com',
            'username' => 'root',
            'password' => 'your-password',
            'realm' => 'pam',
            'port' => 8006,
            'verify' => false, // 在生产环境中应设置为true
            'use_coroutine' => true, // 启用协程支持
        ]);
    }

    #[RequestMapping(path: "/nodes", methods: "GET")]
    public function getNodes()
    {
        return $this->client->getNodes();
    }

    #[RequestMapping(path: "/vms/{node}", methods: "GET")]
    public function getVMs(string $node)
    {
        return $this->client->getNodeVMs($node);
    }
}
```

## 依赖注入

在 Hyperf 中，你可以使用依赖注入容器来管理 Proxmox API 客户端实例：

```php
<?php

namespace App\Service;

use Hyperf\Contract\ConfigInterface;
use ProxmoxApi\Client;

class ProxmoxService
{
    /**
     * @var Client
     */
    private $client;

    public function __construct(ConfigInterface $config)
    {
        $this->client = new Client([
            'hostname' => $config->get('proxmox.hostname'),
            'username' => $config->get('proxmox.username'),
            'password' => $config->get('proxmox.password'),
            'realm' => $config->get('proxmox.realm', 'pam'),
            'port' => $config->get('proxmox.port', 8006),
            'verify' => $config->get('proxmox.verify', false),
            'use_coroutine' => true, // 启用协程支持
        ]);
    }

    public function getNodes()
    {
        return $this->client->getNodes();
    }

    // 其他方法...
}
```

然后在配置文件 `config/autoload/proxmox.php` 中添加：

```php
<?php

return [
    'hostname' => env('PROXMOX_HOSTNAME', 'your-proxmox-server.com'),
    'username' => env('PROXMOX_USERNAME', 'root'),
    'password' => env('PROXMOX_PASSWORD', 'your-password'),
    'realm' => env('PROXMOX_REALM', 'pam'),
    'port' => (int) env('PROXMOX_PORT', 8006),
    'verify' => (bool) env('PROXMOX_VERIFY', false),
];
```

## 并发请求

Hyperf 的协程特性允许你轻松地执行并发请求，以下是一个示例：

```php
<?php

namespace App\Controller;

use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\Utils\Coroutine;
use Hyperf\Utils\Coroutine\Concurrent;
use ProxmoxApi\Client;

#[Controller]
class ProxmoxController
{
    /**
     * @var Client
     */
    private $client;

    public function __construct()
    {
        $this->client = new Client([
            'hostname' => 'your-proxmox-server.com',
            'username' => 'root',
            'password' => 'your-password',
            'realm' => 'pam',
            'port' => 8006,
            'verify' => false,
            'use_coroutine' => true,
        ]);
    }

    #[RequestMapping(path: "/cluster-info", methods: "GET")]
    public function getClusterInfo()
    {
        // 获取节点列表
        $nodes = $this->client->getNodes();
        
        // 创建并发执行器，最大并发数为5
        $concurrent = new Concurrent(5);
        
        $results = [];
        
        foreach ($nodes as $node) {
            $nodeName = $node['node'];
            
            // 添加并发任务
            $concurrent->create(function () use ($nodeName, &$results) {
                try {
                    // 获取节点上的虚拟机列表
                    $vms = $this->client->getNodeVMs($nodeName);
                    
                    // 获取节点上的容器列表
                    $containers = $this->client->nodes->getContainers($nodeName);
                    
                    // 获取节点上的存储列表
                    $storages = $this->client->storage->getNodeStorages($nodeName);
                    
                    $results[$nodeName] = [
                        'vms' => count($vms),
                        'containers' => count($containers),
                        'storages' => count($storages),
                    ];
                } catch (\Exception $e) {
                    $results[$nodeName] = ['error' => $e->getMessage()];
                }
            });
        }
        
        // 等待所有任务完成
        $concurrent->wait();
        
        return $results;
    }
}
```

## 异步任务

你还可以将 Proxmox API 请求放在 Hyperf 的异步任务中执行：

```php
<?php

namespace App\Task;

use Hyperf\AsyncQueue\Annotation\AsyncQueueMessage;
use Hyperf\Utils\ApplicationContext;
use ProxmoxApi\Client;

class ProxmoxTask
{
    /**
     * @AsyncQueueMessage
     */
    public function createVM(string $node, array $params)
    {
        $client = new Client([
            'hostname' => 'your-proxmox-server.com',
            'username' => 'root',
            'password' => 'your-password',
            'realm' => 'pam',
            'port' => 8006,
            'verify' => false,
            'use_coroutine' => true,
        ]);
        
        try {
            $result = $client->createVM($node, $params);
            
            // 处理结果...
            
        } catch (\Exception $e) {
            // 处理异常...
        }
    }
}
```

## 性能优化

在 Hyperf 中使用 Proxmox API 客户端时，可以考虑以下性能优化措施：

1. **连接池**：对于高并发场景，可以考虑实现一个 Proxmox API 客户端的连接池。

2. **超时设置**：根据实际情况调整请求超时时间，避免长时间阻塞协程。

3. **错误重试**：对于网络不稳定的情况，可以实现请求失败自动重试机制。

4. **缓存**：对于频繁请求但变化不大的数据，可以使用缓存来减少 API 调用。

## 注意事项

1. 确保 Hyperf 运行在 Swoole 环境中，否则协程功能将无法正常工作。

2. 在协程环境中，要注意全局变量和静态变量的使用，避免数据混乱。

3. 对于长时间运行的请求，要设置合理的超时时间，避免协程泄漏。

4. 在生产环境中，建议启用 SSL 验证（将 `verify` 设置为 `true`）以确保安全性。

## 故障排除

如果在使用过程中遇到问题，可以尝试以下解决方法：

1. **检查 Swoole 版本**：确保 Swoole 版本支持协程功能。

2. **检查 Hyperf Guzzle 组件**：确保已正确安装 `hyperf/guzzle` 组件。

3. **启用调试模式**：在开发环境中启用调试模式，查看详细的错误信息。

4. **检查网络连接**：确保 Hyperf 服务器可以正常连接到 Proxmox 服务器。

5. **检查认证信息**：确保提供的用户名、密码和域正确无误。 