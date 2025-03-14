<?php

namespace ProxmoxApi\Api;

use ProxmoxApi\Client;

/**
 * Proxmox 备份API类
 */
class Backup
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
     * 获取节点上的备份列表
     *
     * @param string $node 节点名称
     * @return array
     */
    public function getNodeBackups(string $node): array
    {
        return $this->client->get("nodes/{$node}/storage/local/content", ['content' => 'backup'])->toArray();
    }

    /**
     * 创建虚拟机备份
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机ID
     * @param array $params 备份参数
     * @return array
     */
    public function createVMBackup(string $node, int $vmid, array $params = []): array
    {
        $defaultParams = [
            'mode' => 'snapshot',
            'compress' => 'zstd',
            'storage' => 'local',
        ];

        $params = array_merge($defaultParams, $params);
        $params['vmid'] = $vmid;

        return $this->client->post("nodes/{$node}/vzdump", $params)->toArray();
    }

    /**
     * 创建容器备份
     *
     * @param string $node 节点名称
     * @param int $vmid 容器ID
     * @param array $params 备份参数
     * @return array
     */
    public function createContainerBackup(string $node, int $vmid, array $params = []): array
    {
        return $this->createVMBackup($node, $vmid, $params);
    }

    /**
     * 从备份恢复虚拟机
     *
     * @param string $node 节点名称
     * @param string $storage 存储名称
     * @param string $volid 卷ID
     * @param array $params 恢复参数
     * @return array
     */
    public function restoreVMBackup(string $node, string $storage, string $volid, array $params = []): array
    {
        $params['storage'] = $storage;
        $params['archive'] = $volid;

        return $this->client->post("nodes/{$node}/qemu", $params)->toArray();
    }

    /**
     * 从备份恢复容器
     *
     * @param string $node 节点名称
     * @param string $storage 存储名称
     * @param string $volid 卷ID
     * @param array $params 恢复参数
     * @return array
     */
    public function restoreContainerBackup(string $node, string $storage, string $volid, array $params = []): array
    {
        $params['storage'] = $storage;
        $params['archive'] = $volid;

        return $this->client->post("nodes/{$node}/lxc", $params)->toArray();
    }

    /**
     * 获取备份配置
     *
     * @param string $node 节点名称
     * @return array
     */
    public function getBackupConfig(string $node): array
    {
        return $this->client->get("nodes/{$node}/vzdump/defaults")->toArray();
    }

    /**
     * 获取备份内容
     *
     * @param string $node 节点名称
     * @param string $volid 卷ID
     * @return array
     */
    public function getBackupContent(string $node, string $volid): array
    {
        return $this->client->get("nodes/{$node}/vzdump/extractconfig", ['volume' => $volid])->toArray();
    }

    /**
     * 创建批量备份
     *
     * @param string $node 节点名称
     * @param array $vmids 虚拟机/容器ID数组
     * @param array $params 备份参数
     * @return array
     */
    public function createBatchBackup(string $node, array $vmids, array $params = []): array
    {
        $defaultParams = [
            'mode' => 'snapshot',
            'compress' => 'zstd',
            'storage' => 'local',
            'all' => 0,
        ];

        $params = array_merge($defaultParams, $params);
        $params['vmid'] = implode(',', $vmids);

        return $this->client->post("nodes/{$node}/vzdump", $params)->toArray();
    }

    /**
     * 创建所有虚拟机和容器的备份
     *
     * @param string $node 节点名称
     * @param array $params 备份参数
     * @return array
     */
    public function createAllBackup(string $node, array $params = []): array
    {
        $defaultParams = [
            'mode' => 'snapshot',
            'compress' => 'zstd',
            'storage' => 'local',
            'all' => 1,
        ];

        $params = array_merge($defaultParams, $params);

        return $this->client->post("nodes/{$node}/vzdump", $params)->toArray();
    }

    /**
     * 删除备份
     *
     * @param string $node 节点名称
     * @param string $storage 存储名称
     * @param string $volid 卷ID
     * @return array
     */
    public function deleteBackup(string $node, string $storage, string $volid): array
    {
        return $this->client->delete("nodes/{$node}/storage/{$storage}/content/{$volid}")->toArray();
    }

    /**
     * 获取备份任务状态
     *
     * @param string $node 节点名称
     * @param string $upid 任务ID
     * @return array
     */
    public function getBackupTaskStatus(string $node, string $upid): array
    {
        return $this->client->get("nodes/{$node}/tasks/{$upid}/status")->toArray();
    }

    /**
     * 获取备份任务日志
     *
     * @param string $node 节点名称
     * @param string $upid 任务ID
     * @return array
     */
    public function getBackupTaskLog(string $node, string $upid): array
    {
        return $this->client->get("nodes/{$node}/tasks/{$upid}/log")->toArray();
    }
} 