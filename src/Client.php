<?php

namespace ProxmoxApi;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use ProxmoxApi\Exception\AuthenticationException;
use ProxmoxApi\Exception\ProxmoxApiException;
use ProxmoxApi\Http\ApiResponse;
use ProxmoxApi\Api\Nodes;
use ProxmoxApi\Api\Cluster;
use ProxmoxApi\Api\Storage;
use ProxmoxApi\Api\Access;
use ProxmoxApi\Api\Firewall;
use ProxmoxApi\Api\Backup;
use ProxmoxApi\Api\Network;
use ProxmoxApi\Api\Snapshot;

/**
 * Proxmox API 客户端主类
 */
class Client
{
    /**
     * @var HttpClient Guzzle HTTP 客户端
     */
    private HttpClient $httpClient;

    /**
     * @var array 配置选项
     */
    private array $config;

    /**
     * @var string|null 认证令牌
     */
    private ?string $ticket = null;

    /**
     * @var string|null CSRF 防护令牌
     */
    private ?string $csrfToken = null;

    /**
     * @var Nodes 节点API
     */
    public Nodes $nodes;

    /**
     * @var Cluster 集群API
     */
    public Cluster $cluster;

    /**
     * @var Storage 存储API
     */
    public Storage $storage;

    /**
     * @var Access 访问控制API
     */
    public Access $access;

    /**
     * @var Firewall 防火墙API
     */
    public Firewall $firewall;

    /**
     * @var Backup 备份API
     */
    public Backup $backup;

    /**
     * @var Network 网络API
     */
    public Network $network;

    /**
     * @var Snapshot 快照API
     */
    public Snapshot $snapshot;

    /**
     * 客户端构造函数
     *
     * @param array $config 配置选项
     */
    public function __construct(array $config)
    {
        $this->config = array_merge([
            'hostname' => null,
            'username' => null,
            'password' => null,
            'realm' => 'pam',
            'port' => 8006,
            'verify' => true,
            'timeout' => 30,
        ], $config);

        $this->validateConfig();
        $this->initHttpClient();
        $this->initApiEndpoints();
    }

    /**
     * 验证配置
     *
     * @throws \InvalidArgumentException
     */
    private function validateConfig(): void
    {
        $requiredFields = ['hostname', 'username', 'password'];
        foreach ($requiredFields as $field) {
            if (empty($this->config[$field])) {
                throw new \InvalidArgumentException("配置参数 '{$field}' 不能为空");
            }
        }
    }

    /**
     * 初始化HTTP客户端
     */
    private function initHttpClient(): void
    {
        $this->httpClient = new HttpClient([
            'base_uri' => $this->getBaseUri(),
            'verify' => $this->config['verify'],
            'timeout' => $this->config['timeout'],
        ]);
    }

    /**
     * 初始化API端点
     */
    private function initApiEndpoints(): void
    {
        $this->nodes = new Nodes($this);
        $this->cluster = new Cluster($this);
        $this->storage = new Storage($this);
        $this->access = new Access($this);
        $this->firewall = new Firewall($this);
        $this->backup = new Backup($this);
        $this->network = new Network($this);
        $this->snapshot = new Snapshot($this);
    }

    /**
     * 获取基础URI
     *
     * @return string
     */
    private function getBaseUri(): string
    {
        return sprintf(
            'https://%s:%d/api2/json/',
            $this->config['hostname'],
            $this->config['port']
        );
    }

    /**
     * 登录并获取认证令牌
     *
     * @throws AuthenticationException
     */
    public function login(): void
    {
        try {
            $response = $this->httpClient->post('access/ticket', [
                'form_params' => [
                    'username' => $this->config['username'] . '@' . $this->config['realm'],
                    'password' => $this->config['password'],
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);
            
            if (!isset($data['data']['ticket']) || !isset($data['data']['CSRFPreventionToken'])) {
                throw new AuthenticationException('无法获取认证令牌');
            }

            $this->ticket = $data['data']['ticket'];
            $this->csrfToken = $data['data']['CSRFPreventionToken'];
        } catch (GuzzleException $e) {
            throw new AuthenticationException('认证失败: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 发送API请求
     *
     * @param string $method HTTP方法
     * @param string $path API路径
     * @param array $options 请求选项
     * @return ApiResponse
     * @throws ProxmoxApiException
     */
    public function request(string $method, string $path, array $options = []): ApiResponse
    {
        if ($this->ticket === null) {
            $this->login();
        }

        $options['cookies'] = [
            'PVEAuthCookie' => $this->ticket,
        ];

        if (in_array(strtoupper($method), ['POST', 'PUT', 'DELETE'])) {
            $options['headers'] = [
                'CSRFPreventionToken' => $this->csrfToken,
            ];
        }

        try {
            $response = $this->httpClient->request($method, $path, $options);
            return new ApiResponse($response);
        } catch (GuzzleException $e) {
            throw new ProxmoxApiException('API请求失败: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 发送GET请求
     *
     * @param string $path API路径
     * @param array $params 查询参数
     * @return ApiResponse
     */
    public function get(string $path, array $params = []): ApiResponse
    {
        return $this->request('GET', $path, ['query' => $params]);
    }

    /**
     * 发送POST请求
     *
     * @param string $path API路径
     * @param array $data 表单数据
     * @return ApiResponse
     */
    public function post(string $path, array $data = []): ApiResponse
    {
        return $this->request('POST', $path, ['form_params' => $data]);
    }

    /**
     * 发送PUT请求
     *
     * @param string $path API路径
     * @param array $data 表单数据
     * @return ApiResponse
     */
    public function put(string $path, array $data = []): ApiResponse
    {
        return $this->request('PUT', $path, ['form_params' => $data]);
    }

    /**
     * 发送DELETE请求
     *
     * @param string $path API路径
     * @param array $params 查询参数
     * @return ApiResponse
     */
    public function delete(string $path, array $params = []): ApiResponse
    {
        return $this->request('DELETE', $path, ['query' => $params]);
    }

    /**
     * 获取节点列表
     *
     * @return array
     */
    public function getNodes(): array
    {
        return $this->nodes->getList();
    }

    /**
     * 获取节点上的虚拟机列表
     *
     * @param string $node 节点名称
     * @return array
     */
    public function getNodeVMs(string $node): array
    {
        return $this->nodes->getVMs($node);
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
        return $this->nodes->getVMStatus($node, $vmid);
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
        return $this->nodes->startVM($node, $vmid);
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
        return $this->nodes->stopVM($node, $vmid);
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
        return $this->nodes->createVM($node, $params);
    }

    /**
     * 批量操作虚拟机
     *
     * @param string $node 节点名称
     * @param array $vmids 虚拟机ID数组
     * @param string $action 操作 (start, stop, reboot, suspend, resume)
     * @return array 操作结果数组
     */
    public function batchVMAction(string $node, array $vmids, string $action): array
    {
        $results = [];
        $actionMethod = '';

        switch ($action) {
            case 'start':
                $actionMethod = 'startVM';
                break;
            case 'stop':
                $actionMethod = 'stopVM';
                break;
            case 'reboot':
                $actionMethod = 'rebootVM';
                break;
            case 'suspend':
                $actionMethod = 'suspendVM';
                break;
            case 'resume':
                $actionMethod = 'resumeVM';
                break;
            default:
                throw new \InvalidArgumentException("不支持的操作: {$action}");
        }

        foreach ($vmids as $vmid) {
            try {
                $results[$vmid] = $this->nodes->$actionMethod($node, $vmid);
            } catch (\Exception $e) {
                $results[$vmid] = ['error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * 批量创建虚拟机
     *
     * @param string $node 节点名称
     * @param array $templates 模板参数数组
     * @return array 创建结果数组
     */
    public function batchCreateVMs(string $node, array $templates): array
    {
        $results = [];

        foreach ($templates as $template) {
            try {
                $results[$template['vmid']] = $this->nodes->createVM($node, $template);
            } catch (\Exception $e) {
                $results[$template['vmid']] = ['error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * 批量删除虚拟机
     *
     * @param string $node 节点名称
     * @param array $vmids 虚拟机ID数组
     * @return array 删除结果数组
     */
    public function batchDeleteVMs(string $node, array $vmids): array
    {
        $results = [];

        foreach ($vmids as $vmid) {
            try {
                $results[$vmid] = $this->nodes->deleteVM($node, $vmid);
            } catch (\Exception $e) {
                $results[$vmid] = ['error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * 批量克隆虚拟机
     *
     * @param string $node 节点名称
     * @param int $sourceVmid 源虚拟机ID
     * @param array $targetVmids 目标虚拟机ID数组
     * @param array $params 克隆参数
     * @return array 克隆结果数组
     */
    public function batchCloneVMs(string $node, int $sourceVmid, array $targetVmids, array $params = []): array
    {
        $results = [];

        foreach ($targetVmids as $targetVmid) {
            try {
                $cloneParams = array_merge($params, ['newid' => $targetVmid]);
                $results[$targetVmid] = $this->nodes->cloneVM($node, $sourceVmid, $cloneParams);
            } catch (\Exception $e) {
                $results[$targetVmid] = ['error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * 获取所有节点上的所有虚拟机
     *
     * @return array
     */
    public function getAllVMs(): array
    {
        $nodes = $this->getNodes();
        $allVMs = [];

        foreach ($nodes as $node) {
            $nodeName = $node['node'];
            try {
                $vms = $this->getNodeVMs($nodeName);
                $allVMs[$nodeName] = $vms;
            } catch (\Exception $e) {
                $allVMs[$nodeName] = ['error' => $e->getMessage()];
            }
        }

        return $allVMs;
    }

    /**
     * 获取所有节点上的所有容器
     *
     * @return array
     */
    public function getAllContainers(): array
    {
        $nodes = $this->getNodes();
        $allContainers = [];

        foreach ($nodes as $node) {
            $nodeName = $node['node'];
            try {
                $containers = $this->nodes->getContainers($nodeName);
                $allContainers[$nodeName] = $containers;
            } catch (\Exception $e) {
                $allContainers[$nodeName] = ['error' => $e->getMessage()];
            }
        }

        return $allContainers;
    }

    /**
     * 获取所有节点上的所有存储
     *
     * @return array
     */
    public function getAllStorages(): array
    {
        $nodes = $this->getNodes();
        $allStorages = [];

        foreach ($nodes as $node) {
            $nodeName = $node['node'];
            try {
                $storages = $this->storage->getNodeStorages($nodeName);
                $allStorages[$nodeName] = $storages;
            } catch (\Exception $e) {
                $allStorages[$nodeName] = ['error' => $e->getMessage()];
            }
        }

        return $allStorages;
    }

    /**
     * 获取所有节点上的所有备份
     *
     * @return array
     */
    public function getAllBackups(): array
    {
        $nodes = $this->getNodes();
        $allBackups = [];

        foreach ($nodes as $node) {
            $nodeName = $node['node'];
            try {
                $backups = $this->backup->getNodeBackups($nodeName);
                $allBackups[$nodeName] = $backups;
            } catch (\Exception $e) {
                $allBackups[$nodeName] = ['error' => $e->getMessage()];
            }
        }

        return $allBackups;
    }
} 