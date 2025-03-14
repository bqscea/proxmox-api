<?php

namespace ProxmoxApi\Api;

use ProxmoxApi\Client;

/**
 * Proxmox 访问控制API类
 */
class Access
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
     * 获取用户列表
     *
     * @return array
     */
    public function getUsers(): array
    {
        return $this->client->get('access/users')->toArray();
    }

    /**
     * 创建用户
     *
     * @param array $params 用户参数
     * @return array
     */
    public function createUser(array $params): array
    {
        return $this->client->post('access/users', $params)->toArray();
    }

    /**
     * 获取特定用户信息
     *
     * @param string $userid 用户ID
     * @return array
     */
    public function getUser(string $userid): array
    {
        return $this->client->get("access/users/{$userid}")->toArray();
    }

    /**
     * 更新用户
     *
     * @param string $userid 用户ID
     * @param array $params 用户参数
     * @return array
     */
    public function updateUser(string $userid, array $params): array
    {
        return $this->client->put("access/users/{$userid}", $params)->toArray();
    }

    /**
     * 删除用户
     *
     * @param string $userid 用户ID
     * @return array
     */
    public function deleteUser(string $userid): array
    {
        return $this->client->delete("access/users/{$userid}")->toArray();
    }

    /**
     * 获取组列表
     *
     * @return array
     */
    public function getGroups(): array
    {
        return $this->client->get('access/groups')->toArray();
    }

    /**
     * 创建组
     *
     * @param array $params 组参数
     * @return array
     */
    public function createGroup(array $params): array
    {
        return $this->client->post('access/groups', $params)->toArray();
    }

    /**
     * 获取特定组信息
     *
     * @param string $groupid 组ID
     * @return array
     */
    public function getGroup(string $groupid): array
    {
        return $this->client->get("access/groups/{$groupid}")->toArray();
    }

    /**
     * 更新组
     *
     * @param string $groupid 组ID
     * @param array $params 组参数
     * @return array
     */
    public function updateGroup(string $groupid, array $params): array
    {
        return $this->client->put("access/groups/{$groupid}", $params)->toArray();
    }

    /**
     * 删除组
     *
     * @param string $groupid 组ID
     * @return array
     */
    public function deleteGroup(string $groupid): array
    {
        return $this->client->delete("access/groups/{$groupid}")->toArray();
    }

    /**
     * 获取角色列表
     *
     * @return array
     */
    public function getRoles(): array
    {
        return $this->client->get('access/roles')->toArray();
    }

    /**
     * 创建角色
     *
     * @param array $params 角色参数
     * @return array
     */
    public function createRole(array $params): array
    {
        return $this->client->post('access/roles', $params)->toArray();
    }

    /**
     * 获取特定角色信息
     *
     * @param string $roleid 角色ID
     * @return array
     */
    public function getRole(string $roleid): array
    {
        return $this->client->get("access/roles/{$roleid}")->toArray();
    }

    /**
     * 更新角色
     *
     * @param string $roleid 角色ID
     * @param array $params 角色参数
     * @return array
     */
    public function updateRole(string $roleid, array $params): array
    {
        return $this->client->put("access/roles/{$roleid}", $params)->toArray();
    }

    /**
     * 删除角色
     *
     * @param string $roleid 角色ID
     * @return array
     */
    public function deleteRole(string $roleid): array
    {
        return $this->client->delete("access/roles/{$roleid}")->toArray();
    }

    /**
     * 获取权限列表
     *
     * @return array
     */
    public function getAcl(): array
    {
        return $this->client->get('access/acl')->toArray();
    }

    /**
     * 更新权限
     *
     * @param array $params 权限参数
     * @return array
     */
    public function updateAcl(array $params): array
    {
        return $this->client->put('access/acl', $params)->toArray();
    }

    /**
     * 获取域列表
     *
     * @return array
     */
    public function getDomains(): array
    {
        return $this->client->get('access/domains')->toArray();
    }

    /**
     * 创建域
     *
     * @param array $params 域参数
     * @return array
     */
    public function createDomain(array $params): array
    {
        return $this->client->post('access/domains', $params)->toArray();
    }

    /**
     * 获取特定域信息
     *
     * @param string $realm 域ID
     * @return array
     */
    public function getDomain(string $realm): array
    {
        return $this->client->get("access/domains/{$realm}")->toArray();
    }

    /**
     * 更新域
     *
     * @param string $realm 域ID
     * @param array $params 域参数
     * @return array
     */
    public function updateDomain(string $realm, array $params): array
    {
        return $this->client->put("access/domains/{$realm}", $params)->toArray();
    }

    /**
     * 删除域
     *
     * @param string $realm 域ID
     * @return array
     */
    public function deleteDomain(string $realm): array
    {
        return $this->client->delete("access/domains/{$realm}")->toArray();
    }

    /**
     * 获取当前用户权限
     *
     * @return array
     */
    public function getPermissions(): array
    {
        return $this->client->get('access/permissions')->toArray();
    }
} 