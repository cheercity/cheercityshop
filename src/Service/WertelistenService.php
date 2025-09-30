<?php

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

final class WertelistenService
{
    private FileMakerClient $fm;
    private CacheItemPoolInterface $cache;
    private LoggerInterface $logger;
    // Request-local memoization to avoid repeated FM lookups during one HTTP request
    private array $localCache = [];
    private string $cacheKeyPrefix = 'wertelisten_map_';

    public function __construct(FileMakerClient $fm, CacheItemPoolInterface $cache, LoggerInterface $logger)
    {
        $this->fm = $fm;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * Return a mapping for the given list name. Cached for 1 hour by default.
     * Expected FileMaker layout: sym_Wertelisten
     * Fields expected: Code, Label, ImageFile (optional)
     * @return array<string,mixed>
     */
    public function getMap(string $listName): array
    {
        // short-circuit using request-local memoization
        if (array_key_exists($listName, $this->localCache)) {
            return $this->localCache[$listName];
        }

        $cacheKey = $this->cacheKeyPrefix.$listName;
        try {
            $item = $this->cache->getItem($cacheKey);
            if ($item->isHit()) {
                return $item->get();
            }
        } catch (\Throwable $e) {
            // proceed to fetch but log
            $this->logger->warning('Cache read failed for '.$cacheKey.': '.$e->getMessage());
        }

        // First, try a list() fetch which does not rely on criteria fields being
        // present on the layout. This is often more robust for static lookup
        // tables like sym_Wertelisten. We'll filter results client-side by
        // ListName. If list() fails for any reason, fall back to a find().
        $rows = [];
        try {
            $this->logger->debug(sprintf('WertelistenService: attempting list() for "%s"', $listName));
            $resList = $this->fm->list('sym_Wertelisten', 1000, 1);
            $rows = $resList['response']['data'] ?? [];
        } catch (\Throwable $eList) {
            $this->logger->warning('WertelistenService: list() failed for '.$listName.': '.$eList->getMessage());
            // To avoid a thundering herd of concurrent fallback find() calls
            // write a short-lived provisional negative cache entry before attempting find().
            try {
                $this->logger->debug('WertelistenService: writing provisional negative cache for '.$cacheKey);
                $tmp = $this->cache->getItem($cacheKey);
                $tmp->set([]);
                $tmp->expiresAfter(10); // short lock window while one process performs find()
                $this->cache->save($tmp);
            } catch (\Throwable $eTmp) {
                $this->logger->warning('WertelistenService: failed to write provisional cache '.$cacheKey.': '.$eTmp->getMessage());
            }

            // Fall back to find() which may work if the layout exposes ListName
            try {
                $this->logger->debug(sprintf('WertelistenService: falling back to find() for "%s" (excluding empty Code fields)', $listName));
                // Use a compound find: records with ListName == $listName AND omit records where Code is empty.
                // FileMaker Data API supports an 'omit' boolean on a query object to exclude matching records.
                $findQuery = [
                    ['ListName' => $listName],
                    // Omit records where Code == '' (i.e. exclude empty Code fields)
                    ['Code' => '', 'omit' => true],
                ];
                $res = $this->fm->find('sym_Wertelisten', $findQuery, ['limit' => 1000]);
                $rows = $res['response']['data'] ?? [];
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                $this->logger->error('Failed to fetch wertelisten '.$listName.': '.$msg);
                $this->localCache[$listName] = [];

                $isFieldMissing = (false !== stripos($msg, 'Field is missing') || false !== stripos($msg, '"code":"102"') || false !== stripos($msg, '"code":102'));
                $ttl = $isFieldMissing ? 3600 : 60;

                // ensure we write a negative cache entry to avoid repeated failing FM calls
                try {
                    $negItem = $this->cache->getItem($cacheKey);
                    $negItem->set([]);
                    $negItem->expiresAfter($ttl);
                    $this->cache->save($negItem);
                    $this->logger->debug('WertelistenService: negative cache written for '.$cacheKey.' ttl='.$ttl);
                } catch (\Throwable $e2) {
                    $this->logger->warning('Failed to write negative cache for '.$cacheKey.': '.$e2->getMessage());
                }

                return [];
            }
        }

        $mapped = [];
        foreach ($rows as $r) {
            $fields = $r['fieldData'] ?? [];
            // If we used list(), it returns many lists — filter by ListName
            if (!empty($fields) && isset($fields['ListName']) && $fields['ListName'] !== $listName) {
                continue;
            }

            // Support multiple possible field names coming from FM layouts
            // e.g. English: Code / Label / ImageFile
            // German:  Farbcode / Farbbezeichnung / Farbcode_Image_File
            $code = $fields['Code'] ?? $fields['Farbcode'] ?? $fields['code'] ?? null;
            if (!$code) {
                continue;
            }

            $label = $fields['Label'] ?? $fields['Bezeichnung'] ?? $fields['Farbbezeichnung'] ?? $fields['label'] ?? '';
            $image = $fields['ImageFile'] ?? $fields['Farbcode_Image_File'] ?? $fields['Image'] ?? '';

            $mapped[$code] = [
                'label' => $label,
                'image' => $image,
            ];
        }

        // persist
        try {
            $item->set($mapped);
            $item->expiresAfter(3600);
            $this->cache->save($item);
            // also populate request-local memo so subsequent calls this request are fast
            $this->localCache[$listName] = $mapped;
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to save wertelisten cache '.$cacheKey.': '.$e->getMessage());
        }

        return $mapped;
    }

    /**
     * Convenience: get label for code from a list
     */
    public function getLabel(string $listName, string $code): string
    {
        $map = $this->getMap($listName);
        return $map[$code]['label'] ?? '';
    }

    /**
     * Convenience: get image file name (if any) for code from a list
     */
    public function getImage(string $listName, string $code): string
    {
        $map = $this->getMap($listName);
        return $map[$code]['image'] ?? '';
    }
}
