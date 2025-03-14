# Proxmox API Client

This is a PHP 8.0+ wrapper for the Proxmox VE API, providing a simple and easy-to-use interface for interacting with Proxmox VE servers.

![Version](https://img.shields.io/badge/Version-1.0.0-blue.svg)
![PHP Version](https://img.shields.io/badge/PHP-8.0+-green.svg)
![License](https://img.shields.io/badge/License-MIT-yellow.svg)

[中文版](README.md)

## Installation

Install via Composer:

```bash
composer require bqscea/proxmox-api
```

## Basic Usage

```php
<?php

require 'vendor/autoload.php';

use ProxmoxApi\Client;

// Create client instance
$client = new Client([
    'hostname' => 'your-proxmox-server.com',
    'username' => 'root',
    'password' => 'your-password',
    'realm' => 'pam', // Default is 'pam'
    'port' => 8006,   // Default is 8006
    'verify' => true  // Whether to verify SSL certificate
]);

// Get list of nodes
$nodes = $client->getNodes();

// Get list of VMs for a specific node
$vms = $client->getNodeVMs('node1');

// Get status of a specific VM
$vmStatus = $client->getVMStatus('node1', 100);

// Start a VM
$client->startVM('node1', 100);

// Stop a VM
$client->stopVM('node1', 100);

// Create a VM
$client->createVM('node1', [
    'vmid' => 101,
    'name' => 'test-vm',
    'memory' => 1024,
    'cores' => 2,
    // Other parameters...
]);
```

## Features

- Complete support for Proxmox VE API
- Simple and intuitive interface
- Support for all node, cluster, storage, VM, and container operations
- Automatic authentication and session management
- Support for asynchronous operations
- Detailed error handling and logging
- Support for Hyperf coroutine environment (requires hyperf/guzzle)

## Documentation

For detailed API documentation, please refer to [here](docs/index.md).

## License

MIT

## Testing

This project includes comprehensive unit tests to ensure code quality and functionality.

### Running Tests

```bash
# Run all tests
composer test

# Generate test coverage report
composer test-coverage

# Run code style check
composer cs-check

# Automatically fix code style issues
composer cs-fix
```

### Test Coverage

Tests cover the following modules:

1. **Automation Task Base Class** - Tests for logging, task waiting, and VM filtering functionality
2. **Batch VM Operation Task** - Tests for batch starting, stopping, cloning, and other operations
3. **Batch Backup Task** - Tests for backup creation, scheduling, and old backup cleanup
4. **Resource Monitoring Task** - Tests for resource monitoring, threshold checking, and alert handling

### Writing New Tests

If you want to add tests for new features, please follow these steps:

1. Create a corresponding test class in the `tests` directory
2. Use PHPUnit assertions to validate functionality
3. Run `composer test` to ensure all tests pass

## Using with Hyperf

This client supports using coroutines for HTTP requests in the Hyperf framework, improving concurrent performance.

### Install Hyperf Guzzle Component

```bash
composer require hyperf/guzzle
```

### Enable Coroutine Support

```php
<?php

use ProxmoxApi\Client;

// Create client instance with coroutine support enabled
$client = new Client([
    'hostname' => 'your-proxmox-server.com',
    'username' => 'root',
    'password' => 'your-password',
    'realm' => 'pam',
    'port' => 8006,
    'verify' => false,
    'use_coroutine' => true, // Enable coroutine support
]);

// Get list of nodes
$nodes = $client->getNodes();
```

With coroutine support enabled, HTTP requests will use the Swoole coroutine handler, allowing them to execute non-blockingly in a coroutine environment, improving concurrent performance. 