<?php
/**
 * Helper para generar avatars de iniciales con gradientes deterministas
 * basados en el hash del nombre. Mismo nombre → mismo color siempre.
 */

if (!function_exists('doctor_initials')) {
    function doctor_initials(?string $name, int $max = 2): string {
        $name = trim((string)$name);
        if ($name === '') return '?';
        $parts = preg_split('/\s+/', $name) ?: [];
        $out = '';
        foreach ($parts as $p) {
            if ($p === '') continue;
            if (mb_strlen($out, 'UTF-8') >= $max) break;
            $out .= mb_substr($p, 0, 1, 'UTF-8');
        }
        return mb_strtoupper($out ?: '?', 'UTF-8');
    }
}

if (!function_exists('doctor_avatar_palette')) {
    /**
     * Devuelve un par de hex colors deterministas para gradiente.
     */
    function doctor_avatar_palette(?string $key): array {
        $palettes = [
            ['#0d9488', '#0284c7'], // teal → sky
            ['#7c3aed', '#db2777'], // violet → pink
            ['#0891b2', '#1e40af'], // cyan → indigo
            ['#059669', '#0d9488'], // emerald → teal
            ['#dc2626', '#ea580c'], // red → orange
            ['#1d4ed8', '#6d28d9'], // blue → violet
            ['#b45309', '#dc2626'], // amber → red
            ['#0f766e', '#0e7490'], // dark teal → dark cyan
            ['#6d28d9', '#1e40af'], // violet → indigo
            ['#be185d', '#7c2d12'], // pink → brown
        ];
        $key = (string)($key ?? '');
        if ($key === '') return $palettes[0];
        $hash = crc32($key);
        return $palettes[$hash % count($palettes)];
    }
}

if (!function_exists('doctor_avatar_html')) {
    /**
     * Renderiza un <span class="doctor-av"> con iniciales y gradient inline.
     * $size: 'sm' (28px) | 'md' (40px) | 'lg' (56px) | 'xl' (80px)
     */
    function doctor_avatar_html(?string $name, string $size = 'md'): string {
        $initials = doctor_initials($name);
        [$c1, $c2] = doctor_avatar_palette($name);
        $style = 'background: linear-gradient(135deg, ' . $c1 . ', ' . $c2 . ');';
        return '<span class="doctor-av doctor-av-' . htmlspecialchars($size, ENT_QUOTES, 'UTF-8') . '"'
             . ' style="' . $style . '"'
             . ' aria-hidden="true">' . htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}
