<?php

namespace App\Twig;

use App\Service\FileMakerLayoutRegistry;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Stellt Twig-Funktion fm_layout(key) bereit um den konfigurierten Layout-Namen auszugeben.
 */
final class FileMakerLayoutExtension extends AbstractExtension
{
    public function __construct(private FileMakerLayoutRegistry $registry)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('fm_layout', function (string $key, ?string $default = null): string {
                return $this->registry->get($key, $default);
            }),
        ];
    }
}
