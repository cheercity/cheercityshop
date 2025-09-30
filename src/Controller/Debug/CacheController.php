<?php

namespace App\Controller\Debug;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/debug', name: 'debug_')]
class CacheController extends AbstractController
{
    #[Route('/cache-clear', name: 'cache_clear')]
    public function clearCache(KernelInterface $kernel, LoggerInterface $logger, Request $request): Response
    {
        // (4) Schutz via ENV Variable (robuster Zugriff aus verschiedenen Quellen)
        $allowRaw = $_SERVER['ALLOW_WEB_CACHE_CLEAR']
            ?? $_ENV['ALLOW_WEB_CACHE_CLEAR']
            ?? getenv('ALLOW_WEB_CACHE_CLEAR');
        $allowNorm = is_string($allowRaw) ? strtolower(trim($allowRaw)) : '';
        $isAllowed = in_array($allowNorm, ['1', 'true', 'yes', 'on'], true);
        if (!$isAllowed) {
            return $this->json([
                'error' => 'Web Cache Clear ist deaktiviert. Setze ALLOW_WEB_CACHE_CLEAR=1 um es zu erlauben.',
                'current_value' => false === $allowRaw || null === $allowRaw ? '(nicht gesetzt)' : (string) $allowRaw,
                'checked_sources' => ['_SERVER', '_ENV', 'getenv()'],
            ], 403);
        }

        $projectDir = $kernel->getProjectDir();
        $phpBinary = PHP_BINARY; // Annahme: gleiches PHP für Subprozess ok
        $env = $kernel->getEnvironment();

        $doWarmup = $request->query->getBoolean('warmup', false);
        $forceNoWarmup = $request->query->getBoolean('no-warmup', false);

        $results = [];
        $success = true;

        try {
            // 1. cache:clear
            $cmd = [$phpBinary, 'bin/console', 'cache:clear', '--env='.$env];
            if ($forceNoWarmup || !$doWarmup) {
                $cmd[] = '--no-warmup';
            }
            $results[] = '▶ Ausführen: '.implode(' ', $cmd);
            $process = new Process($cmd, $projectDir, [
                // minimale ENV-Vererbung; entferne evtl. störende Variablen bei Bedarf hier
            ]);
            $process->setTimeout(120);
            $process->run();
            if (!$process->isSuccessful()) {
                $success = false;
                $results[] = '❌ cache:clear Fehler: '.$process->getErrorOutput();
            } else {
                $results[] = '✅ cache:clear OK';
                $results[] = trim($process->getOutput());
            }

            // 2. Optionales Warmup
            if ($success && $doWarmup && !$forceNoWarmup) {
                $warmCmd = [$phpBinary, 'bin/console', 'cache:warmup', '--env='.$env];
                $results[] = '▶ Ausführen: '.implode(' ', $warmCmd);
                $warm = new Process($warmCmd, $projectDir);
                $warm->setTimeout(180);
                $warm->run();
                if (!$warm->isSuccessful()) {
                    $success = false;
                    $results[] = '❌ cache:warmup Fehler: '.$warm->getErrorOutput();
                } else {
                    $results[] = '✅ cache:warmup OK';
                }
            }

            if ($success) {
                $results[] = '🎉 Cache Vorgang abgeschlossen';
            }
        } catch (\Throwable $e) {
            $fmLogger = $logger;
            if ($this->container->has('monolog.logger.filemaker')) {
                $fmLogger = $this->container->get('monolog.logger.filemaker');
            }
            $fmLogger->error('CacheController sub-process exception: '.$e->getMessage(), ['exception' => $e]);
            $results[] = '❌ Ausnahme: '.$e->getMessage();
            $success = false;
        }

        // (1) Redirect-Pattern: Ergebnisse in Session Flash + Redirect auf Info
        // Ergebnisse via Query (Fallback ohne direkten FlashBag-Zugriff)
        $encoded = urlencode(base64_encode(json_encode([
            'r' => $results,
            's' => $success ? 1 : 0,
        ], JSON_UNESCAPED_UNICODE)));

        return new RedirectResponse($this->generateUrl('debug_cache_info', ['cc' => $encoded]));
    }

    #[Route('/cache-info', name: 'cache_info')]
    public function cacheInfo(KernelInterface $kernel, Request $request): Response
    {
        $filesystem = new Filesystem();
        $cacheDir = $kernel->getCacheDir();
        $varCacheDir = $kernel->getProjectDir().'/var/cache';

        $info = [];

        // Cache-Verzeichnis Info
        if ($filesystem->exists($cacheDir)) {
            $size = $this->getDirectorySize($cacheDir);
            $info['current_cache'] = [
                'path' => $cacheDir,
                'exists' => true,
                'size' => $this->formatBytes($size),
                'writable' => is_writable($cacheDir),
            ];
        } else {
            $info['current_cache'] = [
                'path' => $cacheDir,
                'exists' => false,
                'size' => '0 B',
                'writable' => false,
            ];
        }

        // Var Cache Info
        if ($filesystem->exists($varCacheDir)) {
            $size = $this->getDirectorySize($varCacheDir);
            $info['var_cache'] = [
                'path' => $varCacheDir,
                'exists' => true,
                'size' => $this->formatBytes($size),
                'writable' => is_writable($varCacheDir),
            ];
        } else {
            $info['var_cache'] = [
                'path' => $varCacheDir,
                'exists' => false,
                'size' => '0 B',
                'writable' => false,
            ];
        }

        $info['environment'] = $kernel->getEnvironment();
        $info['debug'] = $kernel->isDebug();

        // (1) Ergebnisse aus Query-Parameter 'cc' (base64(JSON)) lesen
        $flashResults = null;
        $flashSuccess = null;
        if ($cc = $request->query->get('cc')) {
            $decodedRaw = base64_decode($cc, true);
            if (false !== $decodedRaw) {
                $decoded = json_decode($decodedRaw, true);
                if (is_array($decoded)) {
                    $flashResults = $decoded['r'] ?? null;
                    $flashSuccess = (($decoded['s'] ?? 0) === 1);
                }
            }
        }

        return $this->render('debug/cache/info.html.twig', [
            'info' => $info,
            'cache_clear_results' => $flashResults,
            'cache_clear_success' => $flashSuccess,
        ]);
    }

    private function getDirectorySize(string $directory): int
    {
        $size = 0;
        if (is_dir($directory)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        }

        return $size;
    }

    private function formatBytes(int $size): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $size > 0 ? floor(log($size, 1024)) : 0;

        return number_format($size / pow(1024, $power), 2).' '.$units[$power];
    }
}
