<?php

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

class BannerService
{
    private FileMakerClient $fileMakerClient;
    private CacheItemPoolInterface $cache;
    private LoggerInterface $logger;

    public function __construct(
        FileMakerClient $fileMakerClient,
        CacheItemPoolInterface $cache,
        LoggerInterface $logger,
        #[\SensitiveParameter]
        string $bannerLayout = 'sym_Banner',
    ) {
        $this->fileMakerClient = $fileMakerClient;
        $this->cache = $cache;
        $this->logger = $logger;
        $this->bannerLayout = $bannerLayout;
    }

    private string $bannerLayout = 'sym_Banner';

    /**
     * Get banners for a specific position (e.g. 'Main', 'Secondary', etc.)
     * Only returns banners with Aktiv = 1.
     */
    public function getBanners(string $position = 'Main', int $ttl = 1800): array
    {
        $cacheKey = 'banners_'.strtolower($position).'_v1';

        // Check cache first (unless TTL is 0 for fresh data)
        if ($ttl > 0) {
            $cacheItem = $this->cache->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                $this->logger->info('BannerService: Cache hit for position', ['position' => $position]);

                return $cacheItem->get();
            }
        }

        try {
            // Fetch from FileMaker: get all banners and filter by criteria
            $response = $this->fileMakerClient->list($this->bannerLayout);

            if (!isset($response['response']['data'])) {
                $this->logger->warning('BannerService: No data in FileMaker response', ['position' => $position]);

                return [];
            }

            $banners = [];
            foreach ($response['response']['data'] as $record) {
                $fieldData = $record['fieldData'] ?? [];

                // Filter: only active banners for the requested position
                $recordAktiv = $fieldData['Aktiv'] ?? $fieldData['aktiv'] ?? '0';
                $recordPosition = $fieldData['position'] ?? $fieldData['Position'] ?? '';

                // normalize aktiv to string and position to trimmed lowercase for reliable comparison
                $recordAktivStr = trim((string) $recordAktiv);
                $recordPositionNorm = mb_strtolower(trim((string) $recordPosition));
                $positionNorm = mb_strtolower(trim((string) $position));

                if ('1' === $recordAktivStr && $recordPositionNorm === $positionNorm) {
                    // Date range filtering: Freigabe ab / Freigabe bis
                    // Accept various date formats returned by FileMaker; use strtotime fallback.
                    $now = new \DateTimeImmutable('now');
                    $dateStartRaw = $fieldData['date_start'] ?? $fieldData['Date_Start'] ?? $fieldData['freigabe_ab'] ?? $fieldData['Freigabe_ab'] ?? null;
                    $dateEndRaw = $fieldData['date_end'] ?? $fieldData['Date_End'] ?? $fieldData['freigabe_bis'] ?? $fieldData['Freigabe_bis'] ?? null;

                    $inRange = true;
                    if ($dateStartRaw) {
                        $ts = strtotime((string) $dateStartRaw);
                        if (false !== $ts) {
                            $start = (new \DateTimeImmutable())->setTimestamp($ts);
                            if ($now < $start) {
                                $inRange = false;
                            }
                        }
                    }
                    if ($dateEndRaw && $inRange) {
                        $ts = strtotime((string) $dateEndRaw);
                        if (false !== $ts) {
                            $end = (new \DateTimeImmutable())->setTimestamp($ts);
                            if ($now > $end) {
                                $inRange = false;
                            }
                        }
                    }

                    if (!$inRange) {
                        continue; // skip banners outside display date range
                    }

                    // Map FileMaker fields to our banner structure (include more fields)
                    // Field mapping requested:
                    // - title = description
                    // - bezeichnung = beschreibung
                    // - alt_tag = image_title
                    // - source = video_lnk || image_lnk || image
                    $description = $fieldData['description'] ?? $fieldData['beschreibung'] ?? $fieldData['Beschreibung'] ?? '';
                    $imageLnk = $fieldData['image_lnk'] ?? $fieldData['image'] ?? $fieldData['bild'] ?? $fieldData['Bild'] ?? '';
                    $videoLnk = $fieldData['video_lnk'] ?? $fieldData['Video_Lnk'] ?? '';
                    $imageTitle = $fieldData['image_title'] ?? $fieldData['Image_Title'] ?? $fieldData['image_title_lnk'] ?? '';

                    $source = '';
                    if (!empty($videoLnk)) {
                        $source = $videoLnk;
                    } elseif (!empty($imageLnk)) {
                        $source = $imageLnk;
                    }

                    $banner = [
                        'id' => $record['recordId'] ?? null,
                        // Titel should show the description field per request
                        'title' => $description,
                        // Bezeichnung maps to Beschreibung
                        'bezeichnung' => $description,
                        'subtitle' => $fieldData['untertitel'] ?? $fieldData['Untertitel'] ?? '',
                        'description' => $description,
                        // Bild / image link
                        'image' => $imageLnk,
                        'alt_tag' => $imageTitle,
                        'source' => $source,
                        'link' => $fieldData['link'] ?? $fieldData['Link'] ?? '',
                        'video_lnk' => $videoLnk,
                        'h1_content' => $fieldData['h1_content'] ?? $fieldData['H1_Content'] ?? '',
                        'button_text' => $fieldData['button_text'] ?? $fieldData['Button_Text'] ?? 'View Collection',
                        'position' => $fieldData['position'] ?? $fieldData['Position'] ?? $position,
                        'sortorder' => (int) ($fieldData['sortorder'] ?? $fieldData['Sortorder'] ?? 0),
                        // keep normalized aktiv string for clarity
                        'aktiv' => $recordAktivStr,
                        'date_start' => $dateStartRaw,
                        'date_end' => $dateEndRaw,
                        'raw_fieldData' => $fieldData,
                    ];

                    $banners[] = $banner;
                }
            }

            // Sort by Sortorder field
            usort($banners, function ($a, $b) {
                return $a['sortorder'] <=> $b['sortorder'];
            });

            $this->logger->info('BannerService: Retrieved banners from FileMaker', [
                'position' => $position,
                'count' => count($banners),
            ]);

            // Cache the result (if TTL > 0)
            if ($ttl > 0) {
                $cacheItem = $this->cache->getItem($cacheKey);
                $cacheItem->set($banners);
                $cacheItem->expiresAfter($ttl);
                $this->cache->save($cacheItem);
            }

            return $banners;
        } catch (\Exception $e) {
            $this->logger->error('BannerService: Error fetching banners', [
                'position' => $position,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get all banner positions available in the system.
     */
    public function getAllBannerPositions(int $ttl = 1800): array
    {
        $cacheKey = 'banner_positions_v1';

        // Check cache first
        if ($ttl > 0) {
            $cacheItem = $this->cache->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                return $cacheItem->get();
            }
        }

        try {
            // Get all banners and extract unique positions
            $response = $this->fileMakerClient->list($this->bannerLayout);

            if (!isset($response['response']['data'])) {
                return [];
            }

            $positions = [];
            foreach ($response['response']['data'] as $record) {
                $fieldData = $record['fieldData'] ?? [];
                $position = $fieldData['position'] ?? $fieldData['Position'] ?? '';

                if (!empty($position) && !in_array($position, $positions)) {
                    $positions[] = $position;
                }
            }

            sort($positions);

            // Cache the result
            if ($ttl > 0) {
                $cacheItem = $this->cache->getItem($cacheKey);
                $cacheItem->set($positions);
                $cacheItem->expiresAfter($ttl);
                $this->cache->save($cacheItem);
            }

            return $positions;
        } catch (\Exception $e) {
            $this->logger->error('BannerService: Error fetching banner positions', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get banner statistics for debug purposes.
     */
    public function getBannerStats(): array
    {
        try {
            $response = $this->fileMakerClient->list($this->bannerLayout);

            if (!isset($response['response']['data'])) {
                return ['total' => 0, 'active' => 0, 'positions' => []];
            }

            $stats = [
                'total' => count($response['response']['data']),
                'active' => 0,
                'positions' => [],
            ];

            foreach ($response['response']['data'] as $record) {
                $fieldData = $record['fieldData'] ?? [];
                $aktiv = $fieldData['Aktiv'] ?? $fieldData['aktiv'] ?? '0';
                $position = $fieldData['position'] ?? $fieldData['Position'] ?? 'Unknown';
                $positionNorm = trim((string) $position);

                // normalize aktiv for stats counting
                $aktivStr = trim((string) $aktiv);
                if ('1' === $aktivStr) {
                    ++$stats['active'];
                }

                if (!isset($stats['positions'][$positionNorm])) {
                    $stats['positions'][$positionNorm] = ['total' => 0, 'active' => 0];
                }

                ++$stats['positions'][$positionNorm]['total'];
                if ('1' === $aktivStr) {
                    ++$stats['positions'][$positionNorm]['active'];
                }
            }

            return $stats;
        } catch (\Exception $e) {
            $this->logger->error('BannerService: Error getting banner stats', [
                'error' => $e->getMessage(),
            ]);

            return ['total' => 0, 'active' => 0, 'positions' => [], 'error' => $e->getMessage()];
        }
    }
}
