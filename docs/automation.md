# Proxmox API 自动化任务

本文档介绍了 Proxmox API 包中的自动化任务功能，这些功能可以帮助您自动化管理 Proxmox 虚拟环境中的各种操作。

## 目录

- [自动化任务基类](#自动化任务基类)
- [批量虚拟机操作任务](#批量虚拟机操作任务)
- [批量备份任务](#批量备份任务)
- [资源监控任务](#资源监控任务)
- [使用示例](#使用示例)

## 自动化任务基类

所有自动化任务都继承自 `AutomationTask` 基类，该基类提供了一些通用功能：

- 任务日志记录
- 任务结果收集
- 等待任务完成
- 过滤虚拟机和容器

### 基本用法

```php
use ProxmoxApi\Client;
use ProxmoxApi\Automation\AutomationTask;

class MyCustomTask extends AutomationTask
{
    public function execute(): array
    {
        $this->log("开始执行自定义任务");
        
        // 执行任务逻辑
        
        $this->log("任务完成");
        return $this->results;
    }
}

$client = new Client([/* 配置 */]);
$task = new MyCustomTask($client, [/* 任务配置 */]);
$results = $task->execute();

// 获取任务日志
$logs = $task->getLogs();
```

## 批量虚拟机操作任务

`BatchVMOperationTask` 类用于批量执行虚拟机操作，如启动、停止、重启、克隆等。

### 支持的操作

- `ACTION_START`: 启动虚拟机
- `ACTION_STOP`: 停止虚拟机
- `ACTION_REBOOT`: 重启虚拟机
- `ACTION_SUSPEND`: 暂停虚拟机
- `ACTION_RESUME`: 恢复虚拟机
- `ACTION_BACKUP`: 备份虚拟机
- `ACTION_SNAPSHOT`: 创建快照
- `ACTION_CLONE`: 克隆虚拟机
- `ACTION_DELETE`: 删除虚拟机
- `ACTION_MIGRATE`: 迁移虚拟机

### 配置选项

| 选项 | 类型 | 默认值 | 描述 |
|------|------|--------|------|
| `action` | string | `ACTION_START` | 要执行的操作 |
| `node` | string | `null` | 节点名称，如果为 null 则在所有节点上执行 |
| `vmids` | array | `[]` | 要操作的虚拟机 ID 列表 |
| `filters` | array | `[]` | 虚拟机过滤条件 |
| `params` | array | `[]` | 操作参数 |
| `parallel` | bool | `false` | 是否并行执行 |
| `max_parallel` | int | `5` | 最大并行数量 |
| `continue_on_error` | bool | `true` | 出错时是否继续执行 |
| `timeout` | int | `300` | 任务超时时间（秒） |

### 示例

```php
use ProxmoxApi\Automation\BatchVMOperationTask;

// 批量启动虚拟机
$task = new BatchVMOperationTask($client, [
    'action' => BatchVMOperationTask::ACTION_START,
    'node' => 'pve',
    'vmids' => [100, 101, 102],
    'parallel' => true,
]);

$results = $task->execute();
```

## 批量备份任务

`BatchBackupTask` 类用于批量备份虚拟机和容器，支持创建定时备份计划和清理旧备份。

### 配置选项

| 选项 | 类型 | 默认值 | 描述 |
|------|------|--------|------|
| `node` | string | `null` | 节点名称，如果为 null 则在所有节点上执行 |
| `vmids` | array | `[]` | 要备份的虚拟机 ID 列表 |
| `filters` | array | `[]` | 虚拟机过滤条件 |
| `all` | bool | `false` | 是否备份所有虚拟机 |
| `storage` | string | `'local'` | 存储名称 |
| `mode` | string | `'snapshot'` | 备份模式 |
| `compress` | string | `'zstd'` | 压缩方式 |
| `remove` | int | `0` | 是否删除旧备份 |
| `schedule` | string | `null` | 定时计划（Cron 表达式） |
| `max_backups` | int | `null` | 最大备份数量 |
| `exclude_vms` | array | `[]` | 排除的虚拟机 ID 列表 |
| `mail_notification` | string | `null` | 邮件通知方式 |
| `mail_to` | string | `null` | 通知邮箱 |
| `timeout` | int | `3600` | 任务超时时间（秒） |

### 主要方法

- `execute()`: 执行备份任务
- `createSchedule()`: 创建定时备份计划
- `cleanupOldBackups(int $keepCount)`: 清理旧备份，保留指定数量的最新备份

### 示例

```php
use ProxmoxApi\Automation\BatchBackupTask;

// 批量备份虚拟机
$task = new BatchBackupTask($client, [
    'node' => 'pve',
    'vmids' => [100, 101, 102],
    'storage' => 'local',
    'mode' => 'snapshot',
]);

$results = $task->execute();

// 创建定时备份计划
$scheduleTask = new BatchBackupTask($client, [
    'node' => 'pve',
    'vmids' => [100, 101, 102],
    'storage' => 'local',
    'schedule' => '0 2 * * *', // 每天凌晨2点
]);

$result = $scheduleTask->createSchedule();

// 清理旧备份
$cleanupTask = new BatchBackupTask($client);
$results = $cleanupTask->cleanupOldBackups(5); // 保留最新的5个备份
```

## 资源监控任务

`ResourceMonitorTask` 类用于监控虚拟机的资源使用情况，支持设置阈值和警报处理。

### 资源类型

- `RESOURCE_CPU`: CPU 使用率
- `RESOURCE_MEMORY`: 内存使用率
- `RESOURCE_DISK`: 磁盘使用率
- `RESOURCE_NETWORK`: 网络使用率
- `RESOURCE_ALL`: 所有资源

### 配置选项

| 选项 | 类型 | 默认值 | 描述 |
|------|------|--------|------|
| `node` | string | `null` | 节点名称，如果为 null 则在所有节点上执行 |
| `vmids` | array | `[]` | 要监控的虚拟机 ID 列表 |
| `filters` | array | `[]` | 虚拟机过滤条件 |
| `resources` | array | `[RESOURCE_ALL]` | 要监控的资源类型 |
| `threshold_cpu` | int | `80` | CPU 使用率阈值（百分比） |
| `threshold_memory` | int | `80` | 内存使用率阈值（百分比） |
| `threshold_disk` | int | `80` | 磁盘使用率阈值（百分比） |
| `threshold_network` | int | `null` | 网络使用率阈值（字节/秒） |
| `timeframe` | int | `3600` | 监控时间范围（秒） |
| `interval` | int | `60` | 采样间隔（秒） |
| `samples` | int | `10` | 采样数量 |
| `alert_on_threshold` | bool | `true` | 是否在超过阈值时发出警报 |
| `alert_handlers` | array | `[]` | 警报处理器列表 |

### 警报处理器

资源监控任务支持添加自定义警报处理器，用于处理资源使用超过阈值的情况。内置了几种常用的警报处理器：

- `createEmailAlertHandler()`: 创建邮件警报处理器
- `createLogAlertHandler()`: 创建日志警报处理器
- `createAutoScaleHandler()`: 创建自动扩容处理器

### 示例

```php
use ProxmoxApi\Automation\ResourceMonitorTask;

// 监控虚拟机资源
$task = new ResourceMonitorTask($client, [
    'node' => 'pve',
    'vmids' => [100],
    'resources' => [
        ResourceMonitorTask::RESOURCE_CPU,
        ResourceMonitorTask::RESOURCE_MEMORY,
    ],
    'threshold_cpu' => 80,
    'threshold_memory' => 80,
    'samples' => 5,
    'interval' => 30,
]);

// 添加邮件警报处理器
$task->addAlertHandler(
    ResourceMonitorTask::createEmailAlertHandler('admin@example.com')
);

// 添加自动扩容处理器
$task->addAlertHandler(
    ResourceMonitorTask::createAutoScaleHandler([
        'cpu_increment' => 1,
        'memory_increment' => 1024, // MB
        'max_cpu' => 4,
        'max_memory' => 8192, // MB
    ])
);

$results = $task->execute();
```

## 使用示例

更多详细示例请参考 [examples/automation-examples.php](../examples/automation-examples.php) 文件。

### 批量启动虚拟机

```php
$task = new BatchVMOperationTask($client, [
    'action' => BatchVMOperationTask::ACTION_START,
    'node' => 'pve',
    'vmids' => [100, 101, 102],
]);

$results = $task->execute();
```

### 按状态过滤虚拟机并执行操作

```php
$task = new BatchVMOperationTask($client, [
    'action' => BatchVMOperationTask::ACTION_STOP,
    'node' => 'pve',
    'filters' => [
        'status' => 'running',
        'name' => 'test-*', // 支持通配符匹配
    ],
]);

$results = $task->execute();
```

### 创建定时备份计划

```php
$task = new BatchBackupTask($client, [
    'node' => 'pve',
    'vmids' => [100, 101, 102],
    'storage' => 'local',
    'schedule' => '0 2 * * *', // 每天凌晨2点
    'max_backups' => 5,
    'mail_notification' => 'always',
    'mail_to' => 'admin@example.com',
]);

$result = $task->createSchedule();
```

### 自定义警报处理器

```php
$task = new ResourceMonitorTask($client, [
    'node' => 'pve',
    'vmids' => [100],
    'resources' => [ResourceMonitorTask::RESOURCE_ALL],
]);

// 添加自定义警报处理器
$task->addAlertHandler(function (array $alerts, array $vm, ResourceMonitorTask $task) {
    if (empty($alerts)) {
        return;
    }
    
    echo "收到虚拟机 {$vm['vmid']} 的警报:\n";
    foreach ($alerts as $alert) {
        echo "- {$alert['message']}\n";
    }
});

$results = $task->execute();
``` 