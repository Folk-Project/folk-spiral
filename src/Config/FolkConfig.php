<?php

declare(strict_types=1);

namespace Folk\Spiral\Config;

use Spiral\Core\InjectableConfig;

/**
 * Folk configuration for Spiral.
 *
 * Config file: app/config/folk.php
 *
 * ```php
 * return [
 *     'grpc' => [
 *         'services' => [
 *             'helloworld.Greeter' => App\Grpc\GreeterService::class,
 *         ],
 *     ],
 * ];
 * ```
 */
final class FolkConfig extends InjectableConfig
{
    public const CONFIG = 'folk';

    /** @var array<string, mixed> */
    protected array $config = [
        'grpc' => [
            'services' => [],
        ],
    ];

    /**
     * @return array<string, class-string>
     */
    public function getGrpcServices(): array
    {
        return $this->config['grpc']['services'] ?? [];
    }
}
