<?php

namespace App\Debug;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;

class DebugFlags
{
    private array $config;
    private array $enabledPanels = [];
    private bool $allowProd = false;

    public function __construct(ParameterBagInterface $params)
    {
        $this->config = $params->has('app_debug') ? (array) $params->get('app_debug') : [];
        $panels = $this->config['panels'] ?? [];
        foreach ($panels as $k => $v) {
            if ($v) {
                $this->enabledPanels[] = $k;
            }
        }
        if (isset($this->config['default_panels'])) {
            foreach ($this->config['default_panels'] as $d) {
                if (!in_array($d, $this->enabledPanels, true)) {
                    $this->enabledPanels[] = $d;
                }
            }
        }
        $this->allowProd = (bool) ($this->config['allow_prod'] ?? false);
        $envPanels = getenv('DEBUG_PANELS');
        if ($envPanels) {
            $list = array_filter(array_map('trim', explode(',', $envPanels)));
            if ($list) {
                $this->enabledPanels = array_values(array_unique($list));
            }
        }
    }

    public function isProdAllowed(): bool
    {
        $override = getenv('DEBUG_ALLOW_PROD');
        if (false !== $override) {
            return in_array(strtolower((string) $override), ['1', 'true', 'yes', 'on'], true);
        }

        return $this->allowProd;
    }

    public function resolveForRequest(Request $request): void
    {
        $enabled = $this->enabledPanels;
        $only = $this->csv($request->query->get('only'));
        if ($only) {
            $enabled = $only;
        } else {
            $show = $this->csv($request->query->get('show'));
            if ($show) {
                foreach ($show as $p) {
                    if (!in_array($p, $enabled, true)) {
                        $enabled[] = $p;
                    }
                }
            }
            $hide = $this->csv($request->query->get('hide'));
            if ($hide) {
                $enabled = array_values(array_filter($enabled, fn ($p) => !in_array($p, $hide, true)));
            }
        }
        $this->enabledPanels = $enabled;
    }

    public function getEnabledPanels(): array
    {
        return $this->enabledPanels;
    }

    public function isEnabled(string $key): bool
    {
        return in_array($key, $this->enabledPanels, true);
    }

    private function csv(?string $value): array
    {
        if (!$value) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }
}
