<?php
/**
 * Avatar SVG inline para médicos sin foto. Genera un círculo con
 * gradient verde hospitalario + iniciales en blanco. Cero dependencias
 * de assets físicos.
 */

function doctor_initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $skip = ['dr', 'dra', 'dr.', 'dra.', 'doctor', 'doctora'];
    $initials = '';
    foreach ($parts as $p) {
        $clean = trim(mb_strtolower($p, 'UTF-8'), '.,');
        if ($clean === '' || in_array($clean, $skip, true)) continue;
        $initials .= mb_strtoupper(mb_substr($p, 0, 1, 'UTF-8'), 'UTF-8');
        if (strlen($initials) >= 2) break;
    }
    return $initials !== '' ? $initials : '?';
}

function doctor_avatar_svg(string $name, int $size = 200): string {
    $initials = doctor_initials($name);
    // Color base derivado del nombre para variedad sutil pero verde hospitalario
    $hash = crc32($name);
    $hueShift = $hash % 20 - 10; // -10..+10 grados
    $c1 = '#047857';
    $c2 = '#10b981';
    $fontSize = round($size * 0.42);
    $textY = round($size * 0.62);
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $size . ' ' . $size . '" width="' . $size . '" height="' . $size . '">'
         . '<defs><linearGradient id="hgrad" x1="0%" y1="0%" x2="100%" y2="100%">'
         . '<stop offset="0%" stop-color="' . $c1 . '"/>'
         . '<stop offset="100%" stop-color="' . $c2 . '"/>'
         . '</linearGradient></defs>'
         . '<rect width="' . $size . '" height="' . $size . '" fill="url(#hgrad)"/>'
         . '<g opacity="0.12" transform="translate(' . round($size * 0.65) . ' ' . round($size * 0.65) . ')">'
         . '<path d="M 0 0 L 35 0 L 35 12 L 47 12 L 47 23 L 35 23 L 35 35 L 23 35 L 23 23 L 12 23 L 12 12 L 0 12 Z" fill="white"/>'
         . '</g>'
         . '<text x="50%" y="' . $textY . '" text-anchor="middle" '
         . 'font-family="Inter, -apple-system, BlinkMacSystemFont, sans-serif" '
         . 'font-size="' . $fontSize . '" font-weight="800" fill="white">' . htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') . '</text>'
         . '</svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

/**
 * Devuelve URL de foto real si existe, sino el avatar SVG con iniciales.
 */
function doctor_photo_or_avatar(?string $photoUrl, string $name): string {
    return $photoUrl ?: doctor_avatar_svg($name);
}
