<?php

namespace App\Debug\Panel;

abstract class AbstractDebugPanel implements DebugPanelInterface
{
    public function isAvailable(): bool
    {
        return true;
    }
}
