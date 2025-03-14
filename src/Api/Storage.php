<?php

namespace ProxmoxApi\Api;

use ProxmoxApi\Client;

/**
 * Proxmox 存储API类
 */
class Storage
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
     * 获取存储列表
     *
     * @return array
     */
    public function getList(): array
    {
        return $this->client->get('storage')->toArray();
    }

    /**
     * 创建存储
     *
     * @param array $params 存储参数
     * @return array
     */
    public function create(array $params): array
    {
        return $this->client->post('storage', $params)->toArray();
    }

    /**
     * 获取特定存储信息
     *
     * @param string $storage 存储ID
     * @return array
     */
    public function get(string $storage): array
    {
        return $this->client->get("storage/{$storage}")->toArray();
    }

    /**
     * 更新存储
     *
     * @param string $storage 存储ID
     * @param array $params 存储参数
     * @return array
     */
    public function update(string $storage, array $params): array
    {
        return $this->client->put("storage/{$storage}", $params)->toArray();
    }

    /**
     * 删除存储
     *
     * @param string $storage 存储ID
     * @return array
     */
    public function delete(string $storage): array
    {
        return $this->client->delete("storage/{$storage}")->toArray();
    }

    /**
     * 获取节点上的存储列表
     *
     * @param string $node 节点名称
     * @return array
     */
    public function getNodeStorages(string $node): array
    {
        return $this->client->get("nodes/{$node}/storage")->toArray();
    }

    /**
     * 获取节点上特定存储的内容
     *
     * @param string $node 节点名称
     * @param string $storage 存储ID
     * @param string|null $content 内容类型 (images, rootdir, vztmpl, etc.)
     * @return array
     */
    public function getStorageContent(string $node, string $storage, ?string $content = null): array
    {
        $params = [];
        if ($content !== null) {
            $params['content'] = $content;
        }

        return $this->client->get("nodes/{$node}/storage/{$storage}/content", $params)->toArray();
    }

    /**
     * 上传内容到存储
     *
     * @param string $node 节点名称
     * @param string $storage 存储ID
     * @param array $params 上传参数
     * @return array
     */
    public function uploadContent(string $node, string $storage, array $params): array
    {
        return $this->client->post("nodes/{$node}/storage/{$storage}/upload", $params)->toArray();
    }

    /**
     * 删除存储内容
     *
     * @param string $node 节点名称
     * @param string $storage 存储ID
     * @param string $volume 卷ID
     * @return array
     */
    public function deleteContent(string $node, string $storage, string $volume): array
    {
        return $this->client->delete("nodes/{$node}/storage/{$storage}/content/{$volume}")->toArray();
    }

    /**
     * 获取存储状态
     *
     * @param string $node 节点名称
     * @param string $storage 存储ID
     * @return array
     */
    public function getStatus(string $node, string $storage): array
    {
        return $this->client->get("nodes/{$node}/storage/{$storage}/status")->toArray();
    }

    /**
     * 创建卷
     *
     * @param string $node 节点名称
     * @param string $storage 存储ID
     * @param array $params 卷参数
     * @return array
     */
    public function createVolume(string $node, string $storage, array $params): array
    {
        return $this->client->post("nodes/{$node}/storage/{$storage}/content", $params)->toArray();
    }

    /**
     * 获取ISO镜像列表
     *
     * @param string $node 节点名称
     * @param string $storage 存储ID
     * @return array
     */
    public function getISOImages(string $node, string $storage): array
    {
        return $this->getStorageContent($node, $storage, 'iso');
    }

    /**
     * 获取容器模板列表
     *
     * @param string $node 节点名称
     * @param string $storage 存储ID
     * @return array
     */
    public function getContainerTemplates(string $node, string $storage): array
    {
        return $this->getStorageContent($node, $storage, 'vztmpl');
    }

    /**
     * 获取备份列表
     *
     * @param string $node 节点名称
     * @param string $storage 存储ID
     * @return array
     */
    public function getBackups(string $node, string $storage): array
    {
        return $this->getStorageContent($node, $storage, 'backup');
    }
} 