<?php

namespace App\Services\Themes;

final class ThemeCssGenerator
{
    public function __construct(private readonly ThemeValidator $validator) {}

    public function generate(array $configuration): string
    {
        $theme = ThemeSchema::normalize($configuration);
        $light = $this->variables($theme, 'light');
        $dark = $this->variables($theme, 'dark');
        $modeRule = match ($theme['defaultMode']) {
            'dark' => ":root:not(.light){{$dark}}",
            'system' => "@media (prefers-color-scheme: dark){:root:not(.light){$dark}}",
            default => '',
        };

        return "/* Generated tenant theme; do not edit. */\n:root{{$light}}\n.dark{{$dark}}\n{$modeRule}\n";
    }

    private function variables(array $theme, string $mode): string
    {
        $colors = $theme['colors'][$mode];
        $primaryForeground = $this->validator->bestForeground($colors['brand']);
        $accentForeground = $this->validator->bestForeground($colors['accent']);
        $radius = match ($theme['radiusPreset']) {
            'sharp' => '0.25rem',
            'rounded' => '1rem',
            default => '0.625rem',
        };
        $font = match ($theme['typographyPreset']) {
            'strong' => '"Manrope", ui-sans-serif, system-ui, sans-serif',
            'modern' => '"DM Sans", ui-sans-serif, system-ui, sans-serif',
            default => '"Inter", ui-sans-serif, system-ui, sans-serif',
        };
        $sidebar = match ($theme['navigationStyle']) {
            'light' => $colors['surface'],
            'brand' => $colors['brand'],
            default => '#151A16',
        };
        $sidebarForeground = $this->validator->bestForeground($sidebar);
        $focus = $this->validator->contrastRatio($colors['accent'], $colors['background']) >= 3
            ? $colors['accent']
            : $colors['text'];

        return implode('', [
            "--background:{$colors['background']};",
            "--foreground:{$colors['text']};",
            "--card:{$colors['surface']};",
            "--card-foreground:{$colors['text']};",
            "--popover:{$colors['surface']};",
            "--popover-foreground:{$colors['text']};",
            "--primary:{$colors['brand']};",
            "--primary-foreground:{$primaryForeground};",
            "--secondary:{$colors['background']};",
            "--secondary-foreground:{$colors['text']};",
            "--muted:{$colors['background']};",
            "--muted-foreground:{$colors['muted']};",
            "--accent:{$colors['accent']};",
            "--accent-foreground:{$accentForeground};",
            "--border:{$colors['border']};",
            "--input:{$colors['border']};",
            "--ring:{$focus};",
            "--sidebar:{$sidebar};",
            "--sidebar-foreground:{$sidebarForeground};",
            "--sidebar-primary:{$colors['accent']};",
            "--sidebar-primary-foreground:{$accentForeground};",
            "--sidebar-accent:color-mix(in srgb, {$sidebar} 85%, {$sidebarForeground});",
            "--sidebar-accent-foreground:{$sidebarForeground};",
            "--sidebar-border:color-mix(in srgb, {$sidebar} 75%, {$sidebarForeground});",
            "--sidebar-ring:{$colors['accent']};",
            "--radius:{$radius};",
            "--font-sans:{$font};",
            '--theme-density:'.($theme['density'] === 'compact' ? '0.875' : '1').';',
        ]);
    }
}
