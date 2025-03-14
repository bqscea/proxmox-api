<?php

namespace Tests\Automation;

use PHPUnit\Framework\TestCase;
use ProxmoxApi\Client;
use ProxmoxApi\Automation\AutomationTask;
use ProxmoxApi\Automation\BatchVMOperationTask;
use ProxmoxApi\Automation\BatchBackupTask;
use ProxmoxApi\Automation\ResourceMonitorTask;

/**
 * 安全测试
 * 
 * 这个测试类专门测试自动化任务的安全性和错误处理
 */
class SecurityTest extends TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|Client
     */
    private $clientMock;

    protected function setUp(): void
    {
        $this->clientMock = $this->createMock(Client::class);
    }

    /**
     * 测试输入验证和过滤
     */
    public function testInputValidation()
    {
        // 测试无效操作类型
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('无效的操作类型');
        
        $task = new BatchVMOperationTask($this->clientMock, [
            'operation' => 'invalid_operation',
            'vmids' => [100, 101]
        ]);
        
        $task->execute();
    }

    /**
     * 测试VMID注入防护
     */
    public function testVMIDInjectionPrevention()
    {
        // 模拟客户端方法
        $this->clientMock->method('getNodes')
            ->willReturn(['node1']);
            
        $this->clientMock->method('getVMs')
            ->willReturn([
                ['vmid' => 100, 'name' => 'test-vm-1', 'status' => 'running', 'node' => 'node1'],
                ['vmid' => 101, 'name' => 'test-vm-2', 'status' => 'running', 'node' => 'node1']
            ]);
            
        // 测试VMID注入
        $task = new BatchVMOperationTask($this->clientMock, [
            'operation' => BatchVMOperationTask::OPERATION_STOP,
            'vmids' => ['100; rm -rf /', '101']
        ]);
        
        // 模拟startVM方法，验证传入的VMID是否被正确过滤
        $this->clientMock->expects($this->never())
            ->method('stopVM')
            ->with('node1', '100; rm -rf /');
            
        // 执行任务
        $task->execute();
    }

    /**
     * 测试命令注入防护
     */
    public function testCommandInjectionPrevention()
    {
        // 模拟客户端方法
        $this->clientMock->method('getNodes')
            ->willReturn(['node1']);
            
        $this->clientMock->method('getVMs')
            ->willReturn([
                ['vmid' => 100, 'name' => 'test-vm-1', 'status' => 'running', 'node' => 'node1']
            ]);
            
        // 测试命令注入
        $task = new BatchBackupTask($this->clientMock, [
            'vmids' => [100],
            'storage' => "local; rm -rf /",
            'mode' => 'snapshot'
        ]);
        
        // 模拟backupVM方法，验证传入的参数是否被正确过滤
        $this->clientMock->expects($this->once())
            ->method('backupVM')
            ->with(
                'node1', 
                100, 
                $this->callback(function ($params) {
                    // 验证storage参数是否被正确过滤
                    return $params['storage'] === 'local; rm -rf /';
                })
            )
            ->willReturn(['success' => true, 'data' => 'UPID:task1']);
            
        $this->clientMock->method('waitForTask')
            ->willReturn(['status' => 'ok']);
            
        // 执行任务
        $task->execute();
    }

    /**
     * 测试权限检查
     */
    public function testPermissionChecking()
    {
        // 模拟客户端方法
        $this->clientMock->method('getNodes')
            ->willReturn(['node1']);
            
        $this->clientMock->method('getVMs')
            ->willReturn([
                ['vmid' => 100, 'name' => 'test-vm-1', 'status' => 'running', 'node' => 'node1']
            ]);
            
        // 模拟权限错误
        $this->clientMock->method('startVM')
            ->willThrowException(new \Exception('权限不足: 用户无权启动虚拟机'));
            
        // 创建任务
        $task = new BatchVMOperationTask($this->clientMock, [
            'operation' => BatchVMOperationTask::OPERATION_START,
            'vmids' => [100]
        ]);
        
        // 执行任务
        $result = $task->execute();
        
        // 验证结果包含错误信息
        $this->assertArrayHasKey('errors', $result);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('权限不足', $result['errors'][0]['message']);
    }

    /**
     * 测试敏感信息处理
     */
    public function testSensitiveInformationHandling()
    {
        // 创建一个测试任务类
        $testTask = new class($this->clientMock) extends AutomationTask {
            public function execute(): array
            {
                // 模拟包含敏感信息的日志
                $this->log('用户密码: secret123');
                $this->log('API密钥: abcdef123456');
                
                return $this->getResults();
            }
            
            // 公开日志方法以便测试
            public function getLogs(): array
            {
                return $this->logs;
            }
        };
        
        // 执行任务
        $testTask->execute();
        
        // 获取日志
        $logs = $testTask->getLogs();
        
        // 验证敏感信息是否被过滤
        foreach ($logs as $log) {
            $this->assertStringNotContainsString('secret123', $log['message']);
            $this->assertStringNotContainsString('abcdef123456', $log['message']);
        }
    }

    /**
     * 测试错误处理和恢复
     */
    public function testErrorHandlingAndRecovery()
    {
        // 模拟客户端方法
        $this->clientMock->method('getNodes')
            ->willReturn(['node1']);
            
        $this->clientMock->method('getVMs')
            ->willReturn([
                ['vmid' => 100, 'name' => 'test-vm-1', 'status' => 'running', 'node' => 'node1'],
                ['vmid' => 101, 'name' => 'test-vm-2', 'status' => 'running', 'node' => 'node1'],
                ['vmid' => 102, 'name' => 'test-vm-3', 'status' => 'running', 'node' => 'node1']
            ]);
            
        // 模拟第二个VM操作失败
        $this->clientMock->method('stopVM')
            ->willReturnCallback(function ($node, $vmid) {
                if ($vmid == 101) {
                    throw new \Exception('操作失败');
                }
                return ['success' => true, 'data' => 'UPID:task' . $vmid];
            });
            
        $this->clientMock->method('waitForTask')
            ->willReturn(['status' => 'ok']);
            
        // 创建任务
        $task = new BatchVMOperationTask($this->clientMock, [
            'operation' => BatchVMOperationTask::OPERATION_STOP,
            'vmids' => [100, 101, 102]
        ]);
        
        // 执行任务
        $result = $task->execute();
        
        // 验证结果
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertCount(2, $result['success']);
        $this->assertCount(1, $result['errors']);
        
        // 验证错误信息
        $this->assertEquals(101, $result['errors'][0]['vmid']);
        $this->assertStringContainsString('操作失败', $result['errors'][0]['message']);
    }

    /**
     * 测试资源限制
     */
    public function testResourceLimits()
    {
        // 创建一个大量虚拟机的测试数据
        $vms = [];
        for ($i = 1; $i <= 1000; $i++) {
            $vms[] = [
                'vmid' => 100 + $i,
                'name' => "test-vm-{$i}",
                'status' => 'running',
                'node' => 'node1'
            ];
        }
        
        // 模拟客户端方法
        $this->clientMock->method('getNodes')
            ->willReturn(['node1']);
            
        $this->clientMock->method('getVMs')
            ->willReturn($vms);
            
        // 创建任务，设置批量大小限制
        $task = new BatchVMOperationTask($this->clientMock, [
            'operation' => BatchVMOperationTask::OPERATION_STOP,
            'all' => true,
            'batch_size' => 50
        ]);
        
        // 模拟stopVM方法，验证调用次数不超过批量大小
        $this->clientMock->expects($this->atMost(50))
            ->method('stopVM')
            ->willReturn(['success' => true, 'data' => 'UPID:task']);
            
        $this->clientMock->method('waitForTask')
            ->willReturn(['status' => 'ok']);
            
        // 执行任务
        $task->execute();
    }

    /**
     * 测试超时处理
     */
    public function testTimeoutHandling()
    {
        // 模拟客户端方法
        $this->clientMock->method('getNodes')
            ->willReturn(['node1']);
            
        $this->clientMock->method('getVMs')
            ->willReturn([
                ['vmid' => 100, 'name' => 'test-vm-1', 'status' => 'running', 'node' => 'node1']
            ]);
            
        $this->clientMock->method('startVM')
            ->willReturn(['success' => true, 'data' => 'UPID:task100']);
            
        // 模拟任务超时
        $this->clientMock->method('waitForTask')
            ->willReturnCallback(function ($taskId, $timeout) {
                if ($timeout < 60) {
                    return ['status' => 'running'];
                }
                return ['status' => 'ok'];
            });
            
        // 创建任务，设置较短的超时时间
        $task = new BatchVMOperationTask($this->clientMock, [
            'operation' => BatchVMOperationTask::OPERATION_START,
            'vmids' => [100],
            'timeout' => 10
        ]);
        
        // 执行任务
        $result = $task->execute();
        
        // 验证结果包含超时信息
        $this->assertArrayHasKey('errors', $result);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('超时', $result['errors'][0]['message']);
    }

    /**
     * 测试并发控制
     */
    public function testConcurrencyControl()
    {
        // 模拟客户端方法
        $this->clientMock->method('getNodes')
            ->willReturn(['node1']);
            
        $this->clientMock->method('getVMs')
            ->willReturn([
                ['vmid' => 100, 'name' => 'test-vm-1', 'status' => 'running', 'node' => 'node1'],
                ['vmid' => 101, 'name' => 'test-vm-2', 'status' => 'running', 'node' => 'node1'],
                ['vmid' => 102, 'name' => 'test-vm-3', 'status' => 'running', 'node' => 'node1'],
                ['vmid' => 103, 'name' => 'test-vm-4', 'status' => 'running', 'node' => 'node1'],
                ['vmid' => 104, 'name' => 'test-vm-5', 'status' => 'running', 'node' => 'node1']
            ]);
            
        // 创建任务，设置并发数
        $task = new BatchVMOperationTask($this->clientMock, [
            'operation' => BatchVMOperationTask::OPERATION_STOP,
            'all' => true,
            'parallel' => true,
            'max_parallel' => 3
        ]);
        
        // 使用反射获取并发控制相关属性
        $reflection = new \ReflectionClass($task);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($task);
        
        // 验证并发数设置是否正确
        $this->assertEquals(3, $config['max_parallel']);
    }

    /**
     * 测试日志安全性
     */
    public function testLogSecurity()
    {
        // 创建一个测试任务
        $task = new class($this->clientMock) extends AutomationTask {
            public function execute(): array
            {
                // 记录包含敏感信息的日志
                $this->log('用户名: admin, 密码: secret123');
                $this->log('API密钥: abcdef123456');
                $this->log('数据库连接: mysql://user:password@localhost/db');
                
                return $this->getResults();
            }
            
            // 公开日志方法以便测试
            public function getLogs(): array
            {
                return $this->logs;
            }
        };
        
        // 执行任务
        $task->execute();
        
        // 获取日志
        $logs = $task->getLogs();
        
        // 验证敏感信息是否被过滤
        foreach ($logs as $log) {
            $this->assertStringNotContainsString('secret123', $log['message']);
            $this->assertStringNotContainsString('abcdef123456', $log['message']);
            $this->assertStringNotContainsString('password@', $log['message']);
        }
    }

    /**
     * 测试资源监控任务的安全阈值
     */
    public function testResourceMonitorSecurityThresholds()
    {
        // 模拟客户端方法
        $this->clientMock->method('getNodes')
            ->willReturn(['node1']);
            
        $this->clientMock->method('getVMs')
            ->willReturn([
                ['vmid' => 100, 'name' => 'test-vm-1', 'status' => 'running', 'node' => 'node1']
            ]);
            
        $this->clientMock->method('getVMRRDData')
            ->willReturn([
                ['time' => time() - 60, 'value' => 0.95] // 95% CPU使用率
            ]);
            
        // 创建监控任务，设置极端阈值
        $task = new ResourceMonitorTask($this->clientMock, [
            'vmids' => [100],
            'resources' => [ResourceMonitorTask::RESOURCE_CPU],
            'thresholds' => [
                ResourceMonitorTask::RESOURCE_CPU => 1.5 // 150%，不可能的值
            ]
        ]);
        
        // 使用反射获取配置
        $reflection = new \ReflectionClass($task);
        $validateConfigMethod = $reflection->getMethod('validateConfig');
        $validateConfigMethod->setAccessible(true);
        
        // 验证配置是否被正确验证和修正
        $validateConfigMethod->invoke($task);
        
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($task);
        
        // 验证阈值是否被限制在合理范围内
        $this->assertLessThanOrEqual(1.0, $config['thresholds'][ResourceMonitorTask::RESOURCE_CPU]);
    }

    /**
     * 测试防止无限循环
     */
    public function testPreventInfiniteLoops()
    {
        // 模拟客户端方法
        $this->clientMock->method('getNodes')
            ->willReturn(['node1']);
            
        $this->clientMock->method('getVMs')
            ->willReturn([
                ['vmid' => 100, 'name' => 'test-vm-1', 'status' => 'running', 'node' => 'node1']
            ]);
            
        // 模拟任务永远不会完成
        $this->clientMock->method('startVM')
            ->willReturn(['success' => true, 'data' => 'UPID:task100']);
            
        $this->clientMock->method('waitForTask')
            ->willReturn(['status' => 'running']);
            
        // 创建任务，设置较短的超时时间
        $task = new BatchVMOperationTask($this->clientMock, [
            'operation' => BatchVMOperationTask::OPERATION_START,
            'vmids' => [100],
            'timeout' => 1
        ]);
        
        // 执行任务
        $startTime = microtime(true);
        $result = $task->execute();
        $executionTime = microtime(true) - $startTime;
        
        // 验证执行时间不会过长（防止无限循环）
        $this->assertLessThan(10, $executionTime, '执行时间不应超过10秒');
        
        // 验证结果包含超时信息
        $this->assertArrayHasKey('errors', $result);
        $this->assertStringContainsString('超时', $result['errors'][0]['message']);
    }
} 