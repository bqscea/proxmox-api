<?php

namespace ProxmoxApi\Automation;

use ProxmoxApi\Client;
use ProxmoxApi\Exception\ProxmoxApiException;

/**
 * Proxmox 自动化任务基类
 */
abstract class AutomationTask
{
    /**
     * @var Client API客户端
     */
    protected Client $client;

    /**
     * @var array 任务配置
     */
    protected array $config;

    /**
     * @var array 任务结果
     */
    protected array $results = [];

    /**
     * @var array 任务日志
     */
    protected array $logs = [];

    /**
     * 构造函数
     *
     * @param Client $client API客户端
     * @param array $config 任务配置
     */
    public function __construct(Client $client, array $config = [])
    {
        $this->client = $client;
        $this->config = $config;
    }

    /**
     * 执行任务
     *
     * @return array 任务结果
     */
    abstract public function execute(): array;

    /**
     * 获取任务结果
     *
     * @return array
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * 获取任务日志
     *
     * @return array
     */
    public function getLogs(): array
    {
        return $this->logs;
    }

    /**
     * 添加日志
     *
     * @param string $message 日志消息
     * @param string $level 日志级别 (info, warning, error)
     * @return void
     */
    protected function log(string $message, string $level = 'info'): void
    {
        $this->logs[] = [
            'timestamp' => time(),
            'level' => $level,
            'message' => $message,
        ];
    }

    /**
     * 等待任务完成
     *
     * @param string $node 节点名称
     * @param string $upid 任务ID
     * @param int $timeout 超时时间（秒）
     * @param int $interval 检查间隔（秒）
     * @return array 任务状态
     * @throws ProxmoxApiException
     */
    protected function waitForTask(string $node, string $upid, int $timeout = 300, int $interval = 2): array
    {
        $startTime = time();
        $endTime = $startTime + $timeout;
        
        $this->log("等待任务 {$upid} 完成...");
        
        while (time() < $endTime) {
            $taskStatus = $this->client->nodes->getTaskStatus($node, $upid);
            
            if (isset($taskStatus['status'])) {
                if ($taskStatus['status'] === 'stopped') {
                    if (isset($taskStatus['exitstatus']) && $taskStatus['exitstatus'] === 'OK') {
                        $this->log("任务 {$upid} 成功完成");
                    } else {
                        $errorMsg = $taskStatus['exitstatus'] ?? '未知错误';
                        $this->log("任务 {$upid} 失败: {$errorMsg}", 'error');
                    }
                    
                    return $taskStatus;
                }
            }
            
            sleep($interval);
        }
        
        $this->log("任务 {$upid} 超时", 'error');
        throw new ProxmoxApiException("任务 {$upid} 超时");
    }

    /**
     * 获取节点上的所有虚拟机
     *
     * @param string $node 节点名称
     * @param array $filters 过滤条件
     * @return array 虚拟机列表
     */
    protected function getFilteredVMs(string $node, array $filters = []): array
    {
        $vms = $this->client->getNodeVMs($node);
        
        if (empty($filters)) {
            return $vms;
        }
        
        return array_filter($vms, function ($vm) use ($filters) {
            foreach ($filters as $key => $value) {
                if (!isset($vm[$key]) || $vm[$key] != $value) {
                    return false;
                }
            }
            
            return true;
        });
    }

    /**
     * 获取节点上的所有容器
     *
     * @param string $node 节点名称
     * @param array $filters 过滤条件
     * @return array 容器列表
     */
    protected function getFilteredContainers(string $node, array $filters = []): array
    {
        $containers = $this->client->nodes->getContainers($node);
        
        if (empty($filters)) {
            return $containers;
        }
        
        return array_filter($containers, function ($container) use ($filters) {
            foreach ($filters as $key => $value) {
                if (!isset($container[$key]) || $container[$key] != $value) {
                    return false;
                }
            }
            
            return true;
        });
    }

    /**
     * 获取所有节点上的所有虚拟机
     *
     * @param array $filters 过滤条件
     * @return array 虚拟机列表
     */
    protected function getAllFilteredVMs(array $filters = []): array
    {
        $nodes = $this->client->getNodes();
        $allVMs = [];
        
        foreach ($nodes as $node) {
            $nodeName = $node['node'];
            try {
                $vms = $this->getFilteredVMs($nodeName, $filters);
                foreach ($vms as $vm) {
                    $vm['node'] = $nodeName;
                    $allVMs[] = $vm;
                }
            } catch (\Exception $e) {
                $this->log("获取节点 {$nodeName} 的虚拟机失败: " . $e->getMessage(), 'error');
            }
        }
        
        return $allVMs;
    }

    /**
     * 获取所有节点上的所有容器
     *
     * @param array $filters 过滤条件
     * @return array 容器列表
     */
    protected function getAllFilteredContainers(array $filters = []): array
    {
        $nodes = $this->client->getNodes();
        $allContainers = [];
        
        foreach ($nodes as $node) {
            $nodeName = $node['node'];
            try {
                $containers = $this->getFilteredContainers($nodeName, $filters);
                foreach ($containers as $container) {
                    $container['node'] = $nodeName;
                    $allContainers[] = $container;
                }
            } catch (\Exception $e) {
                $this->log("获取节点 {$nodeName} 的容器失败: " . $e->getMessage(), 'error');
            }
        }
        
        return $allContainers;
    }
} 