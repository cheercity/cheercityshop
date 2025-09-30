<?php

namespace App\Controller\Debug;

use App\Service\BannerService;
use App\Service\FileMakerClient;
use App\Service\FileMakerLayoutRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/debug')]
class BannerDebugController extends AbstractController
{
    private BannerService $bannerService;

    public function __construct(BannerService $bannerService, private FileMakerLayoutRegistry $layouts)
    {
        $this->bannerService = $bannerService;
    }

    #[Route('/banner', name: 'debug_banner', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $position = $request->query->get('position', 'Main');
        $ttl = (int) $request->query->get('ttl', 0); // Default to no cache for debug
        $aktivFilter = $request->query->get('aktiv'); // optional filter: '1' to show only active

        // Get banners for the specified position
        $banners = $this->bannerService->getBanners($position, $ttl);

        // If aktiv filter is requested, keep only records that have Aktiv == 1
        if (null !== $aktivFilter && '1' === (string) $aktivFilter) {
            $banners = array_values(array_filter($banners, function ($b) {
                // BannerService may return Aktiv as '1', 1, true, or inside 'aktiv' key — be permissive
                $val = null;
                if (isset($b['aktiv'])) {
                    $val = $b['aktiv'];
                } elseif (isset($b['Aktiv'])) {
                    $val = $b['Aktiv'];
                }
                // normalize
                if (null === $val) {
                    return false;
                }
                if (is_bool($val)) {
                    return true === $val;
                }
                if (is_int($val)) {
                    return 1 === $val;
                }
                $s = trim((string) $val);

                return '1' === $s || 'true' === mb_strtolower($s);
            }));
        }

        // Get all available positions
        $allPositions = $this->bannerService->getAllBannerPositions(0);

        // Get banner statistics
        $stats = $this->bannerService->getBannerStats();

        return $this->render('debug/banner/index.html.twig', [
            'banners' => $banners,
            'currentPosition' => $position,
            'allPositions' => $allPositions,
            'stats' => $stats,
            'ttl' => $ttl,
            'aktiv' => $aktivFilter,
        ]);
    }

    #[Route('/banner/position/{position}', name: 'debug_banner_position', methods: ['GET'])]
    public function byPosition(string $position, Request $request): Response
    {
        $ttl = (int) $request->query->get('ttl', 0);

        $banners = $this->bannerService->getBanners($position, $ttl);

        return $this->render('debug/banner/position.html.twig', [
            'banners' => $banners,
            'position' => $position,
            'ttl' => $ttl,
        ]);
    }

    #[Route('/banner/json', name: 'debug_banner_json', methods: ['GET'])]
    public function jsonData(Request $request): Response
    {
        $position = $request->query->get('position', 'Main');
        $ttl = (int) $request->query->get('ttl', 0);

        $banners = $this->bannerService->getBanners($position, $ttl);

        return $this->json([
            'position' => $position,
            'count' => count($banners),
            'banners' => $banners,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    #[Route('/banner/stats', name: 'debug_banner_stats', methods: ['GET'])]
    public function statsJson(): Response
    {
        $stats = $this->bannerService->getBannerStats();

        return $this->json($stats);
    }

    #[Route('/banner/raw-json', name: 'debug_banner_raw_json', methods: ['GET'])]
    public function jsonRaw(FileMakerClient $fm, Request $request, LoggerInterface $logger): Response
    {
        $limit = (int) $request->query->get('limit', 1000);
        $positionFilter = $request->query->get('position'); // exact match if provided
        $aktivFilter = $request->query->get('aktiv'); // '1', '0' or null

        // normalize aktiv filter: accept '1', '0', 'all'
        if (null !== $aktivFilter) {
            $aktivFilter = (string) $aktivFilter;
            if (in_array(strtolower($aktivFilter), ['all', 'any'], true)) {
                $aktivFilter = null;
            }
        }

        try {
            $layout = $this->layouts->get('banner', 'sym_Banner');
            $result = $fm->list($layout, $limit);

            $rows = [];
            foreach ($result['response']['data'] ?? [] as $rec) {
                $fields = $rec['fieldData'] ?? [];

                // apply position filter if provided (case-insensitive, trimmed)
                if (null !== $positionFilter) {
                    $pos = trim((string) ($fields['position'] ?? $fields['Position'] ?? ''));
                    $posNormalized = mb_strtolower($pos);
                    $filterNormalized = mb_strtolower(trim((string) $positionFilter));
                    if ($posNormalized !== $filterNormalized) {
                        continue;
                    }
                }

                // apply aktiv filter if provided
                if (null !== $aktivFilter) {
                    $aktivVal = (string) ($fields['Aktiv'] ?? $fields['aktiv'] ?? '');
                    // normalize booleans/integers to '1' or '0' strings
                    if ('' === $aktivVal) {
                        $aktivVal = '0';
                    } else {
                        $aktivVal = (string) $aktivVal;
                    }

                    if ('1' === $aktivFilter && '1' !== $aktivVal) {
                        continue;
                    }
                    if ('0' === $aktivFilter && '1' === $aktivVal) {
                        continue;
                    }
                }

                $rows[] = [
                    'recordId' => $rec['recordId'] ?? null,
                    'fieldData' => $fields,
                ];
            }

            return $this->json([
                'count' => count($rows),
                'rows' => $rows,
                'timestamp' => date('c'),
                'filters' => [
                    'position' => $positionFilter,
                    'aktiv' => $aktivFilter,
                ],
            ]);
        } catch (\Throwable $e) {
            $fmLogger = $logger;
            if ($this->container->has('monolog.logger.filemaker')) {
                $fmLogger = $this->container->get('monolog.logger.filemaker');
            }
            $fmLogger->error('sym_Banner raw-json error: '.$e->getMessage(), ['exception' => $e, 'layout' => $layout ?? 'sym_Banner']);

            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}
