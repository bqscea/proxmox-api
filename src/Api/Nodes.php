<?php

namespace ProxmoxApi\Api;

use ProxmoxApi\Client;

/**
 * Proxmox 节点API类
 */
class Nodes
{
    /**
     * @var Client API客户端
     */
    private Client $client;

    /**
     * 构造函数
     *
     * @param Client $client API客户端
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * 获取节点列表
     *
     * @return array
     */
    public function getList(): array
    {
        return $this->client->get('nodes')->toArray();
    }

    /**
     * 获取特定节点信息
     *
     * @param string $node 节点名称
     * @return array
     */
    public function getNode(string $node): array
    {
        return $this->client->get("nodes/{$node}/status")->toArray();
    }

    /**
     * 获取节点上的虚拟机列表
     *
     * @param string $node 节点名称
     * @return array
     */
    public function getVMs(string $node): array
    {
        return $this->client->get("nodes/{$node}/qemu")->toArray();
    }

    /**
     * 获取节点上的容器列表
     *
     * @param string $node 节点名称
     * @return array
     */
    public function getContainers(string $node): array
    {
        return $this->client->get("nodes/{$node}/lxc")->toArray();
    }

    /**
     * 获取虚拟机状态
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机ID
     * @return array
     */
    public function getVMStatus(string $node, int $vmid): array
    {
        return $this->client->get("nodes/{$node}/qemu/{$vmid}/status/current")->toArray();
    }

    /**
     * 获取容器状态
     *
     * @param string $node 节点名称
     * @param int $vmid 容器ID
     * @return array
     */
    public function getContainerStatus(string $node, int $vmid): array
    {
        return $this->client->get("nodes/{$node}/lxc/{$vmid}/status/current")->toArray();
    }

    /**
     * 启动虚拟机
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机ID
     * @return array
     */
    public function startVM(string $node, int $vmid): array
    {
        return $this->client->post("nodes/{$node}/qemu/{$vmid}/status/start")->toArray();
    }

    /**
     * 停止虚拟机
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机ID
     * @return array
     */
    public function stopVM(string $node, int $vmid): array
    {
        return $this->client->post("nodes/{$node}/qemu/{$vmid}/status/stop")->toArray();
    }

    /**
     * 重启虚拟机
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机ID
     * @return array
     */
    public function rebootVM(string $node, int $vmid): array
    {
        return $this->client->post("nodes/{$node}/qemu/{$vmid}/status/reboot")->toArray();
    }

    /**
     * 挂起虚拟机
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机ID
     * @return array
     */
    public function suspendVM(string $node, int $vmid): array
    {
        return $this->client->post("nodes/{$node}/qemu/{$vmid}/status/suspend")->toArray();
    }

    /**
     * 恢复虚拟机
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机ID
     * @return array
     */
    public function resumeVM(string $node, int $vmid): array
    {
        return $this->client->post("nodes/{$node}/qemu/{$vmid}/status/resume")->toArray();
    }

    /**
     * 创建虚拟机
     *
     * @param string $node 节点名称
     * @param array $params 虚拟机参数
     * @return array
     */
    public function createVM(string $node, array $params): array
    {
        return $this->client->post("nodes/{$node}/qemu", $params)->toArray();
    }

    /**
     * 删除虚拟机
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机ID
     * @return array
     */
    public function deleteVM(string $node, int $vmid): array
    {
        return $this->client->delete("nodes/{$node}/qemu/{$vmid}")->toArray();
    }

    /**
     * 克隆虚拟机
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机ID
     * @param array $params 克隆参数
     * @return array
     */
    public function cloneVM(string $node, int $vmid, array $params): array
    {
        return $this->client->post("nodes/{$node}/qemu/{$vmid}/clone", $params)->toArray();
    }

    /**
     * 获取虚拟机配置
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机ID
     * @return array
     */
    public function getVMConfig(string $node, int $vmid): array
    {
        return $this->client->get("nodes/{$node}/qemu/{$vmid}/config")->toArray();
    }

    /**
     * 更新虚拟机配置
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机ID
     * @param array $params 配置参数
     * @return array
     */
    public function updateVMConfig(string $node, int $vmid, array $params): array
    {
        return $this->client->put("nodes/{$node}/qemu/{$vmid}/config", $params)->toArray();
    }

    /**
     * 获取节点任务列表
     *
     * @param string $node 节点名称
     * @return array
     */
    public function getTasks(string $node): array
    {
        return $this->client->get("nodes/{$node}/tasks")->toArray();
    }

    /**
     * 获取任务状态
     *
     * @param string $node 节点名称
     * @param string $upid 任务ID
     * @return array
     */
    public function getTaskStatus(string $node, string $upid): array
    {
        return $this->client->get("nodes/{$node}/tasks/{$upid}/status")->toArray();
    }

    /**
     * 获取任务日志
     *
     * @param string $node 节点名称
     * @param string $upid 任务ID
     * @return array
     */
    public function getTaskLog(string $node, string $upid): array
    {
        return $this->client->get("nodes/{$node}/tasks/{$upid}/log")->toArray();
    }
} 