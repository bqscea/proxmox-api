<?php

namespace Tests\Automation;

use PHPUnit\Framework\TestCase;
use ProxmoxApi\Client;
use ProxmoxApi\Automation\ResourceMonitorTask;

/**
 * 警报处理器测试
 * 
 * 这个测试类专门测试资源监控任务中的警报处理器功能
 */
class AlertHandlersTest extends TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|Client
     */
    private $clientMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|ResourceMonitorTask
     */
    private $monitorTaskMock;

    /**
     * @var array 测试用的虚拟机数据
     */
    private $testVm;

    /**
     * @var array 测试用的警报数据
     */
    private $testAlerts;

    protected function setUp(): void
    {
        $this->clientMock = $this->createMock(Client::class);
        
        $this->monitorTaskMock = $this->getMockBuilder(ResourceMonitorTask::class)
            ->setConstructorArgs([$this->clientMock])
            ->onlyMethods(['log'])
            ->getMock();
            
        $this->testVm = [
            'vmid' => 100,
            'name' => 'test-vm',
            'status' => 'running',
            'node' => 'node1',
        ];
        
        $this->testAlerts = [
            [
                'resource' => ResourceMonitorTask::RESOURCE_CPU,
                'vmid' => 100,
                'value' => 90,
                'threshold' => 80,
                'timestamp' => time(),
                'message' => '虚拟机 100 的CPU使用率 (90%) 超过阈值 (80%)',
            ],
            [
                'resource' => ResourceMonitorTask::RESOURCE_MEMORY,
                'vmid' => 100,
                'value' => 85,
                'threshold' => 80,
                'timestamp' => time(),
                'message' => '虚拟机 100 的内存使用率 (85%) 超过阈值 (80%)',
            ],
        ];
    }

    /**
     * 测试邮件警报处理器
     */
    public function testEmailAlertHandler()
    {
        // 由于无法直接测试mail函数，我们使用runkit扩展模拟它
        // 如果没有runkit扩展，这个测试将被跳过
        if (!extension_loaded('runkit7') && !extension_loaded('runkit')) {
            $this->markTestSkipped('需要runkit扩展来测试邮件功能');
        }
        
        // 保存原始的mail函数
        $originalMailFunction = null;
        if (extension_loaded('runkit7')) {
            $originalMailFunction = runkit7_function_copy('mail', 'original_mail');
        } else {
            $originalMailFunction = runkit_function_copy('mail', 'original_mail');
        }
        
        // 模拟mail函数
        $mailCalled = false;
        $mailTo = null;
        $mailSubject = null;
        $mailBody = null;
        
        if (extension_loaded('runkit7')) {
            runkit7_function_redefine('mail', '$to, $subject, $message, $headers', 
                'global $mailCalled, $mailTo, $mailSubject, $mailBody;
                $mailCalled = true;
                $mailTo = $to;
                $mailSubject = $subject;
                $mailBody = $message;
                return true;');
        } else {
            runkit_function_redefine('mail', '$to, $subject, $message, $headers', 
                'global $mailCalled, $mailTo, $mailSubject, $mailBody;
                $mailCalled = true;
                $mailTo = $to;
                $mailSubject = $subject;
                $mailBody = $message;
                return true;');
        }
        
        try {
            // 创建邮件警报处理器
            $emailHandler = ResourceMonitorTask::createEmailAlertHandler(
                'admin@example.com',
                '虚拟机资源警报',
                'proxmox@example.com'
            );
            
            // 调用处理器
            $emailHandler($this->testAlerts, $this->testVm, $this->monitorTaskMock);
            
            // 验证邮件是否被发送
            $this->assertTrue($mailCalled);
            $this->assertEquals('admin@example.com', $mailTo);
            $this->assertEquals('虚拟机资源警报', $mailSubject);
            $this->assertStringContainsString('虚拟机 100 (test-vm) 资源警报', $mailBody);
            $this->assertStringContainsString('CPU使用率 (90%) 超过阈值 (80%)', $mailBody);
            $this->assertStringContainsString('内存使用率 (85%) 超过阈值 (80%)', $mailBody);
        } finally {
            // 恢复原始的mail函数
            if (extension_loaded('runkit7')) {
                runkit7_function_remove('mail');
                runkit7_function_rename('original_mail', 'mail');
            } else {
                runkit_function_remove('mail');
                runkit_function_rename('original_mail', 'mail');
            }
        }
    }

    /**
     * 测试日志警报处理器
     */
    public function testLogAlertHandler()
    {
        // 创建临时日志文件
        $logFile = tempnam(sys_get_temp_dir(), 'proxmox_test_');
        
        // 创建日志警报处理器
        $logHandler = ResourceMonitorTask::createLogAlertHandler($logFile);
        
        // 模拟日志记录
        $this->monitorTaskMock->expects($this->once())
            ->method('log')
            ->with($this->stringContains('记录警报到日志文件'));
        
        // 调用处理器
        $logHandler($this->testAlerts, $this->testVm, $this->monitorTaskMock);
        
        // 验证日志文件内容
        $logContent = file_get_contents($logFile);
        $this->assertStringContainsString('虚拟机 100 (test-vm) 资源警报', $logContent);
        $this->assertStringContainsString('CPU使用率 (90%) 超过阈值 (80%)', $logContent);
        $this->assertStringContainsString('内存使用率 (85%) 超过阈值 (80%)', $logContent);
        
        // 清理临时文件
        unlink($logFile);
    }

    /**
     * 测试自动扩容处理器
     */
    public function testAutoScaleHandler()
    {
        // 模拟虚拟机配置
        $vmConfig = [
            'sockets' => 1,
            'cores' => 2,
            'memory' => 4096,
            'scsi0' => 'local:vm-100-disk-0,size=20G',
        ];
        
        // 模拟客户端
        $this->clientMock->expects($this->once())
            ->method('get')
            ->with('nodes/node1/qemu/100/config')
            ->willReturn($this->clientMock);
            
        $this->clientMock->expects($this->once())
            ->method('toArray')
            ->willReturn($vmConfig);
            
        $this->clientMock->expects($this->once())
            ->method('put')
            ->with(
                'nodes/node1/qemu/100/config',
                $this->callback(function ($params) {
                    return isset($params['sockets']) && $params['sockets'] == 2 &&
                           isset($params['memory']) && $params['memory'] == 6144;
                })
            )
            ->willReturn($this->clientMock);
            
        $this->clientMock->expects($this->once())
            ->method('toArray')
            ->willReturn(['success' => true]);
            
        $this->clientMock->expects($this->once())
            ->method('getVMStatus')
            ->with('node1', 100)
            ->willReturn(['status' => 'running']);
        
        // 模拟日志记录
        $this->monitorTaskMock->expects($this->atLeastOnce())
            ->method('log')
            ->withConsecutive(
                [$this->stringContains('计划增加虚拟机 100 的CPU')],
                [$this->stringContains('计划增加虚拟机 100 的内存')],
                [$this->stringContains('应用虚拟机 100 的资源扩容')],
                [$this->stringContains('虚拟机 100 资源扩容成功')],
                [$this->stringContains('注意：某些更改可能需要重启虚拟机')]
            );
        
        // 创建自动扩容处理器
        $autoScaleHandler = ResourceMonitorTask::createAutoScaleHandler([
            'cpu_increment' => 2,
            'memory_increment' => 2048,
            'max_cpu' => 8,
            'max_memory' => 16384,
        ]);
        
        // 调用处理器
        $result = $autoScaleHandler($this->testAlerts, $this->testVm, $this->monitorTaskMock);
        
        // 验证结果
        $this->assertEquals(['success' => true], $result);
    }

    /**
     * 测试多个警报处理器的组合使用
     */
    public function testMultipleAlertHandlers()
    {
        // 创建临时日志文件
        $logFile = tempnam(sys_get_temp_dir(), 'proxmox_test_');
        
        // 创建处理器
        $handler1Called = false;
        $handler1 = function ($alerts, $vm, $task) use (&$handler1Called) {
            $handler1Called = true;
            $this->assertCount(2, $alerts);
            $this->assertEquals(100, $vm['vmid']);
            $this->assertInstanceOf(ResourceMonitorTask::class, $task);
            return ['handler' => 1];
        };
        
        $handler2Called = false;
        $handler2 = function ($alerts, $vm, $task) use (&$handler2Called) {
            $handler2Called = true;
            $this->assertCount(2, $alerts);
            $this->assertEquals(100, $vm['vmid']);
            $this->assertInstanceOf(ResourceMonitorTask::class, $task);
            return ['handler' => 2];
        };
        
        $logHandler = ResourceMonitorTask::createLogAlertHandler($logFile);
        
        // 添加处理器到监控任务
        $monitorTask = new ResourceMonitorTask($this->clientMock);
        $monitorTask->addAlertHandler($handler1);
        $monitorTask->addAlertHandler($handler2);
        $monitorTask->addAlertHandler($logHandler);
        
        // 模拟处理警报
        $reflection = new \ReflectionClass($monitorTask);
        $handleAlertsMethod = $reflection->getMethod('handleAlerts');
        $handleAlertsMethod->setAccessible(true);
        
        $handleAlertsMethod->invoke($monitorTask, $this->testAlerts, $this->testVm);
        
        // 验证处理器是否被调用
        $this->assertTrue($handler1Called);
        $this->assertTrue($handler2Called);
        
        // 验证日志文件内容
        $logContent = file_get_contents($logFile);
        $this->assertStringContainsString('虚拟机 100 (test-vm) 资源警报', $logContent);
        
        // 清理临时文件
        unlink($logFile);
    }

    /**
     * 测试处理器异常处理
     */
    public function testAlertHandlerExceptionHandling()
    {
        // 创建会抛出异常的处理器
        $exceptionHandler = function ($alerts, $vm, $task) {
            throw new \Exception('测试异常');
        };
        
        // 模拟日志记录
        $this->monitorTaskMock->expects($this->once())
            ->method('log')
            ->with($this->stringContains('处理警报失败'), 'error');
        
        // 模拟处理警报
        $reflection = new \ReflectionClass($this->monitorTaskMock);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($this->monitorTaskMock);
        $config['alert_handlers'] = [$exceptionHandler];
        $configProperty->setValue($this->monitorTaskMock, $config);
        
        $handleAlertsMethod = $reflection->getMethod('handleAlerts');
        $handleAlertsMethod->setAccessible(true);
        
        // 调用处理器（不应抛出异常）
        $handleAlertsMethod->invoke($this->monitorTaskMock, $this->testAlerts, $this->testVm);
        
        // 如果没有异常抛出，测试通过
        $this->assertTrue(true);
    }
} 