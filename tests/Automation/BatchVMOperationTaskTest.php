<?php

namespace Tests\Automation;

use PHPUnit\Framework\TestCase;
use ProxmoxApi\Client;
use ProxmoxApi\Automation\BatchVMOperationTask;
use ProxmoxApi\Exception\ProxmoxApiException;

class BatchVMOperationTaskTest extends TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|Client
     */
    private $clientMock;

    protected function setUp(): void
    {
        $this->clientMock = $this->createMock(Client::class);
    }

    public function testConstructorWithDefaultConfig()
    {
        $task = new BatchVMOperationTask($this->clientMock);
        
        $reflection = new \ReflectionClass($task);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($task);
        
        $this->assertEquals(BatchVMOperationTask::ACTION_START, $config['action']);
        $this->assertNull($config['node']);
        $this->assertEmpty($config['vmids']);
        $this->assertEmpty($config['filters']);
        $this->assertFalse($config['parallel']);
        $this->assertEquals(5, $config['max_parallel']);
        $this->assertTrue($config['continue_on_error']);
        $this->assertEquals(300, $config['timeout']);
    }

    public function testConstructorWithCustomConfig()
    {
        $customConfig = [
            'action' => BatchVMOperationTask::ACTION_STOP,
            'node' => 'node1',
            'vmids' => [100, 101],
            'parallel' => true,
            'timeout' => 600,
        ];
        
        $task = new BatchVMOperationTask($this->clientMock, $customConfig);
        
        $reflection = new \ReflectionClass($task);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($task);
        
        $this->assertEquals(BatchVMOperationTask::ACTION_STOP, $config['action']);
        $this->assertEquals('node1', $config['node']);
        $this->assertEquals([100, 101], $config['vmids']);
        $this->assertTrue($config['parallel']);
        $this->assertEquals(600, $config['timeout']);
    }

    public function testValidateConfigWithValidAction()
    {
        $task = new BatchVMOperationTask($this->clientMock, [
            'action' => BatchVMOperationTask::ACTION_START,
        ]);
        
        $reflection = new \ReflectionClass($task);
        $validateConfigMethod = $reflection->getMethod('validateConfig');
        $validateConfigMethod->setAccessible(true);
        
        // 不应抛出异常
        $validateConfigMethod->invoke($task);
        $this->assertTrue(true);
    }

    public function testValidateConfigWithInvalidAction()
    {
        $task = new BatchVMOperationTask($this->clientMock, [
            'action' => 'invalid_action',
        ]);
        
        $reflection = new \ReflectionClass($task);
        $validateConfigMethod = $reflection->getMethod('validateConfig');
        $validateConfigMethod->setAccessible(true);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('无效的操作类型: invalid_action');
        
        $validateConfigMethod->invoke($task);
    }

    public function testValidateConfigWithCloneActionMissingSourceVmid()
    {
        $task = new BatchVMOperationTask($this->clientMock, [
            'action' => BatchVMOperationTask::ACTION_CLONE,
            'params' => [],
        ]);
        
        $reflection = new \ReflectionClass($task);
        $validateConfigMethod = $reflection->getMethod('validateConfig');
        $validateConfigMethod->setAccessible(true);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('克隆操作需要指定源虚拟机ID (source_vmid)');
        
        $validateConfigMethod->invoke($task);
    }

    public function testGetTargetVMsWithSpecificVmids()
    {
        $node = 'node1';
        $vmids = [100, 102];
        $vms = [
            ['vmid' => 100, 'name' => 'vm1', 'status' => 'running', 'type' => 'qemu'],
            ['vmid' => 101, 'name' => 'vm2', 'status' => 'stopped', 'type' => 'qemu'],
            ['vmid' => 102, 'name' => 'vm3', 'status' => 'running', 'type' => 'qemu'],
        ];
        
        $this->clientMock->expects($this->once())
            ->method('getNodeVMs')
            ->with($node)
            ->willReturn($vms);
        
        $task = new BatchVMOperationTask($this->clientMock, [
            'node' => $node,
            'vmids' => $vmids,
        ]);
        
        $reflection = new \ReflectionClass($task);
        $getTargetVMsMethod = $reflection->getMethod('getTargetVMs');
        $getTargetVMsMethod->setAccessible(true);
        
        $result = $getTargetVMsMethod->invoke($task);
        
        $this->assertCount(2, $result);
        $this->assertEquals(100, $result[0]['vmid']);
        $this->assertEquals(102, $result[1]['vmid']);
    }

    public function testGetTargetVMsWithFilters()
    {
        $node = 'node1';
        $filters = ['status' => 'running'];
        $vms = [
            ['vmid' => 100, 'name' => 'vm1', 'status' => 'running', 'type' => 'qemu'],
            ['vmid' => 101, 'name' => 'vm2', 'status' => 'stopped', 'type' => 'qemu'],
            ['vmid' => 102, 'name' => 'vm3', 'status' => 'running', 'type' => 'qemu'],
        ];
        
        $this->clientMock->expects($this->once())
            ->method('getNodeVMs')
            ->with($node)
            ->willReturn($vms);
        
        $task = new BatchVMOperationTask($this->clientMock, [
            'node' => $node,
            'filters' => $filters,
        ]);
        
        $reflection = new \ReflectionClass($task);
        $getTargetVMsMethod = $reflection->getMethod('getTargetVMs');
        $getTargetVMsMethod->setAccessible(true);
        
        $result = $getTargetVMsMethod->invoke($task);
        
        $this->assertCount(2, $result);
        $this->assertEquals('running', $result[0]['status']);
        $this->assertEquals('running', $result[1]['status']);
    }

    public function testExecuteSequential()
    {
        $node = 'node1';
        $vms = [
            ['vmid' => 100, 'name' => 'vm1', 'status' => 'stopped', 'type' => 'qemu', 'node' => $node],
            ['vmid' => 101, 'name' => 'vm2', 'status' => 'stopped', 'type' => 'qemu', 'node' => $node],
        ];
        
        $task = $this->getMockBuilder(BatchVMOperationTask::class)
            ->setConstructorArgs([$this->clientMock, [
                'action' => BatchVMOperationTask::ACTION_START,
                'node' => $node,
                'parallel' => false,
            ]])
            ->onlyMethods(['getTargetVMs', 'executeAction'])
            ->getMock();
        
        $task->expects($this->once())
            ->method('getTargetVMs')
            ->willReturn($vms);
            
        $task->expects($this->exactly(2))
            ->method('executeAction')
            ->willReturnMap([
                [$vms[0], ['status' => 'success', 'vmid' => 100]],
                [$vms[1], ['status' => 'success', 'vmid' => 101]],
            ]);
        
        $results = $task->execute();
        
        $this->assertCount(2, $results);
        $this->assertEquals(['status' => 'success', 'vmid' => 100], $results[100]);
        $this->assertEquals(['status' => 'success', 'vmid' => 101], $results[101]);
    }

    public function testExecuteSequentialWithError()
    {
        $node = 'node1';
        $vms = [
            ['vmid' => 100, 'name' => 'vm1', 'status' => 'stopped', 'type' => 'qemu', 'node' => $node],
            ['vmid' => 101, 'name' => 'vm2', 'status' => 'stopped', 'type' => 'qemu', 'node' => $node],
        ];
        
        $task = $this->getMockBuilder(BatchVMOperationTask::class)
            ->setConstructorArgs([$this->clientMock, [
                'action' => BatchVMOperationTask::ACTION_START,
                'node' => $node,
                'parallel' => false,
                'continue_on_error' => true,
            ]])
            ->onlyMethods(['getTargetVMs', 'executeAction', 'log'])
            ->getMock();
        
        $task->expects($this->once())
            ->method('getTargetVMs')
            ->willReturn($vms);
            
        $task->expects($this->once())
            ->method('executeAction')
            ->with($vms[0])
            ->willThrowException(new \Exception('测试错误'));
            
        $task->expects($this->once())
            ->method('log')
            ->with($this->stringContains('失败'), 'error');
        
        $results = $task->execute();
        
        $this->assertCount(1, $results);
        $this->assertEquals(['error' => '测试错误'], $results[100]);
    }

    public function testStartAction()
    {
        $node = 'node1';
        $vmid = 100;
        $vm = ['vmid' => $vmid, 'node' => $node, 'name' => 'vm1', 'status' => 'stopped'];
        
        $this->clientMock->expects($this->once())
            ->method('startVM')
            ->with($node, $vmid)
            ->willReturn(['upid' => 'UPID:node1:00000000:00000000:00000000:start:100:']);
        
        $task = new BatchVMOperationTask($this->clientMock, [
            'action' => BatchVMOperationTask::ACTION_START,
        ]);
        
        $reflection = new \ReflectionClass($task);
        $startActionMethod = $reflection->getMethod('startAction');
        $startActionMethod->setAccessible(true);
        
        $result = $startActionMethod->invoke($task, $vm);
        
        $this->assertArrayHasKey('upid', $result);
    }

    public function testStartActionWithUnsupportedAction()
    {
        $node = 'node1';
        $vmid = 100;
        $vm = ['vmid' => $vmid, 'node' => $node, 'name' => 'vm1', 'status' => 'stopped'];
        
        $task = new BatchVMOperationTask($this->clientMock, [
            'action' => 'unsupported_action',
        ]);
        
        $reflection = new \ReflectionClass($task);
        $startActionMethod = $reflection->getMethod('startAction');
        $startActionMethod->setAccessible(true);
        
        $this->expectException(ProxmoxApiException::class);
        $this->expectExceptionMessage('不支持的操作: unsupported_action');
        
        $startActionMethod->invoke($task, $vm);
    }
} 