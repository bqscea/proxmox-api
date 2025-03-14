<?php

require_once __DIR__ . '/../vendor/autoload.php';

use ProxmoxApi\Client;
use ProxmoxApi\Automation\BatchVMOperationTask;
use ProxmoxApi\Automation\BatchBackupTask;
use ProxmoxApi\Automation\ResourceMonitorTask;

// 创建API客户端
$client = new Client([
    'hostname' => 'your-proxmox-host.example.com',
    'username' => 'root',
    'password' => 'your-password',
    'realm' => 'pam',
]);

// 示例1: 批量启动虚拟机
echo "=== 示例1: 批量启动虚拟机 ===\n";
$startTask = new BatchVMOperationTask($client, [
    'action' => BatchVMOperationTask::ACTION_START,
    'node' => 'pve',
    'vmids' => [100, 101, 102],
    'parallel' => true,
    'max_parallel' => 3,
]);

try {
    $results = $startTask->execute();
    echo "任务执行结果:\n";
    print_r($results);
    
    echo "任务日志:\n";
    foreach ($startTask->getLogs() as $log) {
        echo "[" . date('Y-m-d H:i:s', $log['timestamp']) . "] [{$log['level']}] {$log['message']}\n";
    }
} catch (\Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}

// 示例2: 批量备份虚拟机
echo "\n=== 示例2: 批量备份虚拟机 ===\n";
$backupTask = new BatchBackupTask($client, [
    'node' => 'pve',
    'vmids' => [100, 101, 102],
    'storage' => 'local',
    'mode' => 'snapshot',
    'compress' => 'zstd',
]);

try {
    $results = $backupTask->execute();
    echo "任务执行结果:\n";
    print_r($results);
    
    echo "任务日志:\n";
    foreach ($backupTask->getLogs() as $log) {
        echo "[" . date('Y-m-d H:i:s', $log['timestamp']) . "] [{$log['level']}] {$log['message']}\n";
    }
} catch (\Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}

// 示例3: 创建定时备份计划
echo "\n=== 示例3: 创建定时备份计划 ===\n";
$scheduleTask = new BatchBackupTask($client, [
    'node' => 'pve',
    'vmids' => [100, 101, 102],
    'storage' => 'local',
    'mode' => 'snapshot',
    'compress' => 'zstd',
    'schedule' => '0 2 * * *', // 每天凌晨2点
    'max_backups' => 5,
    'mail_notification' => 'always',
    'mail_to' => 'admin@example.com',
]);

try {
    $result = $scheduleTask->createSchedule();
    echo "计划创建结果:\n";
    print_r($result);
} catch (\Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}

// 示例4: 清理旧备份
echo "\n=== 示例4: 清理旧备份 ===\n";
$cleanupTask = new BatchBackupTask($client);

try {
    $results = $cleanupTask->cleanupOldBackups(3); // 保留最新的3个备份
    echo "清理结果:\n";
    print_r($results);
    
    echo "任务日志:\n";
    foreach ($cleanupTask->getLogs() as $log) {
        echo "[" . date('Y-m-d H:i:s', $log['timestamp']) . "] [{$log['level']}] {$log['message']}\n";
    }
} catch (\Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}

// 示例5: 资源监控
echo "\n=== 示例5: 资源监控 ===\n";
$monitorTask = new ResourceMonitorTask($client, [
    'node' => 'pve',
    'vmids' => [100],
    'resources' => [
        ResourceMonitorTask::RESOURCE_CPU,
        ResourceMonitorTask::RESOURCE_MEMORY,
    ],
    'threshold_cpu' => 80,
    'threshold_memory' => 80,
    'samples' => 3,
    'interval' => 10,
]);

// 添加邮件警报处理器
$monitorTask->addAlertHandler(
    ResourceMonitorTask::createEmailAlertHandler('admin@example.com', '虚拟机资源警报')
);

// 添加日志警报处理器
$monitorTask->addAlertHandler(
    ResourceMonitorTask::createLogAlertHandler('/var/log/proxmox-alerts.log')
);

// 添加自动扩容处理器
$monitorTask->addAlertHandler(
    ResourceMonitorTask::createAutoScaleHandler([
        'cpu_increment' => 1,
        'memory_increment' => 1024, // MB
        'max_cpu' => 4,
        'max_memory' => 8192, // MB
    ])
);

try {
    $results = $monitorTask->execute();
    echo "监控结果:\n";
    print_r($results);
    
    echo "任务日志:\n";
    foreach ($monitorTask->getLogs() as $log) {
        echo "[" . date('Y-m-d H:i:s', $log['timestamp']) . "] [{$log['level']}] {$log['message']}\n";
    }
} catch (\Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}

// 示例6: 批量克隆虚拟机
echo "\n=== 示例6: 批量克隆虚拟机 ===\n";
$cloneTask = new BatchVMOperationTask($client, [
    'action' => BatchVMOperationTask::ACTION_CLONE,
    'node' => 'pve',
    'vmids' => [200, 201, 202], // 目标虚拟机ID
    'params' => [
        'source_vmid' => 100, // 源虚拟机ID
        'full' => 1,
        'description' => '从模板克隆的虚拟机',
    ],
]);

try {
    $results = $cloneTask->execute();
    echo "克隆结果:\n";
    print_r($results);
    
    echo "任务日志:\n";
    foreach ($cloneTask->getLogs() as $log) {
        echo "[" . date('Y-m-d H:i:s', $log['timestamp']) . "] [{$log['level']}] {$log['message']}\n";
    }
} catch (\Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}

// 示例7: 批量创建快照
echo "\n=== 示例7: 批量创建快照 ===\n";
$snapshotTask = new BatchVMOperationTask($client, [
    'action' => BatchVMOperationTask::ACTION_SNAPSHOT,
    'node' => 'pve',
    'vmids' => [100, 101, 102],
    'params' => [
        'snapname' => 'backup-' . date('Y-m-d'),
        'description' => '自动创建的备份快照',
        'include_ram' => false,
    ],
]);

try {
    $results = $snapshotTask->execute();
    echo "快照创建结果:\n";
    print_r($results);
    
    echo "任务日志:\n";
    foreach ($snapshotTask->getLogs() as $log) {
        echo "[" . date('Y-m-d H:i:s', $log['timestamp']) . "] [{$log['level']}] {$log['message']}\n";
    }
} catch (\Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}

// 示例8: 按状态过滤虚拟机并执行操作
echo "\n=== 示例8: 按状态过滤虚拟机并执行操作 ===\n";
$filterTask = new BatchVMOperationTask($client, [
    'action' => BatchVMOperationTask::ACTION_STOP,
    'node' => 'pve',
    'filters' => [
        'status' => 'running',
        'name' => 'test-*', // 支持通配符匹配
    ],
]);

try {
    $results = $filterTask->execute();
    echo "操作结果:\n";
    print_r($results);
    
    echo "任务日志:\n";
    foreach ($filterTask->getLogs() as $log) {
        echo "[" . date('Y-m-d H:i:s', $log['timestamp']) . "] [{$log['level']}] {$log['message']}\n";
    }
} catch (\Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}

// 示例9: 自定义警报处理器
echo "\n=== 示例9: 自定义警报处理器 ===\n";
$customMonitorTask = new ResourceMonitorTask($client, [
    'node' => 'pve',
    'vmids' => [100],
    'resources' => [ResourceMonitorTask::RESOURCE_ALL],
    'samples' => 2,
    'interval' => 10,
]);

// 添加自定义警报处理器
$customMonitorTask->addAlertHandler(function (array $alerts, array $vm, ResourceMonitorTask $task) {
    if (empty($alerts)) {
        return;
    }
    
    echo "收到虚拟机 {$vm['vmid']} 的警报:\n";
    foreach ($alerts as $alert) {
        echo "- {$alert['message']}\n";
        
        // 根据不同资源类型执行不同操作
        switch ($alert['resource']) {
            case ResourceMonitorTask::RESOURCE_CPU:
                echo "  CPU使用率过高，可能需要增加CPU核心\n";
                break;
                
            case ResourceMonitorTask::RESOURCE_MEMORY:
                echo "  内存使用率过高，可能需要增加内存\n";
                break;
                
            case ResourceMonitorTask::RESOURCE_DISK:
                echo "  磁盘使用率过高，可能需要增加磁盘空间\n";
                break;
                
            case ResourceMonitorTask::RESOURCE_NETWORK:
                echo "  网络使用率过高，可能需要限制带宽\n";
                break;
        }
    }
});

try {
    $results = $customMonitorTask->execute();
    echo "监控结果:\n";
    print_r($results);
} catch (\Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
} 