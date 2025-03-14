<?php

namespace Tests\Automation;

use PHPUnit\Framework\TestCase;
use ProxmoxApi\Client;
use ProxmoxApi\Automation\BatchVMOperationTask;
use ProxmoxApi\Automation\BatchBackupTask;
use ProxmoxApi\Automation\ResourceMonitorTask;

/**
 * 性能测试
 * 
 * 这个测试类专门测试自动化任务的性能表现
 */
class PerformanceTest extends TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|Client
     */
    private $clientMock;
    
    /**
     * @var array 测试用的虚拟机数据
     */
    private $testVms;
    
    /**
     * @var int 测试虚拟机数量
     */
    private $vmCount = 100;

    protected function setUp(): void
    {
        $this->clientMock = $this->createMock(Client::class);
        
        // 创建测试虚拟机数据
        $this->testVms = [];
        for ($i = 1; $i <= $this->vmCount; $i++) {
            $this->testVms[] = [
                'vmid' => 100 + $i,
                'name' => "test-vm-{$i}",
                'status' => ($i % 3 == 0) ? 'stopped' : 'running',
                'node' => 'node' . (($i % 3) + 1),
                'type' => ($i % 5 == 0) ? 'lxc' : 'qemu',
                'cpu' => rand(1, 8),
                'maxcpu' => 8,
                'mem' => rand(512, 8192),
                'maxmem' => 8192,
                'disk' => rand(10, 100),
                'maxdisk' => 100,
                'uptime' => ($i % 3 == 0) ? 0 : rand(3600, 86400 * 30),
                'netin' => rand(1024, 10485760),
                'netout' => rand(1024, 10485760),
            ];
        }
    }

    /**
     * 测试并行与串行批量操作的性能差异
     */
    public function testParallelVsSequentialPerformance()
    {
        // 模拟客户端方法
        $this->clientMock->method('getNodes')
            ->willReturn(['node1', 'node2', 'node3']);
            
        $this->clientMock->method('getVMs')
            ->willReturnCallback(function ($node) {
                return array_filter($this->testVms, function ($vm) use ($node) {
                    return $vm['node'] === $node && $vm['type'] === 'qemu';
                });
            });
            
        $this->clientMock->method('startVM')
            ->willReturnCallback(function ($node, $vmid) {
                // 模拟API调用延迟
                usleep(10000); // 10ms
                return ['success' => true, 'data' => 'UPID:' . uniqid()];
            });
            
        $this->clientMock->method('waitForTask')
            ->willReturn(['status' => 'ok']);
            
        // 测试串行执行性能
        $sequentialTask = new BatchVMOperationTask($this->clientMock, [
            'operation' => BatchVMOperationTask::OPERATION_START,
            'parallel' => false,
            'all' => true
        ]);
        
        $startTime = microtime(true);
        $sequentialTask->execute();
        $sequentialTime = microtime(true) - $startTime;
        
        // 测试并行执行性能
        $parallelTask = new BatchVMOperationTask($this->clientMock, [
            'operation' => BatchVMOperationTask::OPERATION_START,
            'parallel' => true,
            'all' => true
        ]);
        
        $startTime = microtime(true);
        $parallelTask->execute();
        $parallelTime = microtime(true) - $startTime;
        
        // 验证并行执行比串行执行快
        $this->assertLessThan($sequentialTime, $parallelTime, '并行执行应该比串行执行快');
        
        // 计算加速比
        $speedup = $sequentialTime / $parallelTime;
        $this->assertGreaterThan(1.5, $speedup, '并行执行应该至少比串行执行快50%');
        
        // 记录性能数据
        $this->addToAssertionCount(1);
        echo PHP_EOL . "性能测试结果：" . PHP_EOL;
        echo "串行执行时间: {$sequentialTime}秒" . PHP_EOL;
        echo "并行执行时间: {$parallelTime}秒" . PHP_EOL;
        echo "加速比: {$speedup}x" . PHP_EOL;
    }

    /**
     * 测试批量备份任务的性能
     */
    public function testBatchBackupPerformance()
    {
        // 模拟客户端方法
        $this->clientMock->method('getNodes')
            ->willReturn(['node1', 'node2', 'node3']);
            
        $this->clientMock->method('getVMs')
            ->willReturnCallback(function ($node) {
                return array_filter($this->testVms, function ($vm) use ($node) {
                    return $vm['node'] === $node && $vm['type'] === 'qemu';
                });
            });
            
        $this->clientMock->method('backupVM')
            ->willReturnCallback(function ($node, $vmid, $params) {
                // 模拟API调用延迟
                usleep(50000); // 50ms
                return ['success' => true, 'data' => 'UPID:' . uniqid()];
            });
            
        $this->clientMock->method('waitForTask')
            ->willReturn(['status' => 'ok']);
            
        // 测试不同批量大小的性能
        $batchSizes = [1, 5, 10, 20];
        $results = [];
        
        foreach ($batchSizes as $batchSize) {
            $backupTask = new BatchBackupTask($this->clientMock, [
                'all' => true,
                'storage' => 'local',
                'mode' => 'snapshot',
                'compress' => 'zstd',
                'batch_size' => $batchSize
            ]);
            
            $startTime = microtime(true);
            $backupTask->execute();
            $executionTime = microtime(true) - $startTime;
            
            $results[$batchSize] = $executionTime;
        }
        
        // 验证较大的批量大小通常会更快
        $this->assertLessThan($results[1], $results[10], '批量大小为10的执行应该比批量大小为1的执行快');
        
        // 记录性能数据
        $this->addToAssertionCount(1);
        echo PHP_EOL . "批量备份性能测试结果：" . PHP_EOL;
        foreach ($results as $batchSize => $time) {
            echo "批量大小 {$batchSize}: {$time}秒" . PHP_EOL;
        }
        
        // 计算最佳批量大小
        $bestBatchSize = array_keys($results, min($results))[0];
        echo "最佳批量大小: {$bestBatchSize}" . PHP_EOL;
    }

    /**
     * 测试资源监控任务的性能
     */
    public function testResourceMonitorPerformance()
    {
        // 模拟客户端方法
        $this->clientMock->method('getNodes')
            ->willReturn(['node1', 'node2', 'node3']);
            
        $this->clientMock->method('getVMs')
            ->willReturnCallback(function ($node) {
                return array_filter($this->testVms, function ($vm) use ($node) {
                    return $vm['node'] === $node && $vm['type'] === 'qemu';
                });
            });
            
        $this->clientMock->method('getVMRRDData')
            ->willReturnCallback(function ($node, $vmid, $resource) {
                // 模拟API调用延迟
                usleep(20000); // 20ms
                
                // 模拟RRD数据
                $data = [];
                $time = time() - 3600;
                for ($i = 0; $i < 60; $i++) {
                    $data[] = [
                        'time' => $time + ($i * 60),
                        'value' => rand(10, 90) / 100
                    ];
                }
                return $data;
            });
            
        // 测试不同采样间隔的性能
        $intervals = [5, 15, 30, 60];
        $results = [];
        
        foreach ($intervals as $interval) {
            $monitorTask = new ResourceMonitorTask($this->clientMock, [
                'all' => true,
                'resources' => [ResourceMonitorTask::RESOURCE_CPU, ResourceMonitorTask::RESOURCE_MEMORY],
                'interval' => $interval,
                'samples' => 10,
                'thresholds' => [
                    ResourceMonitorTask::RESOURCE_CPU => 0.8,
                    ResourceMonitorTask::RESOURCE_MEMORY => 0.8
                ]
            ]);
            
            $startTime = microtime(true);
            $monitorTask->execute();
            $executionTime = microtime(true) - $startTime;
            
            $results[$interval] = $executionTime;
        }
        
        // 验证较长的采样间隔通常会更快
        $this->assertLessThan($results[5], $results[60], '采样间隔为60的执行应该比采样间隔为5的执行快');
        
        // 记录性能数据
        $this->addToAssertionCount(1);
        echo PHP_EOL . "资源监控性能测试结果：" . PHP_EOL;
        foreach ($results as $interval => $time) {
            echo "采样间隔 {$interval}秒: {$time}秒" . PHP_EOL;
        }
    }

    /**
     * 测试大规模虚拟机环境下的性能
     */
    public function testLargeScalePerformance()
    {
        // 创建大规模测试数据
        $largeScaleVms = [];
        for ($i = 1; $i <= 1000; $i++) {
            $largeScaleVms[] = [
                'vmid' => 1000 + $i,
                'name' => "large-vm-{$i}",
                'status' => ($i % 3 == 0) ? 'stopped' : 'running',
                'node' => 'node' . (($i % 10) + 1),
                'type' => ($i % 5 == 0) ? 'lxc' : 'qemu',
            ];
        }
        
        // 模拟客户端方法
        $this->clientMock->method('getNodes')
            ->willReturn(array_map(function ($i) { return "node{$i}"; }, range(1, 10)));
            
        $this->clientMock->method('getVMs')
            ->willReturnCallback(function ($node) use ($largeScaleVms) {
                return array_filter($largeScaleVms, function ($vm) use ($node) {
                    return $vm['node'] === $node && $vm['type'] === 'qemu';
                });
            });
            
        // 测试过滤性能
        $filterCriteria = [
            'status' => 'running',
            'name' => 'large-vm-*'
        ];
        
        $task = new BatchVMOperationTask($this->clientMock, [
            'operation' => BatchVMOperationTask::OPERATION_STOP,
            'filters' => $filterCriteria
        ]);
        
        $startTime = microtime(true);
        $reflection = new \ReflectionClass($task);
        $getTargetVMsMethod = $reflection->getMethod('getTargetVMs');
        $getTargetVMsMethod->setAccessible(true);
        
        $targetVMs = $getTargetVMsMethod->invoke($task);
        $filterTime = microtime(true) - $startTime;
        
        // 验证过滤结果
        $this->assertLessThan(count($largeScaleVms), count($targetVMs), '过滤后的虚拟机数量应该小于总数');
        
        // 记录性能数据
        $this->addToAssertionCount(1);
        echo PHP_EOL . "大规模环境过滤性能测试结果：" . PHP_EOL;
        echo "虚拟机总数: " . count($largeScaleVms) . PHP_EOL;
        echo "过滤后虚拟机数: " . count($targetVMs) . PHP_EOL;
        echo "过滤耗时: {$filterTime}秒" . PHP_EOL;
    }

    /**
     * 测试内存使用情况
     */
    public function testMemoryUsage()
    {
        // 记录初始内存使用
        $initialMemory = memory_get_usage();
        
        // 模拟客户端方法
        $this->clientMock->method('getNodes')
            ->willReturn(['node1', 'node2', 'node3']);
            
        $this->clientMock->method('getVMs')
            ->willReturnCallback(function ($node) {
                return array_filter($this->testVms, function ($vm) use ($node) {
                    return $vm['node'] === $node && $vm['type'] === 'qemu';
                });
            });
            
        // 执行批量操作任务
        $task = new BatchVMOperationTask($this->clientMock, [
            'operation' => BatchVMOperationTask::OPERATION_START,
            'all' => true
        ]);
        
        $task->execute();
        
        // 记录任务执行后的内存使用
        $afterTaskMemory = memory_get_usage();
        $memoryUsed = $afterTaskMemory - $initialMemory;
        
        // 记录内存使用数据
        $this->addToAssertionCount(1);
        echo PHP_EOL . "内存使用测试结果：" . PHP_EOL;
        echo "初始内存使用: " . $this->formatBytes($initialMemory) . PHP_EOL;
        echo "任务执行后内存使用: " . $this->formatBytes($afterTaskMemory) . PHP_EOL;
        echo "内存增长: " . $this->formatBytes($memoryUsed) . PHP_EOL;
        
        // 验证内存使用在合理范围内
        $this->assertLessThan(10 * 1024 * 1024, $memoryUsed, '内存增长应该小于10MB');
    }
    
    /**
     * 格式化字节数为人类可读格式
     *
     * @param int $bytes 字节数
     * @return string 格式化后的字符串
     */
    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
} 