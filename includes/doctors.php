<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/portal_directory.php';

function fallback_medical_profiles(array $services, array $assets): array
{
    $profileImages = [
        $assets['doctors'],
        $assets['doctor_secondary'],
        $assets['ct'],
        $assets['exam'],
        $assets['room'],
    ];
    $locations = [
        'Centro de Consulta Externa, Colinas Mall',
        'Hospital principal, nivel clínico',
        'Área de diagnóstico y apoyo clínico',
    ];
    $profiles = [];

    foreach ($services['consultas']['items'] as $index => $specialty) {
        $name = 'Equipo de ' . $specialty;
        $profiles[] = [
            'id' => null,
            'name' => $name,
            'slug' => slugify($name),
            'title' => 'Equipo clínico',
            'specialty' => $specialty,
            'specialty_slug' => slugify($specialty),
            'exequatur' => '',
            'photo' => $profileImages[$index % count($profileImages)],
            'biography' => 'Atención coordinada por el equipo de ' . $specialty . ' del Hospital General Las Colinas.',
            'education' => '',
            'languages' => 'Español',
            'services' => $specialty,
            'insurances' => 'Principales aseguradoras nacionales según cobertura del paciente.',
            'associations' => '',
            'schedule' => ($index % 3 === 0) ? 'Citas presenciales' : (($index % 3 === 1) ? 'Por coordinación' : 'Agenda programada'),
            'office' => $locations[$index % count($locations)],
            'phone' => '',
            'email' => '',
            'status' => 'active',
            'is_featured' => false,
        ];
    }

    return $profiles;
}

/**
 * Mapea el shape de /portal/directory al shape esperado por la landing.
 */
function map_api_doctor_to_landing(array $r, array $assets): array {
    $defaultPhotos = [$assets['doctors'], $assets['doctor_secondary'], $assets['ct'], $assets['exam']];
    $photo = portal_directory_photo_url($r['photo_url'] ?? null);
    if (!$photo) {
        // Foto por defecto rotando por id
        $photo = $defaultPhotos[abs((int)($r['id'] ?? 0)) % count($defaultPhotos)];
    }
    return [
        'id'             => (int)($r['id'] ?? 0),
        'name'           => $r['name'] ?? 'Médico',
        'slug'           => $r['slug'] ?: ('doctor-' . (int)$r['id']),
        'title'          => '',
        'specialty'      => $r['specialty'] ?? 'Especialidad médica',
        'specialty_slug' => slugify($r['specialty'] ?? 'especialidad'),
        'exequatur'      => $r['exequatur'] ?? '',
        'photo'          => $photo,
        'biography'      => $r['biography'] ?? ($r['bio'] ?? 'Perfil médico del Hospital General Las Colinas.'),
        'education'      => $r['education'] ?? '',
        'languages'      => $r['languages'] ?: 'Español',
        'services'       => $r['services'] ?? '',
        'insurances'     => $r['insurances'] ?? '',
        'associations'   => $r['associations'] ?? '',
        'schedule'       => $r['schedule']['label'] ?? 'Por coordinación',
        'office'         => $r['office'] ?? ($r['office_name'] ?? 'Centro de Consulta Externa'),
        'phone'          => $r['phone'] ?? '',
        'email'          => $r['email'] ?? '',
        'status'         => 'active',
        'is_featured'    => !empty($r['is_featured']),
    ];
}

function public_doctors(array $services, array $assets): array
{
    // 1) Live fetch desde la API interna (con cache 1h)
    $api = portal_directory_doctors();
    if ($api['ok'] && !empty($api['data'])) {
        return array_map(static fn(array $r) => map_api_doctor_to_landing($r, $assets), $api['data']);
    }

    // 2) Fallback: DB local de la landing (legacy)
    if (db_ready()) {
        $stmt = db()->query("
            SELECT d.*, s.name AS specialty, s.slug AS specialty_slug
            FROM doctors d
            LEFT JOIN specialties s ON s.id = d.specialty_id
            WHERE d.status = 'active'
            ORDER BY d.is_featured DESC, d.sort_order ASC, d.last_name ASC, d.first_name ASC
        ");
        $rows = $stmt->fetchAll();
        if ($rows) {
            return array_map(static function (array $row) use ($assets): array {
                $name = trim(($row['title'] ? $row['title'] . ' ' : '') . $row['first_name'] . ' ' . $row['last_name']);
                return [
                    'id' => (int) $row['id'],
                    'name' => $name,
                    'slug' => $row['slug'],
                    'title' => $row['title'],
                    'specialty' => $row['specialty'] ?: 'Especialidad médica',
                    'specialty_slug' => $row['specialty_slug'] ?: 'especialidad',
                    'exequatur' => $row['exequatur'] ?: '',
                    'photo' => $row['photo_path'] ?: $assets['doctors'],
                    'biography' => $row['biography'] ?: 'Perfil médico del Hospital General Las Colinas.',
                    'education' => $row['education'] ?: '',
                    'languages' => $row['languages'] ?: 'Español',
                    'services' => $row['services'] ?: '',
                    'insurances' => $row['insurances'] ?? '',
                    'associations' => $row['associations'] ?? '',
                    'schedule' => $row['schedule'] ?: 'Por coordinación',
                    'office' => $row['office'] ?: 'Centro de Consulta Externa',
                    'phone' => $row['phone'] ?: '',
                    'email' => $row['email'] ?: '',
                    'status' => $row['status'],
                    'is_featured' => (bool) $row['is_featured'],
                ];
            }, $rows);
        }
    }

    // 3) Último recurso: fallback hardcoded
    return fallback_medical_profiles($services, $assets);
}

function public_doctor_by_slug(string $slug, array $services, array $assets): ?array
{
    foreach (public_doctors($services, $assets) as $doctor) {
        if ($doctor['slug'] === $slug) {
            return $doctor;
        }
    }

    return null;
}

function public_specialties(array $services): array
{
    // 1) Live fetch
    $api = portal_directory_specialties();
    if ($api['ok'] && !empty($api['data'])) {
        return array_map(static fn(array $r): array => [
            'id'   => (int)$r['id'],
            'name' => $r['name'],
            'slug' => slugify($r['name']),
        ], $api['data']);
    }

    // 2) Fallback DB local
    if (db_ready()) {
        $stmt = db()->query("SELECT id, name, slug FROM specialties WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
        $rows = $stmt->fetchAll();
        if ($rows) return $rows;
    }

    // 3) Hardcoded
    return array_map(static fn (string $name): array => [
        'id' => null,
        'name' => $name,
        'slug' => slugify($name),
    ], $services['consultas']['items']);
}
