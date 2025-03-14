<?php

namespace ProxmoxApi\Automation;

use ProxmoxApi\Client;
use ProxmoxApi\Exception\ProxmoxApiException;

/**
 * 批量虚拟机操作自动化任务
 */
class BatchVMOperationTask extends AutomationTask
{
    /**
     * 操作类型常量
     */
    const ACTION_START = 'start';
    const ACTION_STOP = 'stop';
    const ACTION_REBOOT = 'reboot';
    const ACTION_SUSPEND = 'suspend';
    const ACTION_RESUME = 'resume';
    const ACTION_BACKUP = 'backup';
    const ACTION_SNAPSHOT = 'snapshot';
    const ACTION_CLONE = 'clone';
    const ACTION_DELETE = 'delete';
    const ACTION_MIGRATE = 'migrate';

    /**
     * 构造函数
     *
     * @param Client $client API客户端
     * @param array $config 任务配置
     */
    public function __construct(Client $client, array $config = [])
    {
        $defaultConfig = [
            'action' => self::ACTION_START,
            'node' => null,
            'vmids' => [],
            'filters' => [],
            'params' => [],
            'parallel' => false,
            'max_parallel' => 5,
            'continue_on_error' => true,
            'timeout' => 300,
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
        $this->log("开始执行批量虚拟机操作任务: {$this->config['action']}");

        // 获取要操作的虚拟机列表
        $vms = $this->getTargetVMs();
        
        if (empty($vms)) {
            $this->log("没有找到符合条件的虚拟机", 'warning');
            return $this->results;
        }
        
        $this->log("找到 " . count($vms) . " 个虚拟机需要处理");
        
        // 执行操作
        if ($this->config['parallel']) {
            $this->executeParallel($vms);
        } else {
            $this->executeSequential($vms);
        }
        
        $this->log("批量虚拟机操作任务完成");
        return $this->results;
    }

    /**
     * 验证配置
     *
     * @throws \InvalidArgumentException
     */
    private function validateConfig(): void
    {
        $validActions = [
            self::ACTION_START,
            self::ACTION_STOP,
            self::ACTION_REBOOT,
            self::ACTION_SUSPEND,
            self::ACTION_RESUME,
            self::ACTION_BACKUP,
            self::ACTION_SNAPSHOT,
            self::ACTION_CLONE,
            self::ACTION_DELETE,
            self::ACTION_MIGRATE,
        ];

        if (!in_array($this->config['action'], $validActions)) {
            throw new \InvalidArgumentException("无效的操作类型: {$this->config['action']}");
        }

        if ($this->config['action'] === self::ACTION_CLONE && !isset($this->config['params']['source_vmid'])) {
            throw new \InvalidArgumentException("克隆操作需要指定源虚拟机ID (source_vmid)");
        }

        if ($this->config['action'] === self::ACTION_MIGRATE && !isset($this->config['params']['target_node'])) {
            throw new \InvalidArgumentException("迁移操作需要指定目标节点 (target_node)");
        }

        if ($this->config['action'] === self::ACTION_SNAPSHOT && !isset($this->config['params']['snapname'])) {
            throw new \InvalidArgumentException("快照操作需要指定快照名称 (snapname)");
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
     * 顺序执行操作
     *
     * @param array $vms 虚拟机列表
     */
    private function executeSequential(array $vms): void
    {
        foreach ($vms as $vm) {
            try {
                $result = $this->executeAction($vm);
                $this->results[$vm['vmid']] = $result;
            } catch (\Exception $e) {
                $this->log("处理虚拟机 {$vm['vmid']} 失败: " . $e->getMessage(), 'error');
                $this->results[$vm['vmid']] = ['error' => $e->getMessage()];
                
                if (!$this->config['continue_on_error']) {
                    break;
                }
            }
        }
    }

    /**
     * 并行执行操作
     *
     * @param array $vms 虚拟机列表
     */
    private function executeParallel(array $vms): void
    {
        $chunks = array_chunk($vms, $this->config['max_parallel']);
        
        foreach ($chunks as $chunk) {
            $tasks = [];
            
            // 启动所有任务
            foreach ($chunk as $vm) {
                try {
                    $task = $this->startAction($vm);
                    if ($task) {
                        $tasks[$vm['vmid']] = [
                            'vm' => $vm,
                            'task' => $task,
                        ];
                    } else {
                        $this->results[$vm['vmid']] = ['status' => 'completed', 'message' => '操作不返回任务ID'];
                    }
                } catch (\Exception $e) {
                    $this->log("启动虚拟机 {$vm['vmid']} 的操作失败: " . $e->getMessage(), 'error');
                    $this->results[$vm['vmid']] = ['error' => $e->getMessage()];
                    
                    if (!$this->config['continue_on_error']) {
                        return;
                    }
                }
            }
            
            // 等待所有任务完成
            foreach ($tasks as $vmid => $taskInfo) {
                try {
                    if (isset($taskInfo['task']['upid'])) {
                        $taskStatus = $this->waitForTask($taskInfo['vm']['node'], $taskInfo['task']['upid'], $this->config['timeout']);
                        $this->results[$vmid] = $taskStatus;
                    } else {
                        $this->results[$vmid] = $taskInfo['task'];
                    }
                } catch (\Exception $e) {
                    $this->log("等待虚拟机 {$vmid} 的任务完成失败: " . $e->getMessage(), 'error');
                    $this->results[$vmid] = ['error' => $e->getMessage()];
                    
                    if (!$this->config['continue_on_error']) {
                        return;
                    }
                }
            }
        }
    }

    /**
     * 执行操作
     *
     * @param array $vm 虚拟机信息
     * @return array 操作结果
     * @throws ProxmoxApiException
     */
    private function executeAction(array $vm): array
    {
        $task = $this->startAction($vm);
        
        if (!$task) {
            return ['status' => 'completed', 'message' => '操作不返回任务ID'];
        }
        
        if (isset($task['upid'])) {
            return $this->waitForTask($vm['node'], $task['upid'], $this->config['timeout']);
        }
        
        return $task;
    }

    /**
     * 启动操作
     *
     * @param array $vm 虚拟机信息
     * @return array|null 任务信息
     * @throws ProxmoxApiException
     */
    private function startAction(array $vm): ?array
    {
        $node = $vm['node'];
        $vmid = $vm['vmid'];
        
        $this->log("对虚拟机 {$vmid} 执行 {$this->config['action']} 操作");
        
        switch ($this->config['action']) {
            case self::ACTION_START:
                return $this->client->startVM($node, $vmid);
                
            case self::ACTION_STOP:
                return $this->client->stopVM($node, $vmid);
                
            case self::ACTION_REBOOT:
                return $this->client->nodes->rebootVM($node, $vmid);
                
            case self::ACTION_SUSPEND:
                return $this->client->nodes->suspendVM($node, $vmid);
                
            case self::ACTION_RESUME:
                return $this->client->nodes->resumeVM($node, $vmid);
                
            case self::ACTION_BACKUP:
                $params = $this->config['params'] ?? [];
                return $this->client->backup->createVMBackup($node, $vmid, $params);
                
            case self::ACTION_SNAPSHOT:
                $snapname = $this->config['params']['snapname'];
                $description = $this->config['params']['description'] ?? null;
                $includeRAM = $this->config['params']['include_ram'] ?? false;
                return $this->client->snapshot->createVMSnapshot($node, $vmid, $snapname, $description, $includeRAM);
                
            case self::ACTION_CLONE:
                $sourceVmid = $this->config['params']['source_vmid'];
                $params = array_merge(
                    $this->config['params'],
                    ['newid' => $vmid]
                );
                unset($params['source_vmid']);
                return $this->client->nodes->cloneVM($node, $sourceVmid, $params);
                
            case self::ACTION_DELETE:
                return $this->client->nodes->deleteVM($node, $vmid);
                
            case self::ACTION_MIGRATE:
                $targetNode = $this->config['params']['target_node'];
                $params = $this->config['params'] ?? [];
                unset($params['target_node']);
                return $this->client->nodes->migrateVM($node, $vmid, $targetNode, $params);
                
            default:
                throw new ProxmoxApiException("不支持的操作: {$this->config['action']}");
        }
    }
} 