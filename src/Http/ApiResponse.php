<?php

namespace ProxmoxApi\Http;

use Psr\Http\Message\ResponseInterface;
use ProxmoxApi\Exception\ProxmoxApiException;

/**
 * Proxmox API 响应处理类
 */
class ApiResponse
{
    /**
     * @var ResponseInterface HTTP响应
     */
    private ResponseInterface $response;

    /**
     * @var array 解析后的响应数据
     */
    private array $data;

    /**
     * 构造函数
     *
     * @param ResponseInterface $response HTTP响应
     * @throws ProxmoxApiException
     */
    public function __construct(ResponseInterface $response)
    {
        $this->response = $response;
        $this->parseResponse();
    }

    /**
     * 解析响应
     *
     * @throws ProxmoxApiException
     */
    private function parseResponse(): void
    {
        $body = (string) $this->response->getBody();
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ProxmoxApiException('无法解析API响应: ' . json_last_error_msg());
        }

        $this->data = $data;

        if (isset($data['errors'])) {
            throw new ProxmoxApiException('API错误: ' . json_encode($data['errors']));
        }
    }

    /**
     * 获取原始HTTP响应
     *
     * @return ResponseInterface
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    /**
     * 获取响应状态码
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->response->getStatusCode();
    }

    /**
     * 获取响应数据
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data['data'] ?? [];
    }

    /**
     * 获取完整响应数据
     *
     * @return array
     */
    public function getFullData(): array
    {
        return $this->data;
    }

    /**
     * 检查响应是否成功
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->getStatusCode() >= 200 && $this->getStatusCode() < 300;
    }

    /**
     * 将响应转换为数组
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->getData();
    }
} 