<?php

namespace App\Controller\Debug;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/debug')]
class ClearCacheController extends AbstractController
{
    public function __construct(private KernelInterface $kernel, private LoggerInterface $logger)
    {
    }

    /**
     * Clear Symfony cache directories under var/cache
     * - Allowed automatically when kernel is in debug mode
     * - Otherwise requires ?key=<CLEAR_CACHE_KEY env value>.
     */
    #[Route('/clear-cache', name: 'debug_clear_cache', methods: ['POST', 'GET'])]
    public function clear(Request $request): Response
    {
        $isDebug = $this->kernel->isDebug();
        $providedKey = $request->query->get('key', '');
        $envKey = getenv('CLEAR_CACHE_KEY') ?: ($_ENV['CLEAR_CACHE_KEY'] ?? '');

        if (!$isDebug && !$envKey) {
            $this->logger->warning('Clear cache endpoint blocked (no debug mode and no CLEAR_CACHE_KEY set).');

            return new JsonResponse(['status' => 'error', 'message' => 'Not allowed'], Response::HTTP_FORBIDDEN);
        }

        if (!$isDebug && $envKey && $providedKey !== $envKey) {
            $this->logger->warning('Clear cache endpoint forbidden: invalid key provided.');

            return new JsonResponse(['status' => 'error', 'message' => 'Invalid key'], Response::HTTP_FORBIDDEN);
        }

        $projectDir = dirname($this->kernel->getProjectDir()); // kernel projectDir usually <root>/; keep safe
        // In Symfony kernel->getProjectDir() points to project root. var/cache is under that.
        $cacheDir = $this->kernel->getProjectDir().DIRECTORY_SEPARATOR.'var'.DIRECTORY_SEPARATOR.'cache';

        $filesystem = new Filesystem();
        $result = ['deleted' => 0, 'errors' => []];

        if (!is_dir($cacheDir)) {
            return new JsonResponse(['status' => 'ok', 'message' => 'No cache directory found', 'cacheDir' => $cacheDir]);
        }

        // iterate children of var/cache and remove them
        $entries = glob($cacheDir.DIRECTORY_SEPARATOR.'*');
        if (false === $entries) {
            return new JsonResponse(['status' => 'error', 'message' => 'Failed to read cache directory'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        foreach ($entries as $entry) {
            try {
                // Safety: ensure path starts with cacheDir
                if (0 !== strpos(realpath($entry), realpath($cacheDir))) {
                    $result['errors'][] = "Skipping unexpected path: $entry";
                    continue;
                }

                $filesystem->remove($entry);
                ++$result['deleted'];
            } catch (IOExceptionInterface $e) {
                $this->logger->error('Failed removing cache entry: '.$entry.' - '.$e->getMessage());
                $result['errors'][] = $e->getMessage();
            }
        }

        $this->logger->info('Debug clear-cache executed', $result);

        return new JsonResponse(['status' => 'ok', 'result' => $result]);
    }
}
