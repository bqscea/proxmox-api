<?php

namespace ProxmoxApi\Api;

use ProxmoxApi\Client;

/**
 * Proxmox 集群API类
 */
class Cluster
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
     * 获取集群状态
     *
     * @return array
     */
    public function getStatus(): array
    {
        return $this->client->get('cluster/status')->toArray();
    }

    /**
     * 获取集群资源
     *
     * @param string|null $type 资源类型 (vm, storage, node, etc.)
     * @return array
     */
    public function getResources(?string $type = null): array
    {
        $params = [];
        if ($type !== null) {
            $params['type'] = $type;
        }

        return $this->client->get('cluster/resources', $params)->toArray();
    }

    /**
     * 获取集群任务
     *
     * @return array
     */
    public function getTasks(): array
    {
        return $this->client->get('cluster/tasks')->toArray();
    }

    /**
     * 获取集群备份计划
     *
     * @return array
     */
    public function getBackupSchedule(): array
    {
        return $this->client->get('cluster/backup')->toArray();
    }

    /**
     * 创建备份计划
     *
     * @param array $params 备份参数
     * @return array
     */
    public function createBackupSchedule(array $params): array
    {
        return $this->client->post('cluster/backup', $params)->toArray();
    }

    /**
     * 获取特定备份计划
     *
     * @param string $id 备份计划ID
     * @return array
     */
    public function getBackupScheduleById(string $id): array
    {
        return $this->client->get("cluster/backup/{$id}")->toArray();
    }

    /**
     * 更新备份计划
     *
     * @param string $id 备份计划ID
     * @param array $params 备份参数
     * @return array
     */
    public function updateBackupSchedule(string $id, array $params): array
    {
        return $this->client->put("cluster/backup/{$id}", $params)->toArray();
    }

    /**
     * 删除备份计划
     *
     * @param string $id 备份计划ID
     * @return array
     */
    public function deleteBackupSchedule(string $id): array
    {
        return $this->client->delete("cluster/backup/{$id}")->toArray();
    }

    /**
     * 获取高可用性状态
     *
     * @return array
     */
    public function getHAStatus(): array
    {
        return $this->client->get('cluster/ha/status')->toArray();
    }

    /**
     * 获取高可用性资源
     *
     * @return array
     */
    public function getHAResources(): array
    {
        return $this->client->get('cluster/ha/resources')->toArray();
    }

    /**
     * 创建高可用性资源
     *
     * @param array $params 资源参数
     * @return array
     */
    public function createHAResource(array $params): array
    {
        return $this->client->post('cluster/ha/resources', $params)->toArray();
    }

    /**
     * 获取特定高可用性资源
     *
     * @param string $id 资源ID
     * @return array
     */
    public function getHAResourceById(string $id): array
    {
        return $this->client->get("cluster/ha/resources/{$id}")->toArray();
    }

    /**
     * 更新高可用性资源
     *
     * @param string $id 资源ID
     * @param array $params 资源参数
     * @return array
     */
    public function updateHAResource(string $id, array $params): array
    {
        return $this->client->put("cluster/ha/resources/{$id}", $params)->toArray();
    }

    /**
     * 删除高可用性资源
     *
     * @param string $id 资源ID
     * @return array
     */
    public function deleteHAResource(string $id): array
    {
        return $this->client->delete("cluster/ha/resources/{$id}")->toArray();
    }

    /**
     * 获取防火墙规则
     *
     * @return array
     */
    public function getFirewallRules(): array
    {
        return $this->client->get('cluster/firewall/rules')->toArray();
    }

    /**
     * 创建防火墙规则
     *
     * @param array $params 规则参数
     * @return array
     */
    public function createFirewallRule(array $params): array
    {
        return $this->client->post('cluster/firewall/rules', $params)->toArray();
    }

    /**
     * 获取特定防火墙规则
     *
     * @param int $pos 规则位置
     * @return array
     */
    public function getFirewallRuleByPos(int $pos): array
    {
        return $this->client->get("cluster/firewall/rules/{$pos}")->toArray();
    }

    /**
     * 更新防火墙规则
     *
     * @param int $pos 规则位置
     * @param array $params 规则参数
     * @return array
     */
    public function updateFirewallRule(int $pos, array $params): array
    {
        return $this->client->put("cluster/firewall/rules/{$pos}", $params)->toArray();
    }

    /**
     * 删除防火墙规则
     *
     * @param int $pos 规则位置
     * @return array
     */
    public function deleteFirewallRule(int $pos): array
    {
        return $this->client->delete("cluster/firewall/rules/{$pos}")->toArray();
    }
} 