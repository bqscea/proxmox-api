<?php

namespace ProxmoxApi\Api;

use ProxmoxApi\Client;

/**
 * Proxmox 快照API类
 */
class Snapshot
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
     * 获取虚拟机快照列表
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机ID
     * @return array
     */
    public function getVMSnapshots(string $node, int $vmid): array
    {
        return $this->client->get("nodes/{$node}/qemu/{$vmid}/snapshot")->toArray();
    }

    /**
     * 创建虚拟机快照
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机ID
     * @param string $name 快照名称
     * @param string|null $description 快照描述
     * @param bool $includeRAM 是否包含内存状态
     * @return array
     */
    public function createVMSnapshot(string $node, int $vmid, string $name, ?string $description = null, bool $includeRAM = false): array
    {
        $params = [
            'snapname' => $name,
            'vmstate' => $includeRAM ? 1 : 0,
        ];

        if ($description !== null) {
            $params['description'] = $description;
        }

        return $this->client->post("nodes/{$node}/qemu/{$vmid}/snapshot", $params)->toArray();
    }

    /**
     * 获取虚拟机快照详情
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机ID
     * @param string $snapshot 快照名称
     * @return array
     */
    public function getVMSnapshot(string $node, int $vmid, string $snapshot): array
    {
        return $this->client->get("nodes/{$node}/qemu/{$vmid}/snapshot/{$snapshot}")->toArray();
    }

    /**
     * 删除虚拟机快照
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机ID
     * @param string $snapshot 快照名称
     * @return array
     */
    public function deleteVMSnapshot(string $node, int $vmid, string $snapshot): array
    {
        return $this->client->delete("nodes/{$node}/qemu/{$vmid}/snapshot/{$snapshot}")->toArray();
    }

    /**
     * 恢复虚拟机快照
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机ID
     * @param string $snapshot 快照名称
     * @return array
     */
    public function rollbackVMSnapshot(string $node, int $vmid, string $snapshot): array
    {
        return $this->client->post("nodes/{$node}/qemu/{$vmid}/snapshot/{$snapshot}/rollback")->toArray();
    }

    /**
     * 获取容器快照列表
     *
     * @param string $node 节点名称
     * @param int $vmid 容器ID
     * @return array
     */
    public function getContainerSnapshots(string $node, int $vmid): array
    {
        return $this->client->get("nodes/{$node}/lxc/{$vmid}/snapshot")->toArray();
    }

    /**
     * 创建容器快照
     *
     * @param string $node 节点名称
     * @param int $vmid 容器ID
     * @param string $name 快照名称
     * @param string|null $description 快照描述
     * @return array
     */
    public function createContainerSnapshot(string $node, int $vmid, string $name, ?string $description = null): array
    {
        $params = [
            'snapname' => $name,
        ];

        if ($description !== null) {
            $params['description'] = $description;
        }

        return $this->client->post("nodes/{$node}/lxc/{$vmid}/snapshot", $params)->toArray();
    }

    /**
     * 获取容器快照详情
     *
     * @param string $node 节点名称
     * @param int $vmid 容器ID
     * @param string $snapshot 快照名称
     * @return array
     */
    public function getContainerSnapshot(string $node, int $vmid, string $snapshot): array
    {
        return $this->client->get("nodes/{$node}/lxc/{$vmid}/snapshot/{$snapshot}")->toArray();
    }

    /**
     * 删除容器快照
     *
     * @param string $node 节点名称
     * @param int $vmid 容器ID
     * @param string $snapshot 快照名称
     * @return array
     */
    public function deleteContainerSnapshot(string $node, int $vmid, string $snapshot): array
    {
        return $this->client->delete("nodes/{$node}/lxc/{$vmid}/snapshot/{$snapshot}")->toArray();
    }

    /**
     * 恢复容器快照
     *
     * @param string $node 节点名称
     * @param int $vmid 容器ID
     * @param string $snapshot 快照名称
     * @return array
     */
    public function rollbackContainerSnapshot(string $node, int $vmid, string $snapshot): array
    {
        return $this->client->post("nodes/{$node}/lxc/{$vmid}/snapshot/{$snapshot}/rollback")->toArray();
    }

    /**
     * 获取快照配置
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机/容器ID
     * @param string $snapshot 快照名称
     * @param string $type 类型 (qemu 或 lxc)
     * @return array
     */
    public function getSnapshotConfig(string $node, int $vmid, string $snapshot, string $type = 'qemu'): array
    {
        return $this->client->get("nodes/{$node}/{$type}/{$vmid}/snapshot/{$snapshot}/config")->toArray();
    }

    /**
     * 更新快照配置
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机/容器ID
     * @param string $snapshot 快照名称
     * @param string $description 快照描述
     * @param string $type 类型 (qemu 或 lxc)
     * @return array
     */
    public function updateSnapshotConfig(string $node, int $vmid, string $snapshot, string $description, string $type = 'qemu'): array
    {
        return $this->client->put("nodes/{$node}/{$type}/{$vmid}/snapshot/{$snapshot}/config", ['description' => $description])->toArray();
    }

    /**
     * 创建定时快照任务
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机/容器ID
     * @param array $params 任务参数
     * @param string $type 类型 (qemu 或 lxc)
     * @return array
     */
    public function createSnapshotJob(string $node, int $vmid, array $params, string $type = 'qemu'): array
    {
        $defaultParams = [
            'enabled' => 1,
            'all' => 0,
            'vmid' => $vmid,
            'node' => $node,
            'storage' => 'local',
            'mode' => 'snapshot',
        ];

        $params = array_merge($defaultParams, $params);

        return $this->client->post("cluster/backup", $params)->toArray();
    }

    /**
     * 批量创建快照
     *
     * @param string $node 节点名称
     * @param array $vmids 虚拟机/容器ID数组
     * @param string $name 快照名称
     * @param string|null $description 快照描述
     * @param string $type 类型 (qemu 或 lxc)
     * @return array 任务结果数组
     */
    public function createBatchSnapshots(string $node, array $vmids, string $name, ?string $description = null, string $type = 'qemu'): array
    {
        $results = [];

        foreach ($vmids as $vmid) {
            try {
                if ($type === 'qemu') {
                    $results[$vmid] = $this->createVMSnapshot($node, $vmid, $name, $description);
                } else {
                    $results[$vmid] = $this->createContainerSnapshot($node, $vmid, $name, $description);
                }
            } catch (\Exception $e) {
                $results[$vmid] = ['error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * 批量删除快照
     *
     * @param string $node 节点名称
     * @param array $vmids 虚拟机/容器ID数组
     * @param string $snapshot 快照名称
     * @param string $type 类型 (qemu 或 lxc)
     * @return array 任务结果数组
     */
    public function deleteBatchSnapshots(string $node, array $vmids, string $snapshot, string $type = 'qemu'): array
    {
        $results = [];

        foreach ($vmids as $vmid) {
            try {
                if ($type === 'qemu') {
                    $results[$vmid] = $this->deleteVMSnapshot($node, $vmid, $snapshot);
                } else {
                    $results[$vmid] = $this->deleteContainerSnapshot($node, $vmid, $snapshot);
                }
            } catch (\Exception $e) {
                $results[$vmid] = ['error' => $e->getMessage()];
            }
        }

        return $results;
    }
} 