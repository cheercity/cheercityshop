<?php

namespace App\Twig;

use App\Service\WertelistenService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class WertelistenExtension extends AbstractExtension
{
    // simple in-process memoization to avoid repeated lookups during template rendering
    private array $localMapCache = [];

    public function __construct(private WertelistenService $wertelisten, private ParameterBagInterface $params)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('wl_label', [$this, 'getLabel']),
            new TwigFunction('wl_color_image', [$this, 'getColorImage']),
        ];
    }

    public function getLabel(string $listName, string $code): string
    {
        if (!array_key_exists($listName, $this->localMapCache)) {
            $this->localMapCache[$listName] = $this->wertelisten->getMap($listName);
        }
        $map = $this->localMapCache[$listName] ?? [];
        return $map[$code]['label'] ?? '';
    }

    public function getColorImage(string $listName, string $code): ?string
    {
        if (!array_key_exists($listName, $this->localMapCache)) {
            $this->localMapCache[$listName] = $this->wertelisten->getMap($listName);
        }
        $map = $this->localMapCache[$listName] ?? [];
        $img = $map[$code]['image'] ?? '';
        if ('' === (string) $img) {
            return null;
        }
        $base = rtrim($this->params->get('colors_images_path') ?? 'public/assets/images/farben', '/');
        // return path relative to public/ for use in templates
        return $base.'/'.$img;
    }
}
