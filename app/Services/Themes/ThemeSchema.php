<?php

namespace App\Services\Themes;

final class ThemeSchema
{
    public const DEFAULT = [
        'schemaVersion' => 1,
        'name' => 'Clean athletic',
        'defaultMode' => 'light',
        'colors' => [
            'light' => [
                'brand' => '#151A16',
                'accent' => '#B5EE45',
                'background' => '#F6F8F4',
                'surface' => '#FFFFFF',
                'text' => '#151A16',
                'muted' => '#657067',
                'border' => '#DCE2DA',
            ],
            'dark' => [
                'brand' => '#B5EE45',
                'accent' => '#B5EE45',
                'background' => '#101411',
                'surface' => '#19201B',
                'text' => '#F5F8F3',
                'muted' => '#A8B3AA',
                'border' => '#344038',
            ],
        ],
        'typographyPreset' => 'athletic',
        'radiusPreset' => 'balanced',
        'density' => 'comfortable',
        'navigationStyle' => 'dark',
        'publicLayout' => 'boldHero',
        'memberLayout' => 'statusFirst',
        'assets' => [
            'lightLogo' => null,
            'darkLogo' => null,
            'appIcon' => null,
            'favicon' => null,
            'heroImage' => null,
            'kioskBackground' => null,
        ],
        'publicContent' => [
            'tagline' => 'Train stronger. Live better.',
            'heroTitle' => 'Your strongest season starts here.',
            'heroText' => 'Flexible memberships, expert coaches, and a community that keeps you moving.',
            'callToActionLabel' => 'Join the gym',
        ],
        'memberContent' => [
            'welcomeTitle' => 'Welcome back',
            'welcomeText' => 'Your membership, bookings, payments, and access code are all here.',
            'membershipLabel' => 'Membership',
            'balanceLabel' => 'Outstanding balance',
            'scheduleTitle' => 'Upcoming classes',
            'supportText' => 'Need help? Speak to the front desk.',
        ],
    ];

    public const MODES = ['light', 'dark', 'system'];

    public const TYPOGRAPHY = ['athletic', 'strong', 'modern'];

    public const RADII = ['sharp', 'balanced', 'rounded'];

    public const DENSITIES = ['comfortable', 'compact'];

    public const NAVIGATION = ['light', 'dark', 'brand'];

    public const LAYOUTS = ['boldHero', 'splitHero', 'scheduleFirst'];

    public const MEMBER_LAYOUTS = ['statusFirst', 'community', 'compact'];

    public const COLOR_KEYS = ['brand', 'accent', 'background', 'surface', 'text', 'muted', 'border'];

    public static function normalize(array $input): array
    {
        $theme = array_replace_recursive(self::DEFAULT, $input);
        $theme['schemaVersion'] = 1;
        $theme['name'] = self::plainText($theme['name'], 80);
        $theme['defaultMode'] = self::allowed($theme['defaultMode'], self::MODES, 'light');
        $theme['typographyPreset'] = self::allowed($theme['typographyPreset'], self::TYPOGRAPHY, 'athletic');
        $theme['radiusPreset'] = self::allowed($theme['radiusPreset'], self::RADII, 'balanced');
        $theme['density'] = self::allowed($theme['density'], self::DENSITIES, 'comfortable');
        $theme['navigationStyle'] = self::allowed($theme['navigationStyle'], self::NAVIGATION, 'dark');
        $theme['publicLayout'] = self::allowed($theme['publicLayout'], self::LAYOUTS, 'boldHero');
        $theme['memberLayout'] = self::allowed($theme['memberLayout'], self::MEMBER_LAYOUTS, 'statusFirst');

        foreach (['light', 'dark'] as $mode) {
            foreach (self::COLOR_KEYS as $key) {
                $value = strtoupper((string) $theme['colors'][$mode][$key]);
                $theme['colors'][$mode][$key] = preg_match('/^#[0-9A-F]{6}$/', $value)
                    ? $value
                    : self::DEFAULT['colors'][$mode][$key];
            }
        }

        foreach (['tagline', 'heroTitle', 'heroText', 'callToActionLabel'] as $key) {
            $limit = $key === 'heroText' ? 240 : 100;
            $theme['publicContent'][$key] = self::plainText($theme['publicContent'][$key], $limit);
        }
        foreach (['welcomeTitle', 'welcomeText', 'membershipLabel', 'balanceLabel', 'scheduleTitle', 'supportText'] as $key) {
            $limit = in_array($key, ['welcomeText', 'supportText'], true) ? 240 : 100;
            $theme['memberContent'][$key] = self::plainText($theme['memberContent'][$key], $limit);
        }

        return $theme;
    }

    private static function plainText(mixed $value, int $limit): string
    {
        return mb_substr(trim(strip_tags((string) $value)), 0, $limit);
    }

    private static function allowed(mixed $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }
}
