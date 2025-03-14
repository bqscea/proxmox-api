<?php

namespace Tests\Automation;

use PHPUnit\Framework\TestCase;
use ProxmoxApi\Client;
use ProxmoxApi\Automation\AutomationTask;
use ProxmoxApi\Exception\ProxmoxApiException;

class AutomationTaskTest extends TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|Client
     */
    private $clientMock;

    protected function setUp(): void
    {
        $this->clientMock = $this->createMock(Client::class);
    }

    public function testLogMethod()
    {
        $task = new ConcreteAutomationTask($this->clientMock);
        $task->testLog('测试消息');
        $task->testLog('警告消息', 'warning');
        $task->testLog('错误消息', 'error');

        $logs = $task->getLogs();
        
        $this->assertCount(3, $logs);
        $this->assertEquals('测试消息', $logs[0]['message']);
        $this->assertEquals('info', $logs[0]['level']);
        $this->assertEquals('警告消息', $logs[1]['message']);
        $this->assertEquals('warning', $logs[1]['level']);
        $this->assertEquals('错误消息', $logs[2]['message']);
        $this->assertEquals('error', $logs[2]['level']);
    }

    public function testWaitForTaskSuccess()
    {
        $taskId = 'UPID:node1:00000000:00000000:00000000:task:success:';
        $node = 'node1';
        
        $this->clientMock->expects($this->exactly(2))
            ->method('nodes')
            ->willReturn($this->clientMock);
            
        $this->clientMock->expects($this->exactly(2))
            ->method('getTaskStatus')
            ->with($node, $taskId)
            ->willReturnOnConsecutiveCalls(
                ['status' => 'running'],
                ['status' => 'stopped', 'exitstatus' => 'OK']
            );
        
        $task = new ConcreteAutomationTask($this->clientMock);
        $result = $task->testWaitForTask($node, $taskId, 10, 1);
        
        $this->assertEquals('stopped', $result['status']);
        $this->assertEquals('OK', $result['exitstatus']);
        
        $logs = $task->getLogs();
        $this->assertStringContainsString('等待任务', $logs[0]['message']);
        $this->assertStringContainsString('成功完成', $logs[1]['message']);
    }

    public function testWaitForTaskFailure()
    {
        $taskId = 'UPID:node1:00000000:00000000:00000000:task:failure:';
        $node = 'node1';
        
        $this->clientMock->expects($this->exactly(2))
            ->method('nodes')
            ->willReturn($this->clientMock);
            
        $this->clientMock->expects($this->exactly(2))
            ->method('getTaskStatus')
            ->with($node, $taskId)
            ->willReturnOnConsecutiveCalls(
                ['status' => 'running'],
                ['status' => 'stopped', 'exitstatus' => 'Error']
            );
        
        $task = new ConcreteAutomationTask($this->clientMock);
        $result = $task->testWaitForTask($node, $taskId, 10, 1);
        
        $this->assertEquals('stopped', $result['status']);
        $this->assertEquals('Error', $result['exitstatus']);
        
        $logs = $task->getLogs();
        $this->assertStringContainsString('等待任务', $logs[0]['message']);
        $this->assertStringContainsString('失败', $logs[1]['message']);
    }

    public function testWaitForTaskTimeout()
    {
        $taskId = 'UPID:node1:00000000:00000000:00000000:task:timeout:';
        $node = 'node1';
        
        $this->clientMock->expects($this->atLeastOnce())
            ->method('nodes')
            ->willReturn($this->clientMock);
            
        $this->clientMock->expects($this->atLeastOnce())
            ->method('getTaskStatus')
            ->with($node, $taskId)
            ->willReturn(['status' => 'running']);
        
        $task = new ConcreteAutomationTask($this->clientMock);
        
        $this->expectException(ProxmoxApiException::class);
        $this->expectExceptionMessage('任务 ' . $taskId . ' 超时');
        
        $task->testWaitForTask($node, $taskId, 2, 1);
    }

    public function testGetFilteredVMs()
    {
        $node = 'node1';
        $vms = [
            ['vmid' => 100, 'name' => 'vm1', 'status' => 'running', 'type' => 'qemu'],
            ['vmid' => 101, 'name' => 'vm2', 'status' => 'stopped', 'type' => 'qemu'],
            ['vmid' => 102, 'name' => 'vm3', 'status' => 'running', 'type' => 'qemu'],
        ];
        
        $this->clientMock->expects($this->once())
            ->method('getNodeVMs')
            ->with($node)
            ->willReturn($vms);
        
        $task = new ConcreteAutomationTask($this->clientMock);
        
        // 测试无过滤条件
        $result = $task->testGetFilteredVMs($node);
        $this->assertCount(3, $result);
        
        // 测试按状态过滤
        $result = $task->testGetFilteredVMs($node, ['status' => 'running']);
        $this->assertCount(2, $result);
        $this->assertEquals(100, $result[0]['vmid']);
        $this->assertEquals(102, $result[2]['vmid']);
        
        // 测试按ID过滤
        $result = $task->testGetFilteredVMs($node, ['vmid' => 101]);
        $this->assertCount(1, $result);
        $this->assertEquals(101, $result[0]['vmid']);
    }

    public function testGetFilteredContainers()
    {
        $node = 'node1';
        $containers = [
            ['vmid' => 200, 'name' => 'ct1', 'status' => 'running', 'type' => 'lxc'],
            ['vmid' => 201, 'name' => 'ct2', 'status' => 'stopped', 'type' => 'lxc'],
        ];
        
        $this->clientMock->expects($this->once())
            ->method('nodes')
            ->willReturn($this->clientMock);
            
        $this->clientMock->expects($this->once())
            ->method('getContainers')
            ->with($node)
            ->willReturn($containers);
        
        $task = new ConcreteAutomationTask($this->clientMock);
        
        // 测试无过滤条件
        $result = $task->testGetFilteredContainers($node);
        $this->assertCount(2, $result);
        
        // 测试按状态过滤
        $result = $task->testGetFilteredContainers($node, ['status' => 'running']);
        $this->assertCount(1, $result);
        $this->assertEquals(200, $result[0]['vmid']);
    }

    public function testGetAllFilteredVMs()
    {
        $nodes = [
            ['node' => 'node1'],
            ['node' => 'node2'],
        ];
        
        $node1VMs = [
            ['vmid' => 100, 'name' => 'vm1', 'status' => 'running', 'type' => 'qemu'],
            ['vmid' => 101, 'name' => 'vm2', 'status' => 'stopped', 'type' => 'qemu'],
        ];
        
        $node2VMs = [
            ['vmid' => 102, 'name' => 'vm3', 'status' => 'running', 'type' => 'qemu'],
        ];
        
        $this->clientMock->expects($this->once())
            ->method('getNodes')
            ->willReturn($nodes);
            
        $this->clientMock->expects($this->exactly(2))
            ->method('getNodeVMs')
            ->willReturnMap([
                ['node1', $node1VMs],
                ['node2', $node2VMs],
            ]);
        
        $task = new ConcreteAutomationTask($this->clientMock);
        $result = $task->testGetAllFilteredVMs();
        
        $this->assertCount(3, $result);
        $this->assertEquals('node1', $result[0]['node']);
        $this->assertEquals('node1', $result[1]['node']);
        $this->assertEquals('node2', $result[2]['node']);
    }
}

/**
 * 用于测试的具体实现类
 */
class ConcreteAutomationTask extends AutomationTask
{
    public function execute(): array
    {
        return [];
    }
    
    public function testLog(string $message, string $level = 'info'): void
    {
        $this->log($message, $level);
    }
    
    public function testWaitForTask(string $node, string $upid, int $timeout = 300, int $interval = 2): array
    {
        return $this->waitForTask($node, $upid, $timeout, $interval);
    }
    
    public function testGetFilteredVMs(string $node, array $filters = []): array
    {
        return $this->getFilteredVMs($node, $filters);
    }
    
    public function testGetFilteredContainers(string $node, array $filters = []): array
    {
        return $this->getFilteredContainers($node, $filters);
    }
    
    public function testGetAllFilteredVMs(array $filters = []): array
    {
        return $this->getAllFilteredVMs($filters);
    }
} 