<?php

namespace ProxmoxApi\Http;

use GuzzleHttp\Handler\CurlFactory;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\HandlerStack;
use Hyperf\Engine\Http\Client as HttpClient;
use Hyperf\Engine\Http\RawResponse;
use Hyperf\Guzzle\CoroutineHandler;
use Hyperf\Utils\Coroutine;
use Swoole\Coroutine as SwooleCoroutine;

/**
 * Guzzle协程处理器
 * 用于在Hyperf环境中支持协程HTTP请求
 */
class GuzzleHandler
{
    /**
     * 创建处理器栈
     *
     * @param array $options 配置选项
     * @return HandlerStack
     */
    public static function create(array $options = []): HandlerStack
    {
        $handler = null;
        
        // 检查是否在协程环境中
        if (self::inCoroutine()) {
            // 使用Hyperf的协程处理器
            $handler = new CoroutineHandler();
        } elseif (class_exists(CurlMultiHandler::class)) {
            // 使用Guzzle的CurlMultiHandler
            $handler = new CurlMultiHandler($options);
        } elseif (class_exists(CurlHandler::class)) {
            // 使用Guzzle的CurlHandler
            $handler = new CurlHandler($options);
        }

        return HandlerStack::create($handler);
    }

    /**
     * 检查是否在协程环境中
     *
     * @return bool
     */
    public static function inCoroutine(): bool
    {
        if (class_exists(Coroutine::class)) {
            return Coroutine::inCoroutine();
        }
        
        if (class_exists(SwooleCoroutine::class)) {
            return SwooleCoroutine::getCid() > 0;
        }
        
        return false;
    }
} 