<?php

namespace App\Services;

use Illuminate\Support\Str;

class JobPhotoStyle
{
    protected array $spec;

    public static function forJob(?string $jobType): self
    {
        $norm = self::normalizeKey($jobType);
        $styles = config('photo_pipeline.job_styles', []);

        if (isset($styles[$norm])) {
            return new self($styles[$norm]);
        }

        // try fallback to older config if not present
        $roleColors = config('photo_bg.role_bg_colors', []);
        if (isset($roleColors[$norm])) {
            return new self(['bg' => $roleColors[$norm], 'style' => 'solid']);
        }

        $def = $styles['__DEFAULT__'] ?? ['bg' => '#0F172A', 'style' => 'solid'];
        return new self($def);
    }

    public function __construct(array $spec)
    {
        $this->spec = $spec;
    }

    public function bgColor(): string
    {
        return $this->spec['bg'] ?? ($this->spec['bg_hex'] ?? '#0F172A');
    }

    public function style(): string
    {
        return $this->spec['style'] ?? 'solid';
    }

    public function toArray(): array
    {
        return $this->spec + ['style' => $this->style(), 'bg' => $this->bgColor()];
    }

    protected static function normalizeKey(?string $k): string
    {
        $k = trim((string) $k);
        $k = preg_replace('/\s+/', ' ', $k);
        return mb_strtoupper($k, 'UTF-8');
    }
}
