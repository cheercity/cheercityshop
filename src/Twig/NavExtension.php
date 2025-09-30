<?php

namespace App\Twig;

use App\Service\NavService;
use Psr\Log\LoggerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class NavExtension extends AbstractExtension
{
    private LoggerInterface $logger;

    public function __construct(private NavService $nav, LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('nav_menu', function (?string $layout = null, array $filter = ['Status' => '1'], int $ttl = 300) {
                try {
                    return $this->nav->getMenu($layout, $filter, $ttl);
                } catch (\Throwable $e) {
                    // Log the exception so we can diagnose backend/FileMaker issues while keeping the template safe.
                    $this->logger->error('nav_menu failed: '.$e->getMessage(), [
                        'exception' => $e,
                        'layout' => $layout,
                        'filter' => $filter,
                        'ttl' => $ttl,
                    ]);
                    // Fallback: write a minimal debug file so remote environments without configured Monolog
                    // still receive diagnostics. This is intentionally simple and appends to var/log/nav_debug.log.
                    try {
                        $logDir = __DIR__.'/../../var/log';
                        if (!is_dir($logDir)) {
                            @mkdir($logDir, 0775, true);
                        }
                        $logFile = $logDir.'/nav_debug.log';
                        $message = sprintf("[%s] nav_menu failed: %s | layout=%s | filter=%s | ttl=%d\n", (new \DateTime())->format(DATE_ATOM), $e->getMessage(), $layout, json_encode($filter, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $ttl);
                        @file_put_contents($logFile, $message.$e->getTraceAsString()."\n\n", FILE_APPEND | LOCK_EX);
                    } catch (\Throwable $ignored) {
                        // swallow - fallback logging must not break behavior
                    }

                    return [];
                }
            }),
            new TwigFunction('footer_modules', function (int $ttl = 300) {
                try {
                    return $this->nav->getFooterModules($ttl);
                } catch (\Throwable $e) {
                    $this->logger->error('footer_modules failed: '.$e->getMessage(), [
                        'exception' => $e,
                        'ttl' => $ttl,
                    ]);
                    try {
                        $logDir = __DIR__.'/../../var/log';
                        if (!is_dir($logDir)) {
                            @mkdir($logDir, 0775, true);
                        }
                        $logFile = $logDir.'/nav_debug.log';
                        $message = sprintf("[%s] footer_modules failed: %s | ttl=%d\n", (new \DateTime())->format(DATE_ATOM), $e->getMessage(), $ttl);
                        @file_put_contents($logFile, $message.$e->getTraceAsString()."\n\n", FILE_APPEND | LOCK_EX);
                    } catch (\Throwable $ignored) {
                        // swallow - fallback logging must not break behavior
                    }

                    return [];
                }
            }),
            new TwigFunction('footer_groups', function (int $ttl = 300) {
                try {
                    return $this->nav->getFooterGroupsByModul($ttl);
                } catch (\Throwable $e) {
                    $this->logger->error('footer_groups failed: '.$e->getMessage(), [
                        'exception' => $e,
                        'ttl' => $ttl,
                    ]);

                    return [];
                }
            }),
        ];
    }
}
