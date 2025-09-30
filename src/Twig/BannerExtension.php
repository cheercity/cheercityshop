<?php

namespace App\Twig;

use App\Service\BannerService;
use Psr\Log\LoggerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class BannerExtension extends AbstractExtension
{
    private BannerService $bannerService;
    private LoggerInterface $logger;

    public function __construct(BannerService $bannerService, LoggerInterface $logger)
    {
        $this->bannerService = $bannerService;
        $this->logger = $logger;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('banners', [$this, 'getBanners']),
            new TwigFunction('banner_positions', [$this, 'getBannerPositions']),
        ];
    }

    /**
     * Get banners for a specific position.
     */
    public function getBanners(string $position = 'Main', int $ttl = 1800): array
    {
        try {
            return $this->bannerService->getBanners($position, $ttl);
        } catch (\Exception $e) {
            $this->logger->error('BannerExtension: Error getting banners', [
                'position' => $position,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get all available banner positions.
     */
    public function getBannerPositions(int $ttl = 1800): array
    {
        try {
            return $this->bannerService->getAllBannerPositions($ttl);
        } catch (\Exception $e) {
            $this->logger->error('BannerExtension: Error getting banner positions', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
