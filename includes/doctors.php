<?php

require_once __DIR__ . '/db.php';

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

function public_doctors(array $services, array $assets): array
{
    if (!db_ready()) {
        return fallback_medical_profiles($services, $assets);
    }

    $stmt = db()->query("
        SELECT d.*, s.name AS specialty, s.slug AS specialty_slug
        FROM doctors d
        LEFT JOIN specialties s ON s.id = d.specialty_id
        WHERE d.status = 'active'
        ORDER BY d.is_featured DESC, d.sort_order ASC, d.last_name ASC, d.first_name ASC
    ");
    $rows = $stmt->fetchAll();

    if (!$rows) {
        return fallback_medical_profiles($services, $assets);
    }

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
    if (!db_ready()) {
        return array_map(static fn (string $name): array => [
            'id' => null,
            'name' => $name,
            'slug' => slugify($name),
        ], $services['consultas']['items']);
    }

    $stmt = db()->query("SELECT id, name, slug FROM specialties WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");

    return $stmt->fetchAll();
}
