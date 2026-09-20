<?php

namespace FlatRate\WikiContext\Tests\Unit;

use FlatRate\WikiContext\Projection\SettingsReader;
use FlatRate\WikiContext\Support\BrowseAccessGate;
use FlatRate\WikiContext\Support\FeatureGates;
use Flarum\Settings\SettingsRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class BrowseAccessGateTest extends TestCase
{
    /**
     * @dataProvider gateMatrix
     */
    public function test_public_browse_requires_both_gates(
        bool $browse,
        bool $public,
        bool $preview,
        bool $expected
    ): void {
        $settings = $this->createMock(SettingsRepositoryInterface::class);
        $values = [
            FeatureGates::BROWSE_ROUTES_ENABLED => $browse ? '1' : '0',
            FeatureGates::PUBLIC_ROLLOUT_ENABLED => $public ? '1' : '0',
            FeatureGates::ADMIN_GHOST_PREVIEW_ENABLED => $preview ? '1' : '0',
        ];

        $settings->method('get')->willReturnCallback(
            fn (string $key, $default = null) => $values[$key] ?? $default
        );

        $gate = new BrowseAccessGate(new SettingsReader($settings));

        $this->assertSame($expected, $gate->publicBrowseEnabled());
    }

    public function gateMatrix(): array
    {
        return [
            'all closed' => [false, false, false, false],
            'browse only' => [true, false, false, false],
            'public only' => [false, true, false, false],
            'preview only' => [false, false, true, false],
            'browse plus preview still closed' => [true, false, true, false],
            'public plus preview still closed' => [false, true, true, false],
            'browse plus public opens' => [true, true, false, true],
            'all open remains public' => [true, true, true, true],
        ];
    }
}
