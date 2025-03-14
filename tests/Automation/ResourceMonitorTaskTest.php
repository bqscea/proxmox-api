<?php

namespace Tests\Automation;

use PHPUnit\Framework\TestCase;
use ProxmoxApi\Client;
use ProxmoxApi\Automation\ResourceMonitorTask;
use ProxmoxApi\Exception\ProxmoxApiException;

class ResourceMonitorTaskTest extends TestCase
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
        $task = new ResourceMonitorTask($this->clientMock);
        
        $reflection = new \ReflectionClass($task);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($task);
        
        $this->assertNull($config['node']);
        $this->assertEmpty($config['vmids']);
        $this->assertEmpty($config['filters']);
        $this->assertEquals([ResourceMonitorTask::RESOURCE_ALL], $config['resources']);
        $this->assertEquals(80, $config['threshold_cpu']);
        $this->assertEquals(80, $config['threshold_memory']);
        $this->assertEquals(80, $config['threshold_disk']);
        $this->assertNull($config['threshold_network']);
        $this->assertEquals(3600, $config['timeframe']);
        $this->assertEquals(60, $config['interval']);
        $this->assertEquals(10, $config['samples']);
        $this->assertTrue($config['alert_on_threshold']);
        $this->assertEmpty($config['alert_handlers']);
    }

    public function testConstructorWithCustomConfig()
    {
        $customConfig = [
            'node' => 'node1',
            'vmids' => [100, 101],
            'resources' => [ResourceMonitorTask::RESOURCE_CPU, ResourceMonitorTask::RESOURCE_MEMORY],
            'threshold_cpu' => 90,
            'threshold_memory' => 85,
            'interval' => 30,
            'samples' => 5,
        ];
        
        $task = new ResourceMonitorTask($this->clientMock, $customConfig);
        
        $reflection = new \ReflectionClass($task);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($task);
        
        $this->assertEquals('node1', $config['node']);
        $this->assertEquals([100, 101], $config['vmids']);
        $this->assertEquals([ResourceMonitorTask::RESOURCE_CPU, ResourceMonitorTask::RESOURCE_MEMORY], $config['resources']);
        $this->assertEquals(90, $config['threshold_cpu']);
        $this->assertEquals(85, $config['threshold_memory']);
        $this->assertEquals(30, $config['interval']);
        $this->assertEquals(5, $config['samples']);
    }

    public function testValidateConfigWithInvalidResource()
    {
        $task = new ResourceMonitorTask($this->clientMock, [
            'resources' => ['invalid_resource'],
        ]);
        
        $reflection = new \ReflectionClass($task);
        $validateConfigMethod = $reflection->getMethod('validateConfig');
        $validateConfigMethod->setAccessible(true);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('无效的资源类型: invalid_resource');
        
        $validateConfigMethod->invoke($task);
    }

    public function testValidateConfigWithInvalidInterval()
    {
        $task = new ResourceMonitorTask($this->clientMock, [
            'interval' => 5,
        ]);
        
        $reflection = new \ReflectionClass($task);
        $validateConfigMethod = $reflection->getMethod('validateConfig');
        $validateConfigMethod->setAccessible(true);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('监控间隔不能小于10秒');
        
        $validateConfigMethod->invoke($task);
    }

    public function testValidateConfigWithInvalidSamples()
    {
        $task = new ResourceMonitorTask($this->clientMock, [
            'samples' => 0,
        ]);
        
        $reflection = new \ReflectionClass($task);
        $validateConfigMethod = $reflection->getMethod('validateConfig');
        $validateConfigMethod->setAccessible(true);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('样本数量不能小于1');
        
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
        
        $task = new ResourceMonitorTask($this->clientMock, [
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

    public function testCollectSample()
    {
        $node = 'node1';
        $vmid = 100;
        
        $vmStatus = [
            'vmid' => $vmid,
            'name' => 'vm1',
            'status' => 'running',
            'cpu' => 0.5, // 50%
            'mem' => 1024 * 1024 * 1024, // 1GB
            'maxmem' => 2 * 1024 * 1024 * 1024, // 2GB
        ];
        
        $vmConfig = [
            'scsi0' => 'local:vm-100-disk-0,size=10G',
            'sata0' => 'local:vm-100-disk-1,size=20G',
        ];
        
        $rrdData = [
            [
                'time' => time(),
                'netin' => 1024 * 1024, // 1MB
                'netout' => 512 * 1024, // 512KB
            ],
        ];
        
        $this->clientMock->expects($this->once())
            ->method('getVMStatus')
            ->with($node, $vmid)
            ->willReturn($vmStatus);
            
        $this->clientMock->expects($this->once())
            ->method('get')
            ->with("nodes/{$node}/qemu/{$vmid}/config")
            ->willReturn($this->clientMock);
            
        $this->clientMock->expects($this->once())
            ->method('toArray')
            ->willReturn($vmConfig);
            
        $this->clientMock->expects($this->once())
            ->method('get')
            ->with("nodes/{$node}/qemu/{$vmid}/rrddata", [
                'timeframe' => 'hour',
                'cf' => 'AVERAGE',
            ])
            ->willReturn($this->clientMock);
            
        $this->clientMock->expects($this->once())
            ->method('toArray')
            ->willReturn($rrdData);
        
        $task = new ResourceMonitorTask($this->clientMock, [
            'resources' => [ResourceMonitorTask::RESOURCE_ALL],
        ]);
        
        $reflection = new \ReflectionClass($task);
        $collectSampleMethod = $reflection->getMethod('collectSample');
        $collectSampleMethod->setAccessible(true);
        
        $sample = $collectSampleMethod->invoke($task, $node, $vmid);
        
        $this->assertArrayHasKey('timestamp', $sample);
        $this->assertArrayHasKey('cpu', $sample);
        $this->assertArrayHasKey('memory', $sample);
        $this->assertArrayHasKey('disks', $sample);
        $this->assertArrayHasKey('network_in', $sample);
        $this->assertArrayHasKey('network_out', $sample);
        
        $this->assertEquals(50, $sample['cpu']);
        $this->assertEquals(50, $sample['memory']);
        $this->assertEquals(1024 * 1024, $sample['network_in']);
        $this->assertEquals(512 * 1024, $sample['network_out']);
        $this->assertEquals(1024 * 1024 + 512 * 1024, $sample['network_total']);
    }

    public function testParseDiskString()
    {
        $task = new ResourceMonitorTask($this->clientMock);
        
        $reflection = new \ReflectionClass($task);
        $parseDiskStringMethod = $reflection->getMethod('parseDiskString');
        $parseDiskStringMethod->setAccessible(true);
        
        $result = $parseDiskStringMethod->invoke($task, 'local:vm-100-disk-0,size=10G');
        $this->assertEquals('10G', $result['size']);
        $this->assertNull($result['used']);
        
        $result = $parseDiskStringMethod->invoke($task, 'local:vm-100-disk-0,size=500M');
        $this->assertEquals('500M', $result['size']);
        
        $result = $parseDiskStringMethod->invoke($task, 'local:vm-100-disk-0');
        $this->assertNull($result);
    }

    public function testConvertSizeToBytes()
    {
        $task = new ResourceMonitorTask($this->clientMock);
        
        $reflection = new \ReflectionClass($task);
        $convertSizeToBytesMethod = $reflection->getMethod('convertSizeToBytes');
        $convertSizeToBytesMethod->setAccessible(true);
        
        $this->assertEquals(10 * 1024 * 1024 * 1024, $convertSizeToBytesMethod->invoke($task, '10G'));
        $this->assertEquals(500 * 1024 * 1024, $convertSizeToBytesMethod->invoke($task, '500M'));
        $this->assertEquals(2 * 1024, $convertSizeToBytesMethod->invoke($task, '2K'));
        $this->assertEquals(1 * 1024 * 1024 * 1024 * 1024, $convertSizeToBytesMethod->invoke($task, '1T'));
        $this->assertEquals(123, $convertSizeToBytesMethod->invoke($task, '123'));
    }

    public function testCheckThresholds()
    {
        $vmid = 100;
        $sample = [
            'timestamp' => time(),
            'cpu' => 90,
            'memory' => 85,
            'disk' => 75,
            'network_total' => 10 * 1024 * 1024, // 10MB
        ];
        
        $task = new ResourceMonitorTask($this->clientMock, [
            'threshold_cpu' => 80,
            'threshold_memory' => 80,
            'threshold_disk' => 80,
            'threshold_network' => 5 * 1024 * 1024, // 5MB
        ]);
        
        $reflection = new \ReflectionClass($task);
        $checkThresholdsMethod = $reflection->getMethod('checkThresholds');
        $checkThresholdsMethod->setAccessible(true);
        
        $alerts = $checkThresholdsMethod->invoke($task, $sample, $vmid);
        
        $this->assertCount(3, $alerts);
        
        $this->assertEquals(ResourceMonitorTask::RESOURCE_CPU, $alerts[0]['resource']);
        $this->assertEquals(90, $alerts[0]['value']);
        $this->assertEquals(80, $alerts[0]['threshold']);
        
        $this->assertEquals(ResourceMonitorTask::RESOURCE_MEMORY, $alerts[1]['resource']);
        $this->assertEquals(85, $alerts[1]['value']);
        $this->assertEquals(80, $alerts[1]['threshold']);
        
        $this->assertEquals(ResourceMonitorTask::RESOURCE_NETWORK, $alerts[2]['resource']);
        $this->assertEquals(10 * 1024 * 1024, $alerts[2]['value']);
        $this->assertEquals(5 * 1024 * 1024, $alerts[2]['threshold']);
    }

    public function testCalculateAverages()
    {
        $samples = [
            [
                'cpu' => 80,
                'memory' => 70,
                'disk' => 60,
                'network_in' => 1024 * 1024,
                'network_out' => 512 * 1024,
                'network_total' => 1536 * 1024,
            ],
            [
                'cpu' => 90,
                'memory' => 80,
                'disk' => 70,
                'network_in' => 2048 * 1024,
                'network_out' => 1024 * 1024,
                'network_total' => 3072 * 1024,
            ],
        ];
        
        $task = new ResourceMonitorTask($this->clientMock);
        
        $reflection = new \ReflectionClass($task);
        $calculateAveragesMethod = $reflection->getMethod('calculateAverages');
        $calculateAveragesMethod->setAccessible(true);
        
        $averages = $calculateAveragesMethod->invoke($task, $samples);
        
        $this->assertEquals(85, $averages['cpu']);
        $this->assertEquals(75, $averages['memory']);
        $this->assertEquals(65, $averages['disk']);
        $this->assertEquals(1536 * 1024, $averages['network_in']);
        $this->assertEquals(768 * 1024, $averages['network_out']);
        $this->assertEquals(2304 * 1024, $averages['network_total']);
    }

    public function testHandleAlerts()
    {
        $vmid = 100;
        $vm = [
            'vmid' => $vmid,
            'name' => 'vm1',
            'status' => 'running',
            'node' => 'node1',
        ];
        
        $alerts = [
            [
                'resource' => ResourceMonitorTask::RESOURCE_CPU,
                'vmid' => $vmid,
                'value' => 90,
                'threshold' => 80,
                'timestamp' => time(),
                'message' => "虚拟机 {$vmid} 的CPU使用率 (90%) 超过阈值 (80%)",
            ],
        ];
        
        $handlerCalled = false;
        $handler = function ($handlerAlerts, $handlerVm, $handlerTask) use (&$handlerCalled, $alerts, $vm) {
            $handlerCalled = true;
            $this->assertEquals($alerts, $handlerAlerts);
            $this->assertEquals($vm, $handlerVm);
            $this->assertInstanceOf(ResourceMonitorTask::class, $handlerTask);
        };
        
        $task = new ResourceMonitorTask($this->clientMock, [
            'alert_handlers' => [$handler],
        ]);
        
        $reflection = new \ReflectionClass($task);
        $handleAlertsMethod = $reflection->getMethod('handleAlerts');
        $handleAlertsMethod->setAccessible(true);
        
        $handleAlertsMethod->invoke($task, $alerts, $vm);
        
        $this->assertTrue($handlerCalled);
    }

    public function testAddAlertHandler()
    {
        $handler = function ($alerts, $vm, $task) {
            // 测试处理器
        };
        
        $task = new ResourceMonitorTask($this->clientMock);
        $result = $task->addAlertHandler($handler);
        
        $this->assertSame($task, $result);
        
        $reflection = new \ReflectionClass($task);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($task);
        
        $this->assertCount(1, $config['alert_handlers']);
        $this->assertSame($handler, $config['alert_handlers'][0]);
    }

    public function testCreateEmailAlertHandler()
    {
        $handler = ResourceMonitorTask::createEmailAlertHandler('admin@example.com', '测试警报', 'test@example.com');
        
        $this->assertIsCallable($handler);
    }

    public function testCreateLogAlertHandler()
    {
        $handler = ResourceMonitorTask::createLogAlertHandler('/tmp/alerts.log');
        
        $this->assertIsCallable($handler);
    }

    public function testCreateAutoScaleHandler()
    {
        $handler = ResourceMonitorTask::createAutoScaleHandler([
            'cpu_increment' => 2,
            'memory_increment' => 2048,
            'max_cpu' => 8,
            'max_memory' => 16384,
        ]);
        
        $this->assertIsCallable($handler);
    }

    public function testMonitorVM()
    {
        $node = 'node1';
        $vmid = 100;
        $vm = [
            'vmid' => $vmid,
            'name' => 'vm1',
            'status' => 'running',
            'node' => $node,
        ];
        
        $sample = [
            'timestamp' => time(),
            'cpu' => 90,
            'memory' => 85,
            'disk' => 75,
            'network_total' => 10 * 1024 * 1024,
        ];
        
        $alerts = [
            [
                'resource' => ResourceMonitorTask::RESOURCE_CPU,
                'vmid' => $vmid,
                'value' => 90,
                'threshold' => 80,
                'timestamp' => time(),
                'message' => "虚拟机 {$vmid} 的CPU使用率 (90%) 超过阈值 (80%)",
            ],
        ];
        
        $averages = [
            'cpu' => 90,
            'memory' => 85,
            'disk' => 75,
            'network_total' => 10 * 1024 * 1024,
        ];
        
        $task = $this->getMockBuilder(ResourceMonitorTask::class)
            ->setConstructorArgs([$this->clientMock, [
                'samples' => 1,
                'interval' => 10,
                'alert_on_threshold' => true,
            ]])
            ->onlyMethods(['collectSample', 'checkThresholds', 'calculateAverages', 'handleAlerts'])
            ->getMock();
            
        $task->expects($this->once())
            ->method('collectSample')
            ->with($node, $vmid)
            ->willReturn($sample);
            
        $task->expects($this->once())
            ->method('checkThresholds')
            ->with($sample, $vmid)
            ->willReturn($alerts);
            
        $task->expects($this->once())
            ->method('calculateAverages')
            ->with([$sample])
            ->willReturn($averages);
            
        $task->expects($this->once())
            ->method('handleAlerts')
            ->with($alerts, $vm);
        
        $reflection = new \ReflectionClass($task);
        $monitorVMMethod = $reflection->getMethod('monitorVM');
        $monitorVMMethod->setAccessible(true);
        
        $monitorVMMethod->invoke($task, $vm);
        
        $resultsProperty = $reflection->getProperty('results');
        $resultsProperty->setAccessible(true);
        $results = $resultsProperty->getValue($task);
        
        $this->assertArrayHasKey($vmid, $results);
        $this->assertEquals([$sample], $results[$vmid]['samples']);
        $this->assertEquals($averages, $results[$vmid]['averages']);
        $this->assertEquals($alerts, $results[$vmid]['alerts']);
    }
} 