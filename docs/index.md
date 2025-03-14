# Proxmox API 客户端文档

这是一个基于PHP 8.0+的Proxmox VE API客户端封装包，提供了简单易用的接口来与Proxmox VE服务器进行交互。

## 安装

使用Composer安装:

```bash
composer require yourname/proxmox-api
```

## 基本用法

### 创建客户端实例

```php
use ProxmoxApi\Client;

$client = new Client([
    'hostname' => 'your-proxmox-server.com',
    'username' => 'root',
    'password' => 'your-password',
    'realm' => 'pam',     // 默认为 'pam'
    'port' => 8006,       // 默认为 8006
    'verify' => true,     // 是否验证SSL证书
    'timeout' => 30,      // 请求超时时间（秒）
]);
```

### 异常处理

```php
use ProxmoxApi\Exception\AuthenticationException;
use ProxmoxApi\Exception\ProxmoxApiException;

try {
    // API调用
} catch (AuthenticationException $e) {
    // 处理认证错误
} catch (ProxmoxApiException $e) {
    // 处理API错误
} catch (\Exception $e) {
    // 处理其他错误
}
```

## API参考

### 节点操作

```php
// 获取节点列表
$nodes = $client->getNodes();

// 获取特定节点信息
$nodeInfo = $client->nodes->getNode('node1');

// 获取节点上的虚拟机列表
$vms = $client->getNodeVMs('node1');

// 获取节点上的容器列表
$containers = $client->nodes->getContainers('node1');
```

### 虚拟机操作

```php
// 获取虚拟机状态
$vmStatus = $client->getVMStatus('node1', 100);

// 启动虚拟机
$client->startVM('node1', 100);

// 停止虚拟机
$client->stopVM('node1', 100);

// 重启虚拟机
$client->nodes->rebootVM('node1', 100);

// 挂起虚拟机
$client->nodes->suspendVM('node1', 100);

// 恢复虚拟机
$client->nodes->resumeVM('node1', 100);

// 创建虚拟机
$client->createVM('node1', [
    'vmid' => 101,
    'name' => 'test-vm',
    'memory' => 1024,
    'cores' => 2,
    'ostype' => 'l26',
    'net0' => 'virtio,bridge=vmbr0',
    'ide2' => 'local:iso/debian-11.0.0-amd64-netinst.iso,media=cdrom',
    'scsihw' => 'virtio-scsi-pci',
    'scsi0' => 'local-lvm:10,format=raw',
    'boot' => 'cdn',
    'bootdisk' => 'scsi0',
]);

// 删除虚拟机
$client->nodes->deleteVM('node1', 101);

// 克隆虚拟机
$client->nodes->cloneVM('node1', 100, [
    'newid' => 102,
    'name' => 'cloned-vm',
]);

// 获取虚拟机配置
$vmConfig = $client->nodes->getVMConfig('node1', 100);

// 更新虚拟机配置
$client->nodes->updateVMConfig('node1', 100, [
    'memory' => 2048,
    'description' => '这是一个测试虚拟机',
]);
```

### 集群操作

```php
// 获取集群状态
$clusterStatus = $client->cluster->getStatus();

// 获取集群资源
$resources = $client->cluster->getResources();

// 获取特定类型的资源
$vmResources = $client->cluster->getResources('vm');

// 获取集群任务
$tasks = $client->cluster->getTasks();

// 获取备份计划
$backupSchedules = $client->cluster->getBackupSchedule();

// 创建备份计划
$client->cluster->createBackupSchedule([
    'id' => 'backup1',
    'starttime' => '00:00',
    'dow' => '1,2,3,4,5',
    'storage' => 'local',
    'mode' => 'snapshot',
    'enabled' => 1,
]);

// 获取高可用性状态
$haStatus = $client->cluster->getHAStatus();

// 获取防火墙规则
$firewallRules = $client->cluster->getFirewallRules();
```

### 存储操作

```php
// 获取存储列表
$storages = $client->storage->getList();

// 创建存储
$client->storage->create([
    'storage' => 'nfs1',
    'type' => 'nfs',
    'server' => '192.168.1.100',
    'export' => '/mnt/data',
    'content' => 'images,iso',
]);

// 获取节点上的存储列表
$nodeStorages = $client->storage->getNodeStorages('node1');

// 获取存储内容
$storageContent = $client->storage->getStorageContent('node1', 'local');

// 获取ISO镜像列表
$isoImages = $client->storage->getISOImages('node1', 'local');

// 获取容器模板列表
$containerTemplates = $client->storage->getContainerTemplates('node1', 'local');

// 获取备份列表
$backups = $client->storage->getBackups('node1', 'local');
```

### 访问控制操作

```php
// 获取用户列表
$users = $client->access->getUsers();

// 创建用户
$client->access->createUser([
    'userid' => 'user1@pam',
    'password' => 'password',
    'email' => 'user1@example.com',
]);

// 获取组列表
$groups = $client->access->getGroups();

// 创建组
$client->access->createGroup([
    'groupid' => 'group1',
]);

// 获取角色列表
$roles = $client->access->getRoles();

// 获取权限列表
$acl = $client->access->getAcl();

// 更新权限
$client->access->updateAcl([
    'path' => '/vms/100',
    'roles' => 'PVEVMAdmin',
    'users' => 'user1@pam',
]);
```

## 高级用法

### 自定义请求

如果您需要访问未封装的API端点，可以使用底层的请求方法：

```php
// GET请求
$response = $client->get('nodes/node1/qemu/100/snapshot');

// POST请求
$response = $client->post('nodes/node1/qemu/100/snapshot', [
    'snapname' => 'snap1',
]);

// PUT请求
$response = $client->put('nodes/node1/qemu/100/config', [
    'memory' => 2048,
]);

// DELETE请求
$response = $client->delete('nodes/node1/qemu/100/snapshot/snap1');
```

### 处理任务

许多Proxmox操作是异步的，会返回一个任务ID。您可以使用以下方法跟踪任务状态：

```php
// 启动虚拟机
$result = $client->startVM('node1', 100);

// 获取任务ID
$taskId = $result['upid'];

// 获取任务状态
$taskStatus = $client->nodes->getTaskStatus('node1', $taskId);

// 获取任务日志
$taskLog = $client->nodes->getTaskLog('node1', $taskId);
```

## 完整示例

请参考 `examples` 目录中的示例文件：

- `basic-usage.php`: 基本用法示例
- `vm-management.php`: 虚拟机管理示例 