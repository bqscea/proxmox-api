<?php

namespace Tests\Automation;

use PHPUnit\Framework\TestCase;
use ProxmoxApi\Client;
use ProxmoxApi\Automation\BatchBackupTask;
use ProxmoxApi\Exception\ProxmoxApiException;

class BatchBackupTaskTest extends TestCase
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
        $task = new BatchBackupTask($this->clientMock);
        
        $reflection = new \ReflectionClass($task);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($task);
        
        $this->assertNull($config['node']);
        $this->assertEmpty($config['vmids']);
        $this->assertEmpty($config['filters']);
        $this->assertFalse($config['all']);
        $this->assertEquals('local', $config['storage']);
        $this->assertEquals('snapshot', $config['mode']);
        $this->assertEquals('zstd', $config['compress']);
        $this->assertEquals(0, $config['remove']);
        $this->assertNull($config['schedule']);
        $this->assertNull($config['max_backups']);
        $this->assertEmpty($config['exclude_vms']);
        $this->assertNull($config['mail_notification']);
        $this->assertNull($config['mail_to']);
        $this->assertEquals(3600, $config['timeout']);
    }

    public function testConstructorWithCustomConfig()
    {
        $customConfig = [
            'node' => 'node1',
            'vmids' => [100, 101],
            'storage' => 'backup',
            'mode' => 'stop',
            'schedule' => '0 2 * * *',
            'mail_to' => 'admin@example.com',
        ];
        
        $task = new BatchBackupTask($this->clientMock, $customConfig);
        
        $reflection = new \ReflectionClass($task);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($task);
        
        $this->assertEquals('node1', $config['node']);
        $this->assertEquals([100, 101], $config['vmids']);
        $this->assertEquals('backup', $config['storage']);
        $this->assertEquals('stop', $config['mode']);
        $this->assertEquals('0 2 * * *', $config['schedule']);
        $this->assertEquals('admin@example.com', $config['mail_to']);
    }

    public function testValidateConfigWithNoTargetSpecified()
    {
        $task = new BatchBackupTask($this->clientMock, [
            'all' => false,
            'vmids' => [],
            'filters' => [],
        ]);
        
        $reflection = new \ReflectionClass($task);
        $validateConfigMethod = $reflection->getMethod('validateConfig');
        $validateConfigMethod->setAccessible(true);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("必须指定 'all'、'vmids' 或 'filters' 参数之一");
        
        $validateConfigMethod->invoke($task);
    }

    public function testValidateConfigWithInvalidSchedule()
    {
        $task = new BatchBackupTask($this->clientMock, [
            'all' => true,
            'schedule' => 'invalid-cron',
        ]);
        
        $reflection = new \ReflectionClass($task);
        $validateConfigMethod = $reflection->getMethod('validateConfig');
        $validateConfigMethod->setAccessible(true);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('无效的计划表达式: invalid-cron');
        
        $validateConfigMethod->invoke($task);
    }

    public function testIsValidCronExpression()
    {
        $task = new BatchBackupTask($this->clientMock);
        
        $reflection = new \ReflectionClass($task);
        $isValidCronExpressionMethod = $reflection->getMethod('isValidCronExpression');
        $isValidCronExpressionMethod->setAccessible(true);
        
        $this->assertTrue($isValidCronExpressionMethod->invoke($task, '0 2 * * *'));
        $this->assertTrue($isValidCronExpressionMethod->invoke($task, '*/5 * * * *'));
        $this->assertTrue($isValidCronExpressionMethod->invoke($task, '0 0 1 1 *'));
        $this->assertFalse($isValidCronExpressionMethod->invoke($task, 'invalid'));
        $this->assertFalse($isValidCronExpressionMethod->invoke($task, '* *'));
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
        
        $task = new BatchBackupTask($this->clientMock, [
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

    public function testGetTargetVMsWithExcludeVms()
    {
        $node = 'node1';
        $vmids = [100, 101, 102];
        $excludeVms = [101];
        $vms = [
            ['vmid' => 100, 'name' => 'vm1', 'status' => 'running', 'type' => 'qemu'],
            ['vmid' => 101, 'name' => 'vm2', 'status' => 'stopped', 'type' => 'qemu'],
            ['vmid' => 102, 'name' => 'vm3', 'status' => 'running', 'type' => 'qemu'],
        ];
        
        $this->clientMock->expects($this->once())
            ->method('getNodeVMs')
            ->with($node)
            ->willReturn($vms);
        
        $task = new BatchBackupTask($this->clientMock, [
            'node' => $node,
            'vmids' => $vmids,
            'exclude_vms' => $excludeVms,
        ]);
        
        $reflection = new \ReflectionClass($task);
        $getTargetVMsMethod = $reflection->getMethod('getTargetVMs');
        $getTargetVMsMethod->setAccessible(true);
        
        $result = $getTargetVMsMethod->invoke($task);
        
        $this->assertCount(2, $result);
        $this->assertEquals(100, $result[0]['vmid']);
        $this->assertEquals(102, $result[1]['vmid']);
    }

    public function testBackupAllVMs()
    {
        $node = 'node1';
        $taskId = 'UPID:node1:00000000:00000000:00000000:backup:all:';
        
        $this->clientMock->expects($this->once())
            ->method('backup')
            ->willReturn($this->clientMock);
            
        $this->clientMock->expects($this->once())
            ->method('createAllBackup')
            ->with($node, $this->anything())
            ->willReturn(['upid' => $taskId]);
        
        $task = $this->getMockBuilder(BatchBackupTask::class)
            ->setConstructorArgs([$this->clientMock, [
                'node' => $node,
                'all' => true,
                'storage' => 'local',
            ]])
            ->onlyMethods(['waitForTask'])
            ->getMock();
            
        $task->expects($this->once())
            ->method('waitForTask')
            ->with($node, $taskId, 3600)
            ->willReturn(['status' => 'stopped', 'exitstatus' => 'OK']);
        
        $results = $task->execute();
        
        $this->assertArrayHasKey('all', $results);
        $this->assertEquals('OK', $results['all']['exitstatus']);
    }

    public function testBackupVMs()
    {
        $node = 'node1';
        $vmids = [100, 101];
        $taskId = 'UPID:node1:00000000:00000000:00000000:backup:vms:';
        
        $vms = [
            ['vmid' => 100, 'name' => 'vm1', 'status' => 'running', 'type' => 'qemu', 'node' => $node],
            ['vmid' => 101, 'name' => 'vm2', 'status' => 'running', 'type' => 'qemu', 'node' => $node],
        ];
        
        $this->clientMock->expects($this->once())
            ->method('backup')
            ->willReturn($this->clientMock);
            
        $this->clientMock->expects($this->once())
            ->method('createBatchBackup')
            ->with($node, $vmids, $this->anything())
            ->willReturn(['upid' => $taskId]);
        
        $task = $this->getMockBuilder(BatchBackupTask::class)
            ->setConstructorArgs([$this->clientMock, [
                'node' => $node,
                'vmids' => $vmids,
                'storage' => 'local',
            ]])
            ->onlyMethods(['getTargetVMs', 'waitForTask'])
            ->getMock();
            
        $task->expects($this->once())
            ->method('getTargetVMs')
            ->willReturn($vms);
            
        $task->expects($this->once())
            ->method('waitForTask')
            ->with($node, $taskId, 3600)
            ->willReturn(['status' => 'stopped', 'exitstatus' => 'OK']);
        
        $results = $task->execute();
        
        $this->assertArrayHasKey($node, $results);
        $this->assertEquals('OK', $results[$node]['exitstatus']);
    }

    public function testBuildBackupParams()
    {
        $task = new BatchBackupTask($this->clientMock, [
            'storage' => 'backup',
            'mode' => 'stop',
            'compress' => 'lzo',
            'remove' => 1,
            'mail_notification' => 'always',
            'mail_to' => 'admin@example.com',
            'schedule' => '0 2 * * *',
            'max_backups' => 5,
            'exclude_vms' => [101, 102],
        ]);
        
        $reflection = new \ReflectionClass($task);
        $buildBackupParamsMethod = $reflection->getMethod('buildBackupParams');
        $buildBackupParamsMethod->setAccessible(true);
        
        $params = $buildBackupParamsMethod->invoke($task, true);
        
        $this->assertEquals('stop', $params['mode']);
        $this->assertEquals('lzo', $params['compress']);
        $this->assertEquals('backup', $params['storage']);
        $this->assertEquals(1, $params['all']);
        $this->assertEquals(1, $params['remove']);
        $this->assertEquals('always', $params['mailnotification']);
        $this->assertEquals('admin@example.com', $params['mailto']);
        $this->assertEquals('0 2 * * *', $params['schedule']);
        $this->assertEquals(5, $params['maxfiles']);
        $this->assertEquals('101,102', $params['exclude']);
    }

    public function testCreateSchedule()
    {
        $scheduleParams = [
            'type' => 'vzdump',
            'enabled' => 1,
            'schedule' => '0 2 * * *',
            'storage' => 'local',
            'mode' => 'snapshot',
            'compress' => 'zstd',
            'remove' => 0,
            'vmid' => '100,101',
        ];
        
        $this->clientMock->expects($this->once())
            ->method('post')
            ->with('cluster/backup', $this->callback(function ($params) use ($scheduleParams) {
                foreach ($scheduleParams as $key => $value) {
                    if (!isset($params[$key]) || $params[$key] !== $value) {
                        return false;
                    }
                }
                return true;
            }))
            ->willReturn($this->clientMock);
            
        $this->clientMock->expects($this->once())
            ->method('toArray')
            ->willReturn(['id' => 'backup-daily']);
        
        $task = new BatchBackupTask($this->clientMock, [
            'vmids' => [100, 101],
            'schedule' => '0 2 * * *',
        ]);
        
        $result = $task->createSchedule();
        
        $this->assertEquals('backup-daily', $result['id']);
    }

    public function testCreateScheduleWithoutSchedule()
    {
        $task = new BatchBackupTask($this->clientMock);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('必须指定计划表达式');
        
        $task->createSchedule();
    }

    public function testCleanupOldBackups()
    {
        $backups = [
            [
                'volid' => 'local:backup/vzdump-qemu-100-2023_01_01-00_00_00.vma.zst',
                'ctime' => 1672531200, // 2023-01-01
                'storage' => 'local',
            ],
            [
                'volid' => 'local:backup/vzdump-qemu-100-2023_01_02-00_00_00.vma.zst',
                'ctime' => 1672617600, // 2023-01-02
                'storage' => 'local',
            ],
            [
                'volid' => 'local:backup/vzdump-qemu-100-2023_01_03-00_00_00.vma.zst',
                'ctime' => 1672704000, // 2023-01-03
                'storage' => 'local',
            ],
        ];
        
        $this->clientMock->expects($this->once())
            ->method('getAllBackups')
            ->willReturn(['node1' => $backups]);
            
        $this->clientMock->expects($this->once())
            ->method('backup')
            ->willReturn($this->clientMock);
            
        $this->clientMock->expects($this->once())
            ->method('deleteBackup')
            ->with(
                'node1',
                'local',
                'local:backup/vzdump-qemu-100-2023_01_01-00_00_00.vma.zst'
            )
            ->willReturn(['success' => true]);
        
        $task = new BatchBackupTask($this->clientMock);
        $results = $task->cleanupOldBackups(2); // 保留最新的2个
        
        $this->assertArrayHasKey(100, $results);
        $this->assertCount(1, $results[100]);
        $this->assertEquals('local:backup/vzdump-qemu-100-2023_01_01-00_00_00.vma.zst', $results[100][0]['volid']);
        $this->assertEquals(['success' => true], $results[100][0]['result']);
    }
} 