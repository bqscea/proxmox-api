<?php

namespace ProxmoxApi\Api;

use ProxmoxApi\Client;

/**
 * Proxmox 网络API类
 */
class Network
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
     * 获取节点网络配置
     *
     * @param string $node 节点名称
     * @return array
     */
    public function getNodeNetworks(string $node): array
    {
        return $this->client->get("nodes/{$node}/network")->toArray();
    }

    /**
     * 获取特定网络接口配置
     *
     * @param string $node 节点名称
     * @param string $iface 接口名称
     * @return array
     */
    public function getNodeNetwork(string $node, string $iface): array
    {
        return $this->client->get("nodes/{$node}/network/{$iface}")->toArray();
    }

    /**
     * 创建网络接口
     *
     * @param string $node 节点名称
     * @param array $params 接口参数
     * @return array
     */
    public function createNodeNetwork(string $node, array $params): array
    {
        return $this->client->post("nodes/{$node}/network", $params)->toArray();
    }

    /**
     * 更新网络接口
     *
     * @param string $node 节点名称
     * @param string $iface 接口名称
     * @param array $params 接口参数
     * @return array
     */
    public function updateNodeNetwork(string $node, string $iface, array $params): array
    {
        return $this->client->put("nodes/{$node}/network/{$iface}", $params)->toArray();
    }

    /**
     * 删除网络接口
     *
     * @param string $node 节点名称
     * @param string $iface 接口名称
     * @return array
     */
    public function deleteNodeNetwork(string $node, string $iface): array
    {
        return $this->client->delete("nodes/{$node}/network/{$iface}")->toArray();
    }

    /**
     * 应用网络配置
     *
     * @param string $node 节点名称
     * @return array
     */
    public function applyNodeNetwork(string $node): array
    {
        return $this->client->put("nodes/{$node}/network", ['reload' => 1])->toArray();
    }

    /**
     * 获取虚拟机网络接口
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机ID
     * @return array
     */
    public function getVMNetworks(string $node, int $vmid): array
    {
        $config = $this->client->get("nodes/{$node}/qemu/{$vmid}/config")->toArray();
        $networks = [];

        foreach ($config as $key => $value) {
            if (preg_match('/^net(\d+)$/', $key, $matches)) {
                $networks[$key] = $value;
            }
        }

        return $networks;
    }

    /**
     * 添加虚拟机网络接口
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机ID
     * @param array $params 接口参数
     * @return array
     */
    public function addVMNetwork(string $node, int $vmid, array $params): array
    {
        // 获取当前配置
        $config = $this->client->get("nodes/{$node}/qemu/{$vmid}/config")->toArray();
        
        // 找到下一个可用的网络接口ID
        $nextId = 0;
        foreach ($config as $key => $value) {
            if (preg_match('/^net(\d+)$/', $key, $matches)) {
                $id = (int)$matches[1];
                if ($id >= $nextId) {
                    $nextId = $id + 1;
                }
            }
        }
        
        // 构建网络接口参数
        $netKey = "net{$nextId}";
        
        return $this->client->put("nodes/{$node}/qemu/{$vmid}/config", [$netKey => $this->buildNetworkString($params)])->toArray();
    }

    /**
     * 更新虚拟机网络接口
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机ID
     * @param int $netId 网络接口ID
     * @param array $params 接口参数
     * @return array
     */
    public function updateVMNetwork(string $node, int $vmid, int $netId, array $params): array
    {
        $netKey = "net{$netId}";
        
        return $this->client->put("nodes/{$node}/qemu/{$vmid}/config", [$netKey => $this->buildNetworkString($params)])->toArray();
    }

    /**
     * 删除虚拟机网络接口
     *
     * @param string $node 节点名称
     * @param int $vmid 虚拟机ID
     * @param int $netId 网络接口ID
     * @return array
     */
    public function deleteVMNetwork(string $node, int $vmid, int $netId): array
    {
        $netKey = "net{$netId}";
        
        return $this->client->put("nodes/{$node}/qemu/{$vmid}/config", ["delete" => $netKey])->toArray();
    }

    /**
     * 获取容器网络接口
     *
     * @param string $node 节点名称
     * @param int $vmid 容器ID
     * @return array
     */
    public function getContainerNetworks(string $node, int $vmid): array
    {
        $config = $this->client->get("nodes/{$node}/lxc/{$vmid}/config")->toArray();
        $networks = [];

        foreach ($config as $key => $value) {
            if (preg_match('/^net(\d+)$/', $key, $matches)) {
                $networks[$key] = $value;
            }
        }

        return $networks;
    }

    /**
     * 添加容器网络接口
     *
     * @param string $node 节点名称
     * @param int $vmid 容器ID
     * @param array $params 接口参数
     * @return array
     */
    public function addContainerNetwork(string $node, int $vmid, array $params): array
    {
        // 获取当前配置
        $config = $this->client->get("nodes/{$node}/lxc/{$vmid}/config")->toArray();
        
        // 找到下一个可用的网络接口ID
        $nextId = 0;
        foreach ($config as $key => $value) {
            if (preg_match('/^net(\d+)$/', $key, $matches)) {
                $id = (int)$matches[1];
                if ($id >= $nextId) {
                    $nextId = $id + 1;
                }
            }
        }
        
        // 构建网络接口参数
        $netKey = "net{$nextId}";
        
        return $this->client->put("nodes/{$node}/lxc/{$vmid}/config", [$netKey => $this->buildContainerNetworkString($params)])->toArray();
    }

    /**
     * 更新容器网络接口
     *
     * @param string $node 节点名称
     * @param int $vmid 容器ID
     * @param int $netId 网络接口ID
     * @param array $params 接口参数
     * @return array
     */
    public function updateContainerNetwork(string $node, int $vmid, int $netId, array $params): array
    {
        $netKey = "net{$netId}";
        
        return $this->client->put("nodes/{$node}/lxc/{$vmid}/config", [$netKey => $this->buildContainerNetworkString($params)])->toArray();
    }

    /**
     * 删除容器网络接口
     *
     * @param string $node 节点名称
     * @param int $vmid 容器ID
     * @param int $netId 网络接口ID
     * @return array
     */
    public function deleteContainerNetwork(string $node, int $vmid, int $netId): array
    {
        $netKey = "net{$netId}";
        
        return $this->client->put("nodes/{$node}/lxc/{$vmid}/config", ["delete" => $netKey])->toArray();
    }

    /**
     * 构建虚拟机网络接口字符串
     *
     * @param array $params 接口参数
     * @return string
     */
    private function buildNetworkString(array $params): string
    {
        $model = $params['model'] ?? 'virtio';
        $bridge = $params['bridge'] ?? 'vmbr0';
        
        $result = "{$model},bridge={$bridge}";
        
        if (isset($params['macaddr'])) {
            $result .= ",macaddr={$params['macaddr']}";
        }
        
        if (isset($params['tag'])) {
            $result .= ",tag={$params['tag']}";
        }
        
        if (isset($params['firewall']) && $params['firewall']) {
            $result .= ",firewall=1";
        }
        
        if (isset($params['rate'])) {
            $result .= ",rate={$params['rate']}";
        }
        
        if (isset($params['mtu'])) {
            $result .= ",mtu={$params['mtu']}";
        }
        
        if (isset($params['queues'])) {
            $result .= ",queues={$params['queues']}";
        }
        
        return $result;
    }

    /**
     * 构建容器网络接口字符串
     *
     * @param array $params 接口参数
     * @return string
     */
    private function buildContainerNetworkString(array $params): string
    {
        $name = $params['name'] ?? 'eth0';
        $bridge = $params['bridge'] ?? 'vmbr0';
        
        $result = "name={$name},bridge={$bridge}";
        
        if (isset($params['ip'])) {
            $result .= ",ip={$params['ip']}";
        }
        
        if (isset($params['gw'])) {
            $result .= ",gw={$params['gw']}";
        }
        
        if (isset($params['tag'])) {
            $result .= ",tag={$params['tag']}";
        }
        
        if (isset($params['firewall']) && $params['firewall']) {
            $result .= ",firewall=1";
        }
        
        if (isset($params['rate'])) {
            $result .= ",rate={$params['rate']}";
        }
        
        if (isset($params['mtu'])) {
            $result .= ",mtu={$params['mtu']}";
        }
        
        if (isset($params['hwaddr'])) {
            $result .= ",hwaddr={$params['hwaddr']}";
        }
        
        return $result;
    }
} 