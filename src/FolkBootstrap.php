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
            // Stamp request_id onto application logs for correlation with Folk's
            // Rust-side access log. Only applies when the default logger is Monolog;
            // reads the id at log time, so nothing to reset between requests.
            try {
                if ($container->has(\Psr\Log\LoggerInterface::class)) {
                    $logger = $container->get(\Psr\Log\LoggerInterface::class);
                    if ($logger instanceof \Monolog\Logger) {
                        $logger->pushProcessor(new Log\FolkRequestIdProcessor());
                    }
                }
            } catch (\Throwable) {
                // Logger integration is optional — never fail the worker bootstrap
            }

            // HTTP handler — streaming size limit from env (0 = unlimited).
            $maxBytes = (int) (getenv('FOLK_STREAM_MAX_BYTES') ?: 0);
            $loop->registerHttpHandler(
                new Handler\SpiralHttpHandler($container, $maxBytes),
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
            $loop->registerResetter(new \Folk\Sdk\Reset\TempUploadResetter());

            if ($container->has(\Cycle\ORM\ORMInterface::class)) {
                $loop->registerResetter(new Reset\CycleResetter($container));
            }
        };
    }
}
