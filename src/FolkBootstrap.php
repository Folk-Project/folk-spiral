<?php

declare(strict_types=1);

namespace Folk\Spiral;

use Folk\Sdk\Grpc\GrpcRouter;
use Folk\Sdk\Worker\HandlerLoop;
use Folk\Spiral\Config\FolkConfig;
use Psr\Container\ContainerInterface;

final class FolkBootstrap
{
    public static function register(ContainerInterface $container): void
    {
        // Only register worker hooks when running as a Folk worker
        if (!\function_exists('folk_worker_run')) {
            return;
        }

        $GLOBALS['folk_worker_boot_hook'] = static function (HandlerLoop $loop) use ($container): void {
            // HTTP handler
            $loop->registerHttpHandler(
                new Handler\SpiralHttpHandler($container),
            );

            // Jobs handler
            $loop->registerJobsHandler(
                new Jobs\SpiralJobHandler($container),
            );

            // gRPC handler (if services configured)
            $grpcServices = [];
            try {
                /** @var FolkConfig $config */
                $config = $container->get(FolkConfig::class);
                $grpcServices = $config->getGrpcServices();
            } catch (\Throwable) {
            }

            if ($grpcServices !== []) {
                $router = new GrpcRouter();
                foreach ($grpcServices as $name => $class) {
                    $router->register($name, $container->get($class));
                }
                $loop->registerGrpcHandler($router);
            }

            // Resetters — run between requests
            $loop->registerResetter(new Reset\FinalizerResetter($container));

            if ($container->has(\Cycle\ORM\ORMInterface::class)) {
                $loop->registerResetter(new Reset\CycleResetter($container));
            }
        };
    }
}
