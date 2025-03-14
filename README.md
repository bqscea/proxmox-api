# Proxmox API 客户端

这是一个基于PHP 8.0+的Proxmox VE API客户端封装包，提供了简单易用的接口来与Proxmox VE服务器进行交互。

![版本](https://img.shields.io/badge/版本-1.0.0-blue.svg)
![PHP版本](https://img.shields.io/badge/PHP-8.0+-green.svg)
![许可证](https://img.shields.io/badge/许可证-MIT-yellow.svg)

[English Version](README.en.md)

## 安装

使用Composer安装:

```bash
composer require bqscea/proxmox-api
```

## 基本用法

```php
<?php

require 'vendor/autoload.php';

use ProxmoxApi\Client;

// 创建客户端实例
$client = new Client([
    'hostname' => 'your-proxmox-server.com',
    'username' => 'root',
    'password' => 'your-password',
    'realm' => 'pam', // 默认为 'pam'
    'port' => 8006,   // 默认为 8006
    'verify' => true  // 是否验证SSL证书
]);

// 获取节点列表
$nodes = $client->getNodes();

// 获取特定节点的虚拟机列表
$vms = $client->getNodeVMs('node1');

// 获取特定虚拟机的状态
$vmStatus = $client->getVMStatus('node1', 100);

// 启动虚拟机
$client->startVM('node1', 100);

// 停止虚拟机
$client->stopVM('node1', 100);

// 创建虚拟机
$client->createVM('node1', [
    'vmid' => 101,
    'name' => 'test-vm',
    'memory' => 1024,
    'cores' => 2,
    // 其他参数...
]);
```

## 功能特性

- 完整支持Proxmox VE API
- 简单易用的接口
- 支持所有节点、集群、存储、虚拟机和容器操作
- 自动处理认证和会话管理
- 支持异步操作
- 详细的错误处理和日志记录
- 支持Hyperf协程环境（需安装hyperf/guzzle）

## 文档

详细的API文档请参考[这里](docs/index.md)。

## 许可证

MIT 

## 测试

本项目包含完整的单元测试，用于确保代码质量和功能正确性。

### 运行测试

```bash
# 运行所有测试
composer test

# 生成测试覆盖率报告
composer test-coverage

# 运行代码风格检查
composer cs-check

# 自动修复代码风格问题
composer cs-fix
```

### 测试覆盖范围

测试覆盖以下模块：

1. **自动化任务基类** - 测试日志记录、任务等待和虚拟机过滤功能
2. **批量虚拟机操作任务** - 测试批量启动、停止、克隆等操作
3. **批量备份任务** - 测试备份创建、定时计划和旧备份清理
4. **资源监控任务** - 测试资源监控、阈值检查和警报处理

### 编写新测试

如果您要为新功能添加测试，请遵循以下步骤：

1. 在 `tests` 目录中创建对应的测试类
2. 使用 PHPUnit 断言来验证功能
3. 运行 `composer test` 确保所有测试通过 

## 在Hyperf中使用

本客户端支持在Hyperf框架中使用协程进行HTTP请求，提高并发性能。

### 安装Hyperf Guzzle组件

```bash
composer require hyperf/guzzle
```

### 启用协程支持

```php
<?php

use ProxmoxApi\Client;

// 创建客户端实例，启用协程支持
$client = new Client([
    'hostname' => 'your-proxmox-server.com',
    'username' => 'root',
    'password' => 'your-password',
    'realm' => 'pam',
    'port' => 8006,
    'verify' => false,
    'use_coroutine' => true, // 启用协程支持
]);

// 获取节点列表
$nodes = $client->getNodes();
```

启用协程支持后，HTTP请求将使用Swoole协程处理器，可以在协程环境中非阻塞地执行，提高并发性能。 