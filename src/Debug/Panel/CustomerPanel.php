<?php

namespace App\Debug\Panel;

class CustomerPanel extends AbstractDebugPanel
{
    public function getKey(): string
    {
        return 'customer';
    }

    public function getLabel(): string
    {
        return 'Customer';
    }

    public function collect(): array
    {
        return [
            'status' => 'placeholder',
            'message' => 'Customer integration not yet implemented.',
        ];
    }
}
