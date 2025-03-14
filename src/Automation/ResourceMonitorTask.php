<?php

namespace ProxmoxApi\Automation;

use ProxmoxApi\Client;
use ProxmoxApi\Exception\ProxmoxApiException;

/**
 * 资源监控自动化任务
 */
class ResourceMonitorTask extends AutomationTask
{
    /**
     * 资源类型常量
     */
    const RESOURCE_CPU = 'cpu';
    const RESOURCE_MEMORY = 'memory';
    const RESOURCE_DISK = 'disk';
    const RESOURCE_NETWORK = 'network';
    const RESOURCE_ALL = 'all';

    /**
     * 构造函数
     *
     * @param Client $client API客户端
     * @param array $config 任务配置
     */
    public function __construct(Client $client, array $config = [])
    {
        $defaultConfig = [
            'node' => null,
            'vmids' => [],
            'filters' => [],
            'resources' => [self::RESOURCE_ALL],
            'threshold_cpu' => 80,
            'threshold_memory' => 80,
            'threshold_disk' => 80,
            'threshold_network' => null,
            'timeframe' => 3600,
            'interval' => 60,
            'samples' => 10,
            'alert_on_threshold' => true,
            'alert_handlers' => [],
        ];

        parent::__construct($client, array_merge($defaultConfig, $config));
    }

    /**
     * 执行任务
     *
     * @return array 任务结果
     * @throws ProxmoxApiException
     */
    public function execute(): array
    {
        $this->validateConfig();
        $this->log("开始执行资源监控任务");

        // 获取要监控的虚拟机列表
        $vms = $this->getTargetVMs();
        
        if (empty($vms)) {
            $this->log("没有找到符合条件的虚拟机", 'warning');
            return $this->results;
        }
        
        $this->log("找到 " . count($vms) . " 个虚拟机需要监控");
        
        // 执行监控
        foreach ($vms as $vm) {
            try {
                $this->monitorVM($vm);
            } catch (\Exception $e) {
                $this->log("监控虚拟机 {$vm['vmid']} 失败: " . $e->getMessage(), 'error');
                $this->results[$vm['vmid']] = ['error' => $e->getMessage()];
            }
        }
        
        $this->log("资源监控任务完成");
        return $this->results;
    }

    /**
     * 验证配置
     *
     * @throws \InvalidArgumentException
     */
    private function validateConfig(): void
    {
        $validResources = [
            self::RESOURCE_CPU,
            self::RESOURCE_MEMORY,
            self::RESOURCE_DISK,
            self::RESOURCE_NETWORK,
            self::RESOURCE_ALL,
        ];

        foreach ($this->config['resources'] as $resource) {
            if (!in_array($resource, $validResources)) {
                throw new \InvalidArgumentException("无效的资源类型: {$resource}");
            }
        }

        if ($this->config['interval'] < 10) {
            throw new \InvalidArgumentException("监控间隔不能小于10秒");
        }

        if ($this->config['samples'] < 1) {
            throw new \InvalidArgumentException("样本数量不能小于1");
        }
    }

    /**
     * 获取目标虚拟机列表
     *
     * @return array
     */
    private function getTargetVMs(): array
    {
        $vms = [];

        // 如果指定了VMID列表，则直接使用
        if (!empty($this->config['vmids'])) {
            if ($this->config['node']) {
                // 如果指定了节点，则只获取该节点上的虚拟机
                $nodeVMs = $this->getFilteredVMs($this->config['node']);
                foreach ($nodeVMs as $vm) {
                    if (in_array($vm['vmid'], $this->config['vmids'])) {
                        $vm['node'] = $this->config['node'];
                        $vms[] = $vm;
                    }
                }
            } else {
                // 如果没有指定节点，则获取所有节点上的虚拟机
                $allVMs = $this->getAllFilteredVMs();
                foreach ($allVMs as $vm) {
                    if (in_array($vm['vmid'], $this->config['vmids'])) {
                        $vms[] = $vm;
                    }
                }
            }
        } else {
            // 如果没有指定VMID列表，则使用过滤条件
            if ($this->config['node']) {
                // 如果指定了节点，则只获取该节点上的虚拟机
                $nodeVMs = $this->getFilteredVMs($this->config['node'], $this->config['filters']);
                foreach ($nodeVMs as $vm) {
                    $vm['node'] = $this->config['node'];
                    $vms[] = $vm;
                }
            } else {
                // 如果没有指定节点，则获取所有节点上的虚拟机
                $vms = $this->getAllFilteredVMs($this->config['filters']);
            }
        }

        return $vms;
    }

    /**
     * 监控虚拟机资源
     *
     * @param array $vm 虚拟机信息
     * @return void
     * @throws ProxmoxApiException
     */
    private function monitorVM(array $vm): void
    {
        $node = $vm['vmid'];
        $vmid = $vm['vmid'];
        
        $this->log("开始监控虚拟机 {$vmid} 的资源使用情况");
        
        $monitorData = [];
        $alerts = [];
        
        // 收集样本
        for ($i = 0; $i < $this->config['samples']; $i++) {
            if ($i > 0) {
                sleep($this->config['interval']);
            }
            
            $this->log("收集虚拟机 {$vmid} 的第 " . ($i + 1) . " 个样本");
            
            $sample = $this->collectSample($node, $vmid);
            $monitorData[] = $sample;
            
            // 检查阈值
            if ($this->config['alert_on_threshold']) {
                $sampleAlerts = $this->checkThresholds($sample, $vmid);
                if (!empty($sampleAlerts)) {
                    $alerts = array_merge($alerts, $sampleAlerts);
                }
            }
        }
        
        // 计算平均值
        $averages = $this->calculateAverages($monitorData);
        
        $this->results[$vmid] = [
            'samples' => $monitorData,
            'averages' => $averages,
            'alerts' => $alerts,
        ];
        
        // 处理警报
        if (!empty($alerts) && !empty($this->config['alert_handlers'])) {
            $this->handleAlerts($alerts, $vm);
        }
        
        $this->log("虚拟机 {$vmid} 的资源监控完成");
    }

    /**
     * 收集资源样本
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机ID
     * @return array
     * @throws ProxmoxApiException
     */
    private function collectSample(string $node, int $vmid): array
    {
        $sample = [
            'timestamp' => time(),
        ];
        
        // 获取虚拟机状态
        $status = $this->client->getVMStatus($node, $vmid);
        
        // 收集CPU使用率
        if (in_array(self::RESOURCE_CPU, $this->config['resources']) || in_array(self::RESOURCE_ALL, $this->config['resources'])) {
            $sample['cpu'] = isset($status['cpu']) ? $status['cpu'] * 100 : 0;
        }
        
        // 收集内存使用率
        if (in_array(self::RESOURCE_MEMORY, $this->config['resources']) || in_array(self::RESOURCE_ALL, $this->config['resources'])) {
            if (isset($status['mem']) && isset($status['maxmem']) && $status['maxmem'] > 0) {
                $sample['memory'] = ($status['mem'] / $status['maxmem']) * 100;
                $sample['memory_used'] = $status['mem'];
                $sample['memory_total'] = $status['maxmem'];
            } else {
                $sample['memory'] = 0;
                $sample['memory_used'] = 0;
                $sample['memory_total'] = 0;
            }
        }
        
        // 收集磁盘使用率
        if (in_array(self::RESOURCE_DISK, $this->config['resources']) || in_array(self::RESOURCE_ALL, $this->config['resources'])) {
            $config = $this->client->get("nodes/{$node}/qemu/{$vmid}/config")->toArray();
            $disks = [];
            
            foreach ($config as $key => $value) {
                if (preg_match('/^(scsi|sata|ide|virtio)(\d+)$/', $key, $matches)) {
                    $diskInfo = $this->parseDiskString($value);
                    if ($diskInfo && isset($diskInfo['size'])) {
                        $disks[$key] = $diskInfo;
                    }
                }
            }
            
            $sample['disks'] = $disks;
            
            // 计算总磁盘使用率（如果可能）
            $totalSize = 0;
            $totalUsed = 0;
            
            foreach ($disks as $disk) {
                if (isset($disk['size'])) {
                    $totalSize += $this->convertSizeToBytes($disk['size']);
                }
                if (isset($disk['used'])) {
                    $totalUsed += $this->convertSizeToBytes($disk['used']);
                }
            }
            
            if ($totalSize > 0) {
                $sample['disk'] = ($totalUsed / $totalSize) * 100;
                $sample['disk_used'] = $totalUsed;
                $sample['disk_total'] = $totalSize;
            } else {
                $sample['disk'] = 0;
                $sample['disk_used'] = 0;
                $sample['disk_total'] = 0;
            }
        }
        
        // 收集网络使用率
        if (in_array(self::RESOURCE_NETWORK, $this->config['resources']) || in_array(self::RESOURCE_ALL, $this->config['resources'])) {
            $rrdData = $this->client->get("nodes/{$node}/qemu/{$vmid}/rrddata", [
                'timeframe' => 'hour',
                'cf' => 'AVERAGE',
            ])->toArray();
            
            if (!empty($rrdData)) {
                $lastData = end($rrdData);
                
                $sample['network_in'] = isset($lastData['netin']) ? $lastData['netin'] : 0;
                $sample['network_out'] = isset($lastData['netout']) ? $lastData['netout'] : 0;
                $sample['network_total'] = $sample['network_in'] + $sample['network_out'];
            } else {
                $sample['network_in'] = 0;
                $sample['network_out'] = 0;
                $sample['network_total'] = 0;
            }
        }
        
        return $sample;
    }

    /**
     * 解析磁盘字符串
     *
     * @param string $diskString 磁盘配置字符串
     * @return array|null
     */
    private function parseDiskString(string $diskString): ?array
    {
        if (preg_match('/size=(\d+[KMGT]?)/', $diskString, $matches)) {
            $size = $matches[1];
            
            return [
                'size' => $size,
                'used' => null, // 无法直接从配置获取使用量
            ];
        }
        
        return null;
    }

    /**
     * 将大小字符串转换为字节数
     *
     * @param string $size 大小字符串（如 10G, 500M 等）
     * @return int
     */
    private function convertSizeToBytes(string $size): int
    {
        $size = strtoupper($size);
        $unit = substr($size, -1);
        $value = (int)substr($size, 0, -1);
        
        switch ($unit) {
            case 'T':
                return $value * 1024 * 1024 * 1024 * 1024;
            case 'G':
                return $value * 1024 * 1024 * 1024;
            case 'M':
                return $value * 1024 * 1024;
            case 'K':
                return $value * 1024;
            default:
                return (int)$size;
        }
    }

    /**
     * 检查资源阈值
     *
     * @param array $sample 资源样本
     * @param int $vmid 虚拟机ID
     * @return array
     */
    private function checkThresholds(array $sample, int $vmid): array
    {
        $alerts = [];
        
        // 检查CPU阈值
        if (isset($sample['cpu']) && $this->config['threshold_cpu'] !== null && $sample['cpu'] > $this->config['threshold_cpu']) {
            $alerts[] = [
                'resource' => self::RESOURCE_CPU,
                'vmid' => $vmid,
                'value' => $sample['cpu'],
                'threshold' => $this->config['threshold_cpu'],
                'timestamp' => $sample['timestamp'],
                'message' => "虚拟机 {$vmid} 的CPU使用率 ({$sample['cpu']}%) 超过阈值 ({$this->config['threshold_cpu']}%)",
            ];
        }
        
        // 检查内存阈值
        if (isset($sample['memory']) && $this->config['threshold_memory'] !== null && $sample['memory'] > $this->config['threshold_memory']) {
            $alerts[] = [
                'resource' => self::RESOURCE_MEMORY,
                'vmid' => $vmid,
                'value' => $sample['memory'],
                'threshold' => $this->config['threshold_memory'],
                'timestamp' => $sample['timestamp'],
                'message' => "虚拟机 {$vmid} 的内存使用率 ({$sample['memory']}%) 超过阈值 ({$this->config['threshold_memory']}%)",
            ];
        }
        
        // 检查磁盘阈值
        if (isset($sample['disk']) && $this->config['threshold_disk'] !== null && $sample['disk'] > $this->config['threshold_disk']) {
            $alerts[] = [
                'resource' => self::RESOURCE_DISK,
                'vmid' => $vmid,
                'value' => $sample['disk'],
                'threshold' => $this->config['threshold_disk'],
                'timestamp' => $sample['timestamp'],
                'message' => "虚拟机 {$vmid} 的磁盘使用率 ({$sample['disk']}%) 超过阈值 ({$this->config['threshold_disk']}%)",
            ];
        }
        
        // 检查网络阈值
        if (isset($sample['network_total']) && $this->config['threshold_network'] !== null && $sample['network_total'] > $this->config['threshold_network']) {
            $alerts[] = [
                'resource' => self::RESOURCE_NETWORK,
                'vmid' => $vmid,
                'value' => $sample['network_total'],
                'threshold' => $this->config['threshold_network'],
                'timestamp' => $sample['timestamp'],
                'message' => "虚拟机 {$vmid} 的网络使用量 ({$sample['network_total']} bytes/s) 超过阈值 ({$this->config['threshold_network']} bytes/s)",
            ];
        }
        
        return $alerts;
    }

    /**
     * 计算平均值
     *
     * @param array $samples 样本数组
     * @return array
     */
    private function calculateAverages(array $samples): array
    {
        if (empty($samples)) {
            return [];
        }
        
        $averages = [];
        $metrics = ['cpu', 'memory', 'disk', 'network_in', 'network_out', 'network_total'];
        
        foreach ($metrics as $metric) {
            $sum = 0;
            $count = 0;
            
            foreach ($samples as $sample) {
                if (isset($sample[$metric])) {
                    $sum += $sample[$metric];
                    $count++;
                }
            }
            
            if ($count > 0) {
                $averages[$metric] = $sum / $count;
            }
        }
        
        return $averages;
    }

    /**
     * 处理警报
     *
     * @param array $alerts 警报数组
     * @param array $vm 虚拟机信息
     * @return void
     */
    private function handleAlerts(array $alerts, array $vm): void
    {
        foreach ($this->config['alert_handlers'] as $handler) {
            if (is_callable($handler)) {
                try {
                    $handler($alerts, $vm, $this);
                } catch (\Exception $e) {
                    $this->log("处理警报失败: " . $e->getMessage(), 'error');
                }
            }
        }
    }

    /**
     * 添加警报处理器
     *
     * @param callable $handler 处理器函数
     * @return self
     */
    public function addAlertHandler(callable $handler): self
    {
        $this->config['alert_handlers'][] = $handler;
        return $this;
    }

    /**
     * 创建邮件警报处理器
     *
     * @param string $to 收件人
     * @param string $subject 主题
     * @param string $from 发件人
     * @return callable
     */
    public static function createEmailAlertHandler(string $to, string $subject = '虚拟机资源警报', string $from = 'proxmox@localhost'): callable
    {
        return function (array $alerts, array $vm, ResourceMonitorTask $task) use ($to, $subject, $from) {
            if (empty($alerts)) {
                return;
            }
            
            $body = "虚拟机 {$vm['vmid']} ({$vm['name']}) 资源警报:\n\n";
            
            foreach ($alerts as $alert) {
                $body .= "- " . $alert['message'] . "\n";
                $body .= "  时间: " . date('Y-m-d H:i:s', $alert['timestamp']) . "\n";
                $body .= "  值: " . $alert['value'] . "\n";
                $body .= "  阈值: " . $alert['threshold'] . "\n\n";
            }
            
            $headers = "From: {$from}\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            
            $task->log("发送警报邮件到 {$to}");
            mail($to, $subject, $body, $headers);
        };
    }

    /**
     * 创建日志警报处理器
     *
     * @param string $logFile 日志文件路径
     * @return callable
     */
    public static function createLogAlertHandler(string $logFile): callable
    {
        return function (array $alerts, array $vm, ResourceMonitorTask $task) use ($logFile) {
            if (empty($alerts)) {
                return;
            }
            
            $logEntry = "[" . date('Y-m-d H:i:s') . "] 虚拟机 {$vm['vmid']} ({$vm['name']}) 资源警报:\n";
            
            foreach ($alerts as $alert) {
                $logEntry .= "- " . $alert['message'] . "\n";
            }
            
            $logEntry .= "\n";
            
            $task->log("记录警报到日志文件 {$logFile}");
            file_put_contents($logFile, $logEntry, FILE_APPEND);
        };
    }

    /**
     * 创建自动扩容处理器
     *
     * @param array $options 扩容选项
     * @return callable
     */
    public static function createAutoScaleHandler(array $options = []): callable
    {
        $defaultOptions = [
            'cpu_increment' => 1,
            'memory_increment' => 1024, // MB
            'disk_increment' => 5, // GB
            'max_cpu' => 8,
            'max_memory' => 16384, // MB
            'max_disk' => 100, // GB
            'cooldown_period' => 3600, // 秒
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        return function (array $alerts, array $vm, ResourceMonitorTask $task) use ($options) {
            if (empty($alerts)) {
                return;
            }
            
            $node = $vm['node'];
            $vmid = $vm['vmid'];
            $client = $task->client;
            
            // 获取当前配置
            $config = $client->get("nodes/{$node}/qemu/{$vmid}/config")->toArray();
            $changed = false;
            $changes = [];
            
            foreach ($alerts as $alert) {
                switch ($alert['resource']) {
                    case ResourceMonitorTask::RESOURCE_CPU:
                        if (isset($config['sockets']) && isset($config['cores'])) {
                            $currentCpu = $config['sockets'] * $config['cores'];
                            $newCpu = min($currentCpu + $options['cpu_increment'], $options['max_cpu']);
                            
                            if ($newCpu > $currentCpu) {
                                // 简单起见，我们只增加插槽数量
                                $changes['sockets'] = ceil($newCpu / $config['cores']);
                                $changed = true;
                                $task->log("计划增加虚拟机 {$vmid} 的CPU从 {$currentCpu} 到 {$newCpu}");
                            }
                        }
                        break;
                        
                    case ResourceMonitorTask::RESOURCE_MEMORY:
                        if (isset($config['memory'])) {
                            $currentMemory = $config['memory'];
                            $newMemory = min($currentMemory + $options['memory_increment'], $options['max_memory']);
                            
                            if ($newMemory > $currentMemory) {
                                $changes['memory'] = $newMemory;
                                $changed = true;
                                $task->log("计划增加虚拟机 {$vmid} 的内存从 {$currentMemory}MB 到 {$newMemory}MB");
                            }
                        }
                        break;
                        
                    case ResourceMonitorTask::RESOURCE_DISK:
                        // 磁盘扩容比较复杂，需要找到主磁盘并扩容
                        // 这里只是一个简化的示例
                        foreach ($config as $key => $value) {
                            if (preg_match('/^(scsi|sata|ide|virtio)(\d+)$/', $key, $matches) && strpos($value, 'size=') !== false) {
                                if (preg_match('/size=(\d+)G/', $value, $sizeMatches)) {
                                    $currentSize = (int)$sizeMatches[1];
                                    $newSize = min($currentSize + $options['disk_increment'], $options['max_disk']);
                                    
                                    if ($newSize > $currentSize) {
                                        // 构建新的磁盘字符串，保留原有配置
                                        $newValue = preg_replace('/size=\d+G/', "size={$newSize}G", $value);
                                        $changes[$key] = $newValue;
                                        $changed = true;
                                        $task->log("计划增加虚拟机 {$vmid} 的磁盘 {$key} 从 {$currentSize}GB 到 {$newSize}GB");
                                    }
                                    
                                    // 只处理第一个找到的磁盘
                                    break;
                                }
                            }
                        }
                        break;
                }
            }
            
            // 应用更改
            if ($changed) {
                try {
                    $task->log("应用虚拟机 {$vmid} 的资源扩容");
                    $result = $client->put("nodes/{$node}/qemu/{$vmid}/config", $changes)->toArray();
                    $task->log("虚拟机 {$vmid} 资源扩容成功");
                    
                    // 如果虚拟机正在运行，可能需要重启才能应用某些更改
                    $status = $client->getVMStatus($node, $vmid);
                    if (isset($status['status']) && $status['status'] === 'running') {
                        $task->log("注意：某些更改可能需要重启虚拟机 {$vmid} 才能生效");
                    }
                    
                    return $result;
                } catch (\Exception $e) {
                    $task->log("虚拟机 {$vmid} 资源扩容失败: " . $e->getMessage(), 'error');
                }
            }
        };
    }
} 