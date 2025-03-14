<?php

namespace Tests\Automation;

use PHPUnit\Framework\TestCase;
use ProxmoxApi\Client;
use ProxmoxApi\Automation\AutomationTask;
use ProxmoxApi\Automation\BatchVMOperationTask;
use ProxmoxApi\Automation\BatchBackupTask;
use ProxmoxApi\Automation\ResourceMonitorTask;
use ProxmoxApi\Exception\ProxmoxApiException;

/**
 * 自动化功能集成测试
 * 
 * 这个测试类测试多个自动化任务类之间的交互和组合使用场景
 */
class IntegrationTest extends TestCase
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
     * 测试批量操作后进行备份的场景
     */
    public function testOperationFollowedByBackup()
    {
        $node = 'node1';
        $vmids = [100, 101];
        $vms = [
            ['vmid' => 100, 'name' => 'vm1', 'status' => 'running', 'type' => 'qemu', 'node' => $node],
            ['vmid' => 101, 'name' => 'vm2', 'status' => 'running', 'type' => 'qemu', 'node' => $node],
        ];
        
        // 模拟停止虚拟机操作
        $stopTask = $this->getMockBuilder(BatchVMOperationTask::class)
            ->setConstructorArgs([$this->clientMock, [
                'action' => BatchVMOperationTask::ACTION_STOP,
                'node' => $node,
                'vmids' => $vmids,
            ]])
            ->onlyMethods(['getTargetVMs', 'executeAction'])
            ->getMock();
            
        $stopTask->expects($this->once())
            ->method('getTargetVMs')
            ->willReturn($vms);
            
        $stopTask->expects($this->exactly(2))
            ->method('executeAction')
            ->willReturnMap([
                [$vms[0], ['status' => 'success', 'vmid' => 100]],
                [$vms[1], ['status' => 'success', 'vmid' => 101]],
            ]);
        
        $stopResults = $stopTask->execute();
        
        // 验证停止操作结果
        $this->assertCount(2, $stopResults);
        $this->assertEquals(['status' => 'success', 'vmid' => 100], $stopResults[100]);
        $this->assertEquals(['status' => 'success', 'vmid' => 101], $stopResults[101]);
        
        // 模拟备份操作
        $backupTask = $this->getMockBuilder(BatchBackupTask::class)
            ->setConstructorArgs([$this->clientMock, [
                'node' => $node,
                'vmids' => $vmids,
                'storage' => 'local',
                'mode' => 'stop', // 已经停止，使用stop模式
            ]])
            ->onlyMethods(['getTargetVMs', 'backupVMs'])
            ->getMock();
            
        $backupTask->expects($this->once())
            ->method('getTargetVMs')
            ->willReturn($vms);
            
        $backupTask->expects($this->once())
            ->method('backupVMs')
            ->with($vms)
            ->willReturn(['node1' => ['status' => 'success']]);
        
        $backupResults = $backupTask->execute();
        
        // 验证备份操作结果
        $this->assertArrayHasKey('node1', $backupResults);
        $this->assertEquals(['status' => 'success'], $backupResults['node1']);
        
        // 模拟启动虚拟机操作
        $startTask = $this->getMockBuilder(BatchVMOperationTask::class)
            ->setConstructorArgs([$this->clientMock, [
                'action' => BatchVMOperationTask::ACTION_START,
                'node' => $node,
                'vmids' => $vmids,
            ]])
            ->onlyMethods(['getTargetVMs', 'executeAction'])
            ->getMock();
            
        $startTask->expects($this->once())
            ->method('getTargetVMs')
            ->willReturn($vms);
            
        $startTask->expects($this->exactly(2))
            ->method('executeAction')
            ->willReturnMap([
                [$vms[0], ['status' => 'success', 'vmid' => 100]],
                [$vms[1], ['status' => 'success', 'vmid' => 101]],
            ]);
        
        $startResults = $startTask->execute();
        
        // 验证启动操作结果
        $this->assertCount(2, $startResults);
        $this->assertEquals(['status' => 'success', 'vmid' => 100], $startResults[100]);
        $this->assertEquals(['status' => 'success', 'vmid' => 101], $startResults[101]);
    }

    /**
     * 测试监控资源并执行自动扩容的场景
     */
    public function testMonitorAndAutoScale()
    {
        $node = 'node1';
        $vmid = 100;
        $vm = [
            'vmid' => $vmid,
            'name' => 'vm1',
            'status' => 'running',
            'node' => $node,
        ];
        
        // 模拟资源监控任务
        $monitorTask = $this->getMockBuilder(ResourceMonitorTask::class)
            ->setConstructorArgs([$this->clientMock, [
                'node' => $node,
                'vmids' => [$vmid],
                'resources' => [ResourceMonitorTask::RESOURCE_CPU, ResourceMonitorTask::RESOURCE_MEMORY],
                'threshold_cpu' => 80,
                'threshold_memory' => 80,
                'samples' => 1,
            ]])
            ->onlyMethods(['getTargetVMs', 'monitorVM'])
            ->getMock();
            
        $monitorTask->expects($this->once())
            ->method('getTargetVMs')
            ->willReturn([$vm]);
            
        $monitorTask->expects($this->once())
            ->method('monitorVM')
            ->with($vm)
            ->will($this->returnCallback(function ($vm) use ($monitorTask) {
                $reflection = new \ReflectionClass($monitorTask);
                $resultsProperty = $reflection->getProperty('results');
                $resultsProperty->setAccessible(true);
                
                $results = [
                    $vm['vmid'] => [
                        'samples' => [
                            [
                                'timestamp' => time(),
                                'cpu' => 90,
                                'memory' => 85,
                            ]
                        ],
                        'averages' => [
                            'cpu' => 90,
                            'memory' => 85,
                        ],
                        'alerts' => [
                            [
                                'resource' => ResourceMonitorTask::RESOURCE_CPU,
                                'vmid' => $vm['vmid'],
                                'value' => 90,
                                'threshold' => 80,
                                'message' => "虚拟机 {$vm['vmid']} 的CPU使用率 (90%) 超过阈值 (80%)",
                            ],
                            [
                                'resource' => ResourceMonitorTask::RESOURCE_MEMORY,
                                'vmid' => $vm['vmid'],
                                'value' => 85,
                                'threshold' => 80,
                                'message' => "虚拟机 {$vm['vmid']} 的内存使用率 (85%) 超过阈值 (80%)",
                            ]
                        ]
                    ]
                ];
                
                $resultsProperty->setValue($monitorTask, $results);
            }));
        
        // 添加自动扩容处理器
        $autoScaleCalled = false;
        $autoScaleHandler = function ($alerts, $vm, $task) use (&$autoScaleCalled) {
            $autoScaleCalled = true;
            $this->assertCount(2, $alerts);
            $this->assertEquals(ResourceMonitorTask::RESOURCE_CPU, $alerts[0]['resource']);
            $this->assertEquals(ResourceMonitorTask::RESOURCE_MEMORY, $alerts[1]['resource']);
            
            // 模拟扩容操作
            return [
                'cpu' => ['old' => 2, 'new' => 4],
                'memory' => ['old' => 4096, 'new' => 8192],
            ];
        };
        
        $monitorTask->addAlertHandler($autoScaleHandler);
        
        $monitorResults = $monitorTask->execute();
        
        // 验证监控结果
        $this->assertArrayHasKey($vmid, $monitorResults);
        $this->assertArrayHasKey('samples', $monitorResults[$vmid]);
        $this->assertArrayHasKey('averages', $monitorResults[$vmid]);
        $this->assertArrayHasKey('alerts', $monitorResults[$vmid]);
        $this->assertCount(2, $monitorResults[$vmid]['alerts']);
        
        // 验证自动扩容处理器被调用
        $this->assertTrue($autoScaleCalled);
    }

    /**
     * 测试定时备份和清理旧备份的场景
     */
    public function testScheduledBackupAndCleanup()
    {
        $node = 'node1';
        $vmids = [100, 101];
        
        // 模拟创建定时备份计划
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
        
        $backupTask = new BatchBackupTask($this->clientMock, [
            'node' => $node,
            'vmids' => $vmids,
            'schedule' => '0 2 * * *',
        ]);
        
        $scheduleResult = $backupTask->createSchedule();
        
        // 验证定时计划创建结果
        $this->assertEquals('backup-daily', $scheduleResult['id']);
        
        // 模拟清理旧备份
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
            [
                'volid' => 'local:backup/vzdump-qemu-101-2023_01_01-00_00_00.vma.zst',
                'ctime' => 1672531200, // 2023-01-01
                'storage' => 'local',
            ],
            [
                'volid' => 'local:backup/vzdump-qemu-101-2023_01_02-00_00_00.vma.zst',
                'ctime' => 1672617600, // 2023-01-02
                'storage' => 'local',
            ],
        ];
        
        $this->clientMock->expects($this->once())
            ->method('getAllBackups')
            ->willReturn(['node1' => $backups]);
            
        $this->clientMock->expects($this->exactly(2))
            ->method('backup')
            ->willReturn($this->clientMock);
            
        $this->clientMock->expects($this->exactly(2))
            ->method('deleteBackup')
            ->withConsecutive(
                ['node1', 'local', 'local:backup/vzdump-qemu-100-2023_01_01-00_00_00.vma.zst'],
                ['node1', 'local', 'local:backup/vzdump-qemu-101-2023_01_01-00_00_00.vma.zst']
            )
            ->willReturn(['success' => true]);
        
        $cleanupTask = new BatchBackupTask($this->clientMock);
        $cleanupResults = $cleanupTask->cleanupOldBackups(2); // 保留最新的2个
        
        // 验证清理结果
        $this->assertArrayHasKey(100, $cleanupResults);
        $this->assertArrayHasKey(101, $cleanupResults);
        $this->assertCount(1, $cleanupResults[100]);
        $this->assertCount(1, $cleanupResults[101]);
    }

    /**
     * 测试批量克隆虚拟机的场景
     */
    public function testBatchCloneVMs()
    {
        $node = 'node1';
        $sourceVmid = 100;
        $targetVmids = [200, 201];
        
        // 模拟克隆操作
        $cloneTask = $this->getMockBuilder(BatchVMOperationTask::class)
            ->setConstructorArgs([$this->clientMock, [
                'action' => BatchVMOperationTask::ACTION_CLONE,
                'node' => $node,
                'vmids' => $targetVmids,
                'params' => [
                    'source_vmid' => $sourceVmid,
                    'full' => 1,
                    'description' => '从模板克隆的虚拟机',
                ],
            ]])
            ->onlyMethods(['getTargetVMs', 'startAction', 'waitForTask'])
            ->getMock();
        
        // 模拟目标虚拟机列表
        $vms = [
            ['vmid' => 200, 'node' => $node, 'name' => 'clone-1', 'status' => 'stopped'],
            ['vmid' => 201, 'node' => $node, 'name' => 'clone-2', 'status' => 'stopped'],
        ];
        
        $cloneTask->expects($this->once())
            ->method('getTargetVMs')
            ->willReturn($vms);
            
        $cloneTask->expects($this->exactly(2))
            ->method('startAction')
            ->willReturnMap([
                [$vms[0], ['upid' => 'UPID:node1:00000000:00000000:00000000:clone:200:']],
                [$vms[1], ['upid' => 'UPID:node1:00000000:00000000:00000000:clone:201:']],
            ]);
            
        $cloneTask->expects($this->exactly(2))
            ->method('waitForTask')
            ->willReturnMap([
                [$node, 'UPID:node1:00000000:00000000:00000000:clone:200:', 300, 2, ['status' => 'stopped', 'exitstatus' => 'OK']],
                [$node, 'UPID:node1:00000000:00000000:00000000:clone:201:', 300, 2, ['status' => 'stopped', 'exitstatus' => 'OK']],
            ]);
        
        $results = $cloneTask->execute();
        
        // 验证克隆结果
        $this->assertCount(2, $results);
        $this->assertEquals(['status' => 'stopped', 'exitstatus' => 'OK'], $results[200]);
        $this->assertEquals(['status' => 'stopped', 'exitstatus' => 'OK'], $results[201]);
    }

    /**
     * 测试批量创建快照的场景
     */
    public function testBatchCreateSnapshots()
    {
        $node = 'node1';
        $vmids = [100, 101];
        $snapname = 'backup-' . date('Y-m-d');
        
        // 模拟创建快照操作
        $snapshotTask = $this->getMockBuilder(BatchVMOperationTask::class)
            ->setConstructorArgs([$this->clientMock, [
                'action' => BatchVMOperationTask::ACTION_SNAPSHOT,
                'node' => $node,
                'vmids' => $vmids,
                'params' => [
                    'snapname' => $snapname,
                    'description' => '自动创建的备份快照',
                ],
            ]])
            ->onlyMethods(['getTargetVMs', 'startAction', 'waitForTask'])
            ->getMock();
        
        // 模拟虚拟机列表
        $vms = [
            ['vmid' => 100, 'node' => $node, 'name' => 'vm1', 'status' => 'running'],
            ['vmid' => 101, 'node' => $node, 'name' => 'vm2', 'status' => 'running'],
        ];
        
        $snapshotTask->expects($this->once())
            ->method('getTargetVMs')
            ->willReturn($vms);
            
        $snapshotTask->expects($this->exactly(2))
            ->method('startAction')
            ->willReturnMap([
                [$vms[0], ['upid' => 'UPID:node1:00000000:00000000:00000000:snapshot:100:']],
                [$vms[1], ['upid' => 'UPID:node1:00000000:00000000:00000000:snapshot:101:']],
            ]);
            
        $snapshotTask->expects($this->exactly(2))
            ->method('waitForTask')
            ->willReturnMap([
                [$node, 'UPID:node1:00000000:00000000:00000000:snapshot:100:', 300, 2, ['status' => 'stopped', 'exitstatus' => 'OK']],
                [$node, 'UPID:node1:00000000:00000000:00000000:snapshot:101:', 300, 2, ['status' => 'stopped', 'exitstatus' => 'OK']],
            ]);
        
        $results = $snapshotTask->execute();
        
        // 验证快照创建结果
        $this->assertCount(2, $results);
        $this->assertEquals(['status' => 'stopped', 'exitstatus' => 'OK'], $results[100]);
        $this->assertEquals(['status' => 'stopped', 'exitstatus' => 'OK'], $results[101]);
    }
} 