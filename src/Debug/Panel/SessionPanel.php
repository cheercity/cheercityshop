<?php

namespace App\Debug\Panel;

use Symfony\Component\HttpFoundation\RequestStack;

class SessionPanel extends AbstractDebugPanel
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function getKey(): string
    {
        return 'session';
    }

    public function getLabel(): string
    {
        return 'Session';
    }

    public function collect(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return ['status' => 'no_request'];
        }
        $session = $request->getSession();
        if (!$session || !$session->isStarted()) {
            return ['status' => 'no_session'];
        }
        $all = [];
        foreach ($session->all() as $k => $v) {
            $all[$k] = $this->stringify($v);
        }

        return [
            'status' => 'ok',
            'count' => count($all),
            'keys' => array_keys($all),
            'values' => $all,
            'id' => method_exists($session, 'getId') ? $session->getId() : null,
        ];
    }

    private function stringify(mixed $value): mixed
    {
        if (is_scalar($value) || null === $value) {
            return $value;
        }
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value)) {
            if ($value instanceof \Stringable) {
                return (string) $value;
            }

            return ['__class' => get_class($value)];
        }

        return (string) json_encode($value);
    }
}
