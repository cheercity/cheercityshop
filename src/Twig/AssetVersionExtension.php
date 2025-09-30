<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class AssetVersionExtension extends AbstractExtension implements GlobalsInterface
{
    private string $version;

    public function __construct()
    {
        // Read an environment-provided asset version if set, otherwise use current timestamp
        $env = getenv('ASSET_VERSION');
        if (false !== $env && '' !== $env) {
            $this->version = (string) $env;
        } else {
            $this->version = (string) time();
        }
    }

    public function getGlobals(): array
    {
        return [
            'asset_version' => $this->version,
        ];
    }
}
