<?php
/**
 * Admin — CSV Export
 *
 * Accepts the same filter params as extractions.php and streams
 * a CSV file for download.
 */
require_once __DIR__ . '/../../includes/Config.php';
Config::load();
require_once __DIR__ . '/../../includes/AdminAuth.php';
AdminAuth::check();

require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../../includes/ExtractionRepository.php';

$repo = new ExtractionRepository(Database::getInstance());

$filters = [
    'q'         => trim($_GET['q']         ?? ''),
    'status'    => $_GET['status']         ?? '',
    'domain'    => trim($_GET['domain']    ?? ''),
    'featured'  => $_GET['featured']       ?? '',
    'conf_min'  => $_GET['conf_min']       ?? '',
    'conf_max'  => $_GET['conf_max']       ?? '',
    'date_from' => $_GET['date_from']      ?? '',
    'date_to'   => $_GET['date_to']        ?? '',
];

$rows = $repo->exportRows($filters);

$filename = 'cleanplate-extractions-' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// BOM for Excel UTF-8 compatibility
fwrite($out, "\xEF\xBB\xBF");

// Header row
fputcsv($out, [
    'ID',
    'URL',
    'Domain',
    'Title',
    'Status',
    'Phase',
    'Confidence Score',
    'Confidence Level',
    'Submissions',
    'Cache Hits',
    'Site Name',
    'Author',
    'Total Time',
    'Servings',
    'Rating Value',
    'Rating Count',
    'Is Featured',
    'First Seen',
    'Last Seen',
    'Processing Time (ms)',
    'Error Code',
]);

foreach ($rows as $row) {
    fputcsv($out, [
        $row['id'],
        $row['url'],
        $row['domain'],
        $row['title']         ?? '',
        $row['status'],
        $row['phase']         ?? '',
        $row['confidence_score']  ?? '',
        $row['confidence_level']  ?? '',
        $row['submission_count'],
        $row['cache_hit_count'],
        $row['site_name']     ?? '',
        $row['author']        ?? '',
        $row['total_time']    ?? '',
        $row['servings']      ?? '',
        $row['rating_value']  ?? '',
        $row['rating_count']  ?? '',
        $row['is_featured'] ? 'yes' : 'no',
        $row['first_seen_at'] ?? '',
        $row['last_seen_at']  ?? '',
        $row['processing_time_ms'] ?? '',
        $row['error_code']    ?? '',
    ]);
}

fclose($out);
exit;
