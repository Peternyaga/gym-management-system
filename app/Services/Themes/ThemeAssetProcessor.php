<?php

namespace App\Services\Themes;

use App\Models\Tenant;
use App\Models\TenantTheme;
use App\Models\ThemeAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ThemeAssetProcessor
{
    private const TYPES = [
        'lightLogo' => ['maxKb' => 2048, 'widths' => [320, 640]],
        'darkLogo' => ['maxKb' => 2048, 'widths' => [320, 640]],
        'appIcon' => ['maxKb' => 1024, 'widths' => [192, 512]],
        'favicon' => ['maxKb' => 1024, 'widths' => [64, 192]],
        'heroImage' => ['maxKb' => 5120, 'widths' => [640, 1280, 1920]],
        'kioskBackground' => ['maxKb' => 5120, 'widths' => [640, 1280, 1920]],
    ];

    public function store(
        Tenant $tenant,
        TenantTheme $theme,
        string $type,
        UploadedFile $file,
        ?string $altText,
        float $focalX,
        float $focalY,
    ): ThemeAsset {
        abort_unless($theme->tenant_id === $tenant->id && $theme->status === 'draft', 409);
        if (! isset(self::TYPES[$type])) {
            throw ValidationException::withMessages(['type' => 'Unsupported theme asset type.']);
        }
        if ($file->getSize() > self::TYPES[$type]['maxKb'] * 1024) {
            throw ValidationException::withMessages(['file' => "The {$type} file is too large."]);
        }

        $contents = $file->get();
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);
        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            throw ValidationException::withMessages(['file' => 'Only real PNG, JPEG, and WebP images are accepted. SVG and HTML formats are blocked.']);
        }
        $details = @getimagesizefromstring($contents);
        if (! $details || $details[0] < 1 || $details[1] < 1 || $details[0] * $details[1] > 20_000_000) {
            throw ValidationException::withMessages(['file' => 'The image is invalid or exceeds the safe 20-megapixel processing limit.']);
        }
        $source = @imagecreatefromstring($contents);
        if (! $source) {
            throw ValidationException::withMessages(['file' => 'The image could not be safely decoded.']);
        }

        $assetId = (string) Str::ulid();
        $directory = "themes/{$tenant->id}/{$assetId}";
        $primaryPath = "{$directory}/{$type}-{$details[0]}w.webp";
        $this->encode($source, $primaryPath, $details[0], $details[1]);
        foreach (self::TYPES[$type]['widths'] as $width) {
            if ($width < $details[0]) {
                $height = max(1, (int) round($details[1] * ($width / $details[0])));
                $this->encode($source, "{$directory}/{$type}-{$width}w.webp", $width, $height);
            }
        }
        imagedestroy($source);

        $theme->assets()->where('type', $type)->whereNull('deleted_at')->delete();
        $asset = ThemeAsset::query()->create([
            'id' => $assetId,
            'tenant_id' => $tenant->id,
            'theme_id' => $theme->id,
            'type' => $type,
            'path' => $primaryPath,
            'mime' => 'image/webp',
            'width' => $details[0],
            'height' => $details[1],
            'checksum' => hash('sha256', Storage::disk('public')->get($primaryPath)),
            'alt_text' => trim(strip_tags((string) $altText)) ?: null,
            'focal_x' => min(100, max(0, $focalX)),
            'focal_y' => min(100, max(0, $focalY)),
        ]);
        $configuration = $theme->configuration;
        $configuration['assets'][$type] = Storage::disk('public')->url($primaryPath);
        $theme->update(['configuration' => ThemeSchema::normalize($configuration), 'validation_summary' => null]);

        return $asset;
    }

    public function remove(Tenant $tenant, TenantTheme $theme, ThemeAsset $asset): void
    {
        abort_unless($theme->status === 'draft' && $asset->tenant_id === $tenant->id && $asset->theme_id === $theme->id, 404);
        $configuration = $theme->configuration;
        $configuration['assets'][$asset->type] = null;
        $theme->update(['configuration' => ThemeSchema::normalize($configuration), 'validation_summary' => null]);
        $asset->delete();
    }

    private function encode(\GdImage $source, string $path, int $width, int $height): void
    {
        $target = imagecreatetruecolor($width, $height);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
        ob_start();
        imagewebp($target, null, 84);
        Storage::disk('public')->put($path, (string) ob_get_clean());
        imagedestroy($target);
    }
}
