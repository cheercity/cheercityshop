<?php

namespace App\Service;

/**
 * Zentraler Zugriff auf FileMaker Layout-Namen.
 * Bietet Methoden zum Holen einzelner Layouts sowie Fallbacks.
 */
final class FileMakerLayoutRegistry
{
    /** @var array<string,string> */
    private array $layouts;

    /**
     * @param array<string,string> $layouts
     */
    public function __construct(array $layouts = [])
    {
        $this->layouts = $layouts;
    }

    /**
     * Liefert den Layout-Namen für den gegebenen Schlüssel (z.B. 'artikel').
     * Optionaler Fallback (Default null => Exception wenn nicht vorhanden).
     */
    public function get(string $key, ?string $default = null): string
    {
        if (!isset($this->layouts[$key])) {
            if (null !== $default) {
                return $default;
            }
            throw new \InvalidArgumentException("Unbekannter Layout-Key '{$key}'");
        }

        return $this->layouts[$key];
    }

    /**
     * Gibt alle Layouts als Array zurück.
     *
     * @return array<string,string>
     */
    public function all(): array
    {
        return $this->layouts;
    }
}
