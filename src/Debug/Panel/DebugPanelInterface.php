<?php

namespace App\Debug\Panel;

interface DebugPanelInterface
{
    public function getKey(): string;

    public function getLabel(): string;

    public function isAvailable(): bool;

    public function collect(): array;
}
