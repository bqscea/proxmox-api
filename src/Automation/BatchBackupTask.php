<?php

namespace ProxmoxApi\Automation;

use ProxmoxApi\Client;
use ProxmoxApi\Exception\ProxmoxApiException;

/**
 * 批量备份自动化任务
 */
class BatchBackupTask extends AutomationTask
{
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
            'all' => false,
            'storage' => 'local',
            'mode' => 'snapshot',
            'compress' => 'zstd',
            'remove' => 0,
            'schedule' => null,
            'max_backups' => null,
            'exclude_vms' => [],
            'mail_notification' => null,
            'mail_to' => null,
            'timeout' => 3600,
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
        $this->log("开始执行批量备份任务");

        // 如果指定了所有虚拟机，则直接执行备份
        if ($this->config['all']) {
            return $this->backupAllVMs();
        }

        // 获取要备份的虚拟机列表
        $vms = $this->getTargetVMs();
        
        if (empty($vms)) {
            $this->log("没有找到符合条件的虚拟机", 'warning');
            return $this->results;
        }
        
        $this->log("找到 " . count($vms) . " 个虚拟机需要备份");
        
        // 执行备份
        return $this->backupVMs($vms);
    }

    /**
     * 验证配置
     *
     * @throws \InvalidArgumentException
     */
    private function validateConfig(): void
    {
        if (!$this->config['all'] && empty($this->config['vmids']) && empty($this->config['filters'])) {
            throw new \InvalidArgumentException("必须指定 'all'、'vmids' 或 'filters' 参数之一");
        }

        if ($this->config['schedule'] && !$this->isValidCronExpression($this->config['schedule'])) {
            throw new \InvalidArgumentException("无效的计划表达式: {$this->config['schedule']}");
        }
    }

    /**
     * 验证Cron表达式
     *
     * @param string $expression Cron表达式
     * @return bool
     */
    private function isValidCronExpression(string $expression): bool
    {
        // 简单验证，实际应用中可能需要更复杂的验证
        $parts = explode(' ', trim($expression));
        return count($parts) >= 5;
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
                    if (in_array($vm['vmid'], $this->config['vmids']) && !in_array($vm['vmid'], $this->config['exclude_vms'])) {
                        $vm['node'] = $this->config['node'];
                        $vms[] = $vm;
                    }
                }
            } else {
                // 如果没有指定节点，则获取所有节点上的虚拟机
                $allVMs = $this->getAllFilteredVMs();
                foreach ($allVMs as $vm) {
                    if (in_array($vm['vmid'], $this->config['vmids']) && !in_array($vm['vmid'], $this->config['exclude_vms'])) {
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
                    if (!in_array($vm['vmid'], $this->config['exclude_vms'])) {
                        $vm['node'] = $this->config['node'];
                        $vms[] = $vm;
                    }
                }
            } else {
                // 如果没有指定节点，则获取所有节点上的虚拟机
                $allVMs = $this->getAllFilteredVMs($this->config['filters']);
                foreach ($allVMs as $vm) {
                    if (!in_array($vm['vmid'], $this->config['exclude_vms'])) {
                        $vms[] = $vm;
                    }
                }
            }
        }

        return $vms;
    }

    /**
     * 备份所有虚拟机
     *
     * @return array
     * @throws ProxmoxApiException
     */
    private function backupAllVMs(): array
    {
        $this->log("开始备份所有虚拟机");
        
        $params = $this->buildBackupParams(true);
        
        if ($this->config['node']) {
            // 如果指定了节点，则只备份该节点上的虚拟机
            $this->log("在节点 {$this->config['node']} 上执行备份");
            $task = $this->client->backup->createAllBackup($this->config['node'], $params);
            
            if (isset($task['upid'])) {
                $taskStatus = $this->waitForTask($this->config['node'], $task['upid'], $this->config['timeout']);
                $this->results['all'] = $taskStatus;
            } else {
                $this->results['all'] = $task;
            }
        } else {
            // 如果没有指定节点，则备份所有节点上的虚拟机
            $nodes = $this->client->getNodes();
            foreach ($nodes as $node) {
                $nodeName = $node['node'];
                $this->log("在节点 {$nodeName} 上执行备份");
                
                try {
                    $task = $this->client->backup->createAllBackup($nodeName, $params);
                    
                    if (isset($task['upid'])) {
                        $taskStatus = $this->waitForTask($nodeName, $task['upid'], $this->config['timeout']);
                        $this->results[$nodeName] = $taskStatus;
                    } else {
                        $this->results[$nodeName] = $task;
                    }
                } catch (\Exception $e) {
                    $this->log("在节点 {$nodeName} 上执行备份失败: " . $e->getMessage(), 'error');
                    $this->results[$nodeName] = ['error' => $e->getMessage()];
                }
            }
        }
        
        $this->log("所有虚拟机备份任务完成");
        return $this->results;
    }

    /**
     * 备份指定虚拟机
     *
     * @param array $vms 虚拟机列表
     * @return array
     * @throws ProxmoxApiException
     */
    private function backupVMs(array $vms): array
    {
        // 按节点分组虚拟机
        $vmsByNode = [];
        foreach ($vms as $vm) {
            $nodeName = $vm['node'];
            if (!isset($vmsByNode[$nodeName])) {
                $vmsByNode[$nodeName] = [];
            }
            $vmsByNode[$nodeName][] = $vm;
        }
        
        // 对每个节点执行批量备份
        foreach ($vmsByNode as $nodeName => $nodeVMs) {
            $this->log("在节点 {$nodeName} 上备份 " . count($nodeVMs) . " 个虚拟机");
            
            $vmids = array_map(function ($vm) {
                return $vm['vmid'];
            }, $nodeVMs);
            
            $params = $this->buildBackupParams(false);
            
            try {
                $task = $this->client->backup->createBatchBackup($nodeName, $vmids, $params);
                
                if (isset($task['upid'])) {
                    $taskStatus = $this->waitForTask($nodeName, $task['upid'], $this->config['timeout']);
                    $this->results[$nodeName] = $taskStatus;
                } else {
                    $this->results[$nodeName] = $task;
                }
            } catch (\Exception $e) {
                $this->log("在节点 {$nodeName} 上执行备份失败: " . $e->getMessage(), 'error');
                $this->results[$nodeName] = ['error' => $e->getMessage()];
            }
        }
        
        $this->log("批量备份任务完成");
        return $this->results;
    }

    /**
     * 构建备份参数
     *
     * @param bool $all 是否备份所有虚拟机
     * @return array
     */
    private function buildBackupParams(bool $all): array
    {
        $params = [
            'mode' => $this->config['mode'],
            'compress' => $this->config['compress'],
            'storage' => $this->config['storage'],
            'all' => $all ? 1 : 0,
            'remove' => $this->config['remove'],
        ];
        
        if ($this->config['mail_notification']) {
            $params['mailnotification'] = $this->config['mail_notification'];
        }
        
        if ($this->config['mail_to']) {
            $params['mailto'] = $this->config['mail_to'];
        }
        
        if ($this->config['schedule']) {
            $params['schedule'] = $this->config['schedule'];
        }
        
        if ($this->config['max_backups']) {
            $params['maxfiles'] = $this->config['max_backups'];
        }
        
        if (!empty($this->config['exclude_vms'])) {
            $params['exclude'] = implode(',', $this->config['exclude_vms']);
        }
        
        return $params;
    }

    /**
     * 创建定时备份任务
     *
     * @return array
     * @throws ProxmoxApiException
     */
    public function createSchedule(): array
    {
        if (!$this->config['schedule']) {
            throw new \InvalidArgumentException("必须指定计划表达式");
        }
        
        $this->log("创建定时备份计划");
        
        $params = [
            'type' => 'vzdump',
            'enabled' => 1,
            'schedule' => $this->config['schedule'],
            'storage' => $this->config['storage'],
            'mode' => $this->config['mode'],
            'compress' => $this->config['compress'],
            'remove' => $this->config['remove'],
        ];
        
        if ($this->config['all']) {
            $params['all'] = 1;
        } elseif (!empty($this->config['vmids'])) {
            $params['vmid'] = implode(',', $this->config['vmids']);
        }
        
        if ($this->config['mail_notification']) {
            $params['mailnotification'] = $this->config['mail_notification'];
        }
        
        if ($this->config['mail_to']) {
            $params['mailto'] = $this->config['mail_to'];
        }
        
        if ($this->config['max_backups']) {
            $params['maxfiles'] = $this->config['max_backups'];
        }
        
        if (!empty($this->config['exclude_vms'])) {
            $params['exclude'] = implode(',', $this->config['exclude_vms']);
        }
        
        if ($this->config['node']) {
            $params['node'] = $this->config['node'];
        }
        
        try {
            $result = $this->client->post("cluster/backup", $params)->toArray();
            $this->log("定时备份计划创建成功");
            return $result;
        } catch (\Exception $e) {
            $this->log("创建定时备份计划失败: " . $e->getMessage(), 'error');
            throw new ProxmoxApiException("创建定时备份计划失败: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 清理旧备份
     *
     * @param int $keepCount 保留的备份数量
     * @return array
     */
    public function cleanupOldBackups(int $keepCount = 5): array
    {
        $this->log("开始清理旧备份，保留最新的 {$keepCount} 个备份");
        
        $cleanupResults = [];
        
        // 获取所有备份
        $allBackups = $this->client->getAllBackups();
        
        foreach ($allBackups as $nodeName => $backups) {
            if (is_array($backups) && !isset($backups['error'])) {
                $this->log("处理节点 {$nodeName} 的备份");
                
                // 按虚拟机ID分组备份
                $backupsByVmid = [];
                foreach ($backups as $backup) {
                    if (preg_match('/vzdump-qemu-(\d+)-/', $backup['volid'], $matches)) {
                        $vmid = (int)$matches[1];
                        
                        if (!isset($backupsByVmid[$vmid])) {
                            $backupsByVmid[$vmid] = [];
                        }
                        
                        $backupsByVmid[$vmid][] = $backup;
                    }
                }
                
                // 对每个虚拟机的备份进行排序和清理
                foreach ($backupsByVmid as $vmid => $vmBackups) {
                    // 按创建时间排序
                    usort($vmBackups, function ($a, $b) {
                        return $b['ctime'] - $a['ctime'];
                    });
                    
                    // 保留最新的 $keepCount 个备份，删除其余的
                    if (count($vmBackups) > $keepCount) {
                        $backupsToDelete = array_slice($vmBackups, $keepCount);
                        
                        $this->log("虚拟机 {$vmid} 有 " . count($vmBackups) . " 个备份，将删除 " . count($backupsToDelete) . " 个");
                        
                        foreach ($backupsToDelete as $backup) {
                            try {
                                $storage = $backup['storage'];
                                $volid = $backup['volid'];
                                
                                $this->log("删除备份: {$volid}");
                                $result = $this->client->backup->deleteBackup($nodeName, $storage, $volid);
                                
                                if (!isset($cleanupResults[$vmid])) {
                                    $cleanupResults[$vmid] = [];
                                }
                                
                                $cleanupResults[$vmid][] = [
                                    'volid' => $volid,
                                    'result' => $result,
                                ];
                            } catch (\Exception $e) {
                                $this->log("删除备份 {$backup['volid']} 失败: " . $e->getMessage(), 'error');
                                
                                if (!isset($cleanupResults[$vmid])) {
                                    $cleanupResults[$vmid] = [];
                                }
                                
                                $cleanupResults[$vmid][] = [
                                    'volid' => $backup['volid'],
                                    'error' => $e->getMessage(),
                                ];
                            }
                        }
                    } else {
                        $this->log("虚拟机 {$vmid} 有 " . count($vmBackups) . " 个备份，不需要清理");
                    }
                }
            } else {
                $this->log("获取节点 {$nodeName} 的备份失败", 'warning');
            }
        }
        
        $this->log("备份清理任务完成");
        return $cleanupResults;
    }
} 