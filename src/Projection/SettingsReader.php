<?php

namespace FlatRate\WikiContext\Projection;

use Flarum\Settings\SettingsRepositoryInterface;

final class SettingsReader
{
    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $value = $this->settings->get($key, $default);

        return $value === null ? $default : (string) $value;
    }

    public function bool(string $key): bool
    {
        $value = $this->get($key, '0');

        return $value === '1' || $value === 'true' || $value === 'on';
    }
}
