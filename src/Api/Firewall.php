<?php

namespace ProxmoxApi\Api;

use ProxmoxApi\Client;

/**
 * Proxmox 防火墙API类
 */
class Firewall
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
     * 获取节点防火墙规则
     *
     * @param string $node 节点名称
     * @return array
     */
    public function getNodeRules(string $node): array
    {
        return $this->client->get("nodes/{$node}/firewall/rules")->toArray();
    }

    /**
     * 创建节点防火墙规则
     *
     * @param string $node 节点名称
     * @param array $params 规则参数
     * @return array
     */
    public function createNodeRule(string $node, array $params): array
    {
        return $this->client->post("nodes/{$node}/firewall/rules", $params)->toArray();
    }

    /**
     * 获取特定节点防火墙规则
     *
     * @param string $node 节点名称
     * @param int $pos 规则位置
     * @return array
     */
    public function getNodeRule(string $node, int $pos): array
    {
        return $this->client->get("nodes/{$node}/firewall/rules/{$pos}")->toArray();
    }

    /**
     * 更新节点防火墙规则
     *
     * @param string $node 节点名称
     * @param int $pos 规则位置
     * @param array $params 规则参数
     * @return array
     */
    public function updateNodeRule(string $node, int $pos, array $params): array
    {
        return $this->client->put("nodes/{$node}/firewall/rules/{$pos}", $params)->toArray();
    }

    /**
     * 删除节点防火墙规则
     *
     * @param string $node 节点名称
     * @param int $pos 规则位置
     * @return array
     */
    public function deleteNodeRule(string $node, int $pos): array
    {
        return $this->client->delete("nodes/{$node}/firewall/rules/{$pos}")->toArray();
    }

    /**
     * 获取虚拟机防火墙规则
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机ID
     * @return array
     */
    public function getVMRules(string $node, int $vmid): array
    {
        return $this->client->get("nodes/{$node}/qemu/{$vmid}/firewall/rules")->toArray();
    }

    /**
     * 创建虚拟机防火墙规则
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机ID
     * @param array $params 规则参数
     * @return array
     */
    public function createVMRule(string $node, int $vmid, array $params): array
    {
        return $this->client->post("nodes/{$node}/qemu/{$vmid}/firewall/rules", $params)->toArray();
    }

    /**
     * 获取特定虚拟机防火墙规则
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机ID
     * @param int $pos 规则位置
     * @return array
     */
    public function getVMRule(string $node, int $vmid, int $pos): array
    {
        return $this->client->get("nodes/{$node}/qemu/{$vmid}/firewall/rules/{$pos}")->toArray();
    }

    /**
     * 更新虚拟机防火墙规则
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机ID
     * @param int $pos 规则位置
     * @param array $params 规则参数
     * @return array
     */
    public function updateVMRule(string $node, int $vmid, int $pos, array $params): array
    {
        return $this->client->put("nodes/{$node}/qemu/{$vmid}/firewall/rules/{$pos}", $params)->toArray();
    }

    /**
     * 删除虚拟机防火墙规则
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机ID
     * @param int $pos 规则位置
     * @return array
     */
    public function deleteVMRule(string $node, int $vmid, int $pos): array
    {
        return $this->client->delete("nodes/{$node}/qemu/{$vmid}/firewall/rules/{$pos}")->toArray();
    }

    /**
     * 获取容器防火墙规则
     *
     * @param string $node 节点名称
     * @param int $vmid 容器ID
     * @return array
     */
    public function getContainerRules(string $node, int $vmid): array
    {
        return $this->client->get("nodes/{$node}/lxc/{$vmid}/firewall/rules")->toArray();
    }

    /**
     * 创建容器防火墙规则
     *
     * @param string $node 节点名称
     * @param int $vmid 容器ID
     * @param array $params 规则参数
     * @return array
     */
    public function createContainerRule(string $node, int $vmid, array $params): array
    {
        return $this->client->post("nodes/{$node}/lxc/{$vmid}/firewall/rules", $params)->toArray();
    }

    /**
     * 获取特定容器防火墙规则
     *
     * @param string $node 节点名称
     * @param int $vmid 容器ID
     * @param int $pos 规则位置
     * @return array
     */
    public function getContainerRule(string $node, int $vmid, int $pos): array
    {
        return $this->client->get("nodes/{$node}/lxc/{$vmid}/firewall/rules/{$pos}")->toArray();
    }

    /**
     * 更新容器防火墙规则
     *
     * @param string $node 节点名称
     * @param int $vmid 容器ID
     * @param int $pos 规则位置
     * @param array $params 规则参数
     * @return array
     */
    public function updateContainerRule(string $node, int $vmid, int $pos, array $params): array
    {
        return $this->client->put("nodes/{$node}/lxc/{$vmid}/firewall/rules/{$pos}", $params)->toArray();
    }

    /**
     * 删除容器防火墙规则
     *
     * @param string $node 节点名称
     * @param int $vmid 容器ID
     * @param int $pos 规则位置
     * @return array
     */
    public function deleteContainerRule(string $node, int $vmid, int $pos): array
    {
        return $this->client->delete("nodes/{$node}/lxc/{$vmid}/firewall/rules/{$pos}")->toArray();
    }

    /**
     * 获取防火墙IP集合
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机/容器ID
     * @param string $type 类型 (qemu 或 lxc)
     * @return array
     */
    public function getIPSets(string $node, int $vmid, string $type = 'qemu'): array
    {
        return $this->client->get("nodes/{$node}/{$type}/{$vmid}/firewall/ipset")->toArray();
    }

    /**
     * 创建防火墙IP集合
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机/容器ID
     * @param string $name 集合名称
     * @param string $type 类型 (qemu 或 lxc)
     * @return array
     */
    public function createIPSet(string $node, int $vmid, string $name, string $type = 'qemu'): array
    {
        return $this->client->post("nodes/{$node}/{$type}/{$vmid}/firewall/ipset", ['name' => $name])->toArray();
    }

    /**
     * 获取防火墙IP集合内容
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机/容器ID
     * @param string $name 集合名称
     * @param string $type 类型 (qemu 或 lxc)
     * @return array
     */
    public function getIPSetContent(string $node, int $vmid, string $name, string $type = 'qemu'): array
    {
        return $this->client->get("nodes/{$node}/{$type}/{$vmid}/firewall/ipset/{$name}")->toArray();
    }

    /**
     * 添加IP到集合
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机/容器ID
     * @param string $name 集合名称
     * @param string $cidr CIDR格式的IP
     * @param string $type 类型 (qemu 或 lxc)
     * @param string|null $comment 注释
     * @return array
     */
    public function addIPToSet(string $node, int $vmid, string $name, string $cidr, string $type = 'qemu', ?string $comment = null): array
    {
        $params = ['cidr' => $cidr];
        if ($comment !== null) {
            $params['comment'] = $comment;
        }
        
        return $this->client->post("nodes/{$node}/{$type}/{$vmid}/firewall/ipset/{$name}", $params)->toArray();
    }

    /**
     * 从集合中删除IP
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机/容器ID
     * @param string $name 集合名称
     * @param string $cidr CIDR格式的IP
     * @param string $type 类型 (qemu 或 lxc)
     * @return array
     */
    public function removeIPFromSet(string $node, int $vmid, string $name, string $cidr, string $type = 'qemu'): array
    {
        return $this->client->delete("nodes/{$node}/{$type}/{$vmid}/firewall/ipset/{$name}/{$cidr}")->toArray();
    }
} 