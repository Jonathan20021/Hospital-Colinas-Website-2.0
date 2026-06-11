<?php
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/doctors.php';
require __DIR__ . '/includes/news.php';
require __DIR__ . '/includes/content.php';

header('Content-Type: application/xml; charset=UTF-8');

news_ensure_schema();
$doctors = public_doctors($services, $assets);
$newsItems = news_query_published(0, 0);
$sitePages = site_pages_catalog($services, $assets, $contact, $patientRights, $patientDuties, $floors);
$servicePages = service_pages_catalog($services, $assets);
$today = date('Y-m-d');
$home = absolute_url();
$directory = absolute_url('directorio-medico');
$newsHome = absolute_url('noticias');

$urls = [
    ['loc' => $home, 'changefreq' => 'weekly', 'priority' => '1.0'],
    ['loc' => $directory, 'changefreq' => 'weekly', 'priority' => '0.9'],
    ['loc' => $newsHome, 'changefreq' => 'daily', 'priority' => '0.85'],
    ['loc' => absolute_url('repositorio'), 'changefreq' => 'weekly', 'priority' => '0.8'],
    ['loc' => $home . '#nosotros', 'changefreq' => 'monthly', 'priority' => '0.7'],
    ['loc' => $home . '#liderazgo', 'changefreq' => 'monthly', 'priority' => '0.6'],
    ['loc' => $home . '#servicios', 'changefreq' => 'monthly', 'priority' => '0.8'],
    ['loc' => $home . '#instalaciones', 'changefreq' => 'monthly', 'priority' => '0.7'],
    ['loc' => $home . '#pacientes', 'changefreq' => 'monthly', 'priority' => '0.6'],
    ['loc' => $home . '#contacto', 'changefreq' => 'monthly', 'priority' => '0.7'],
];

foreach ($sitePages as $slug => $page) {
    $urls[] = [
        'loc' => absolute_url($slug),
        'changefreq' => 'monthly',
        'priority' => ($slug === 'servicios') ? '0.85' : '0.7',
    ];
}

foreach ($servicePages as $service) {
    $urls[] = [
        'loc' => absolute_url('servicios/' . $service['slug']),
        'changefreq' => 'monthly',
        'priority' => '0.72',
    ];
}

foreach ($doctors as $doctor) {
    $urls[] = [
        'loc' => absolute_url('medico/' . $doctor['slug']),
        'changefreq' => 'monthly',
        'priority' => '0.7',
    ];
}

foreach ($newsItems as $news) {
    $urls[] = [
        'loc' => absolute_url('noticias/' . $news['slug']),
        'lastmod' => date('Y-m-d', strtotime($news['updated_at'] ?? $news['published_at'])),
        'changefreq' => 'monthly',
        'priority' => '0.7',
    ];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $url): ?>
    <url>
        <loc><?= htmlspecialchars($url['loc'], ENT_XML1, 'UTF-8') ?></loc>
        <lastmod><?= $url['lastmod'] ?? $today ?></lastmod>
        <changefreq><?= $url['changefreq'] ?></changefreq>
        <priority><?= $url['priority'] ?></priority>
    </url>
<?php endforeach; ?>
</urlset>
