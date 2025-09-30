<?php

namespace App\Debug\Panel;

class CartPanel extends AbstractDebugPanel
{
    public function getKey(): string
    {
        return 'cart';
    }

    public function getLabel(): string
    {
        return 'Cart';
    }

    public function collect(): array
    {
        return [
            'status' => 'placeholder',
            'message' => 'Cart data not yet implemented.',
        ];
    }
}
