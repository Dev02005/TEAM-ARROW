<?php
/**
 * TalentAP Submission Packager
 * Packages the project strictly in accordance with TalentAP Coding Guidelines Rule 2:
 *
 * <teamID>_H4.zip
 * ├── README.md
 * ├── .gitignore
 * ├── submission/
 * │   ├── H4_predictions.csv
 * │   └── H4_resolution.csv
 * └── FULL_CODE/
 *     ├── ... all application code, config, database schema, tests ...
 */

declare(strict_types=1);

$opts = getopt('', ['team:']);
$teamId = $opts['team'] ?? 'TEAM_H4';
$zipName = "{$teamId}_H4.zip";

// Project root is two levels up: FULL_CODE/scripts/ -> FULL_CODE/ -> project root
$projectRoot = dirname(dirname(__DIR__));
$fullCodeDir  = dirname(__DIR__);           // FULL_CODE/
$zipPath      = $projectRoot . '/' . $zipName;

if (file_exists($zipPath)) {
    unlink($zipPath);
}

echo "Packaging submission for {$teamId} into {$zipName}...\n";

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("Failed to create ZIP at {$zipPath}\n");
}

// 1. Top-level files (from project root)
$topFiles = [
    'README.md'  => $projectRoot . '/README.md',
    '.gitignore' => $projectRoot . '/.gitignore',
];

foreach ($topFiles as $entryName => $path) {
    if (file_exists($path)) {
        $zip->addFile($path, $entryName);
        echo "  [TOP] Added {$entryName}\n";
    }
}

// 2. Submission files (project root/submission/)
$subDir   = $projectRoot . '/submission';
$subFiles = ['H4_predictions.csv', 'H4_resolution.csv'];
foreach ($subFiles as $sf) {
    $p = $subDir . '/' . $sf;
    if (file_exists($p)) {
        $zip->addFile($p, "submission/{$sf}");
        echo "  [SUBMISSION] Added submission/{$sf}\n";
    } else {
        echo "  [WARNING] Missing submission/{$sf}!\n";
    }
}

// 3. FULL_CODE/ items: iterate the FULL_CODE directory only
//    Exclude: data/, submission_train/, .env, .git/, *.zip, *.log, *.tmp,
//             score*.json, scratch/
$excludePatterns = [
    '/^data(\\/|\\\\|$)/',
    '/^submission_train(\\/|\\\\|$)/',
    '/^\.env$/',                          // never ship real secrets (.env.example is included)
    '/^\.git(\\/|\\\\|$)/',
    '/\.zip$/i',
    '/\.log$/i',
    '/\.tmp$/i',
    '/^score.*\.json$/i',
    '/^scratch(\\/|\\\\|$)/',
];

$dirIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($fullCodeDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$fullCodeCount = 0;
foreach ($dirIterator as $file) {
    $relPath = str_replace('\\', '/', substr($file->getPathname(), strlen($fullCodeDir) + 1));

    $skip = false;
    foreach ($excludePatterns as $pat) {
        if (preg_match($pat, $relPath)) {
            $skip = true;
            break;
        }
    }
    if ($skip) {
        continue;
    }

    if ($file->isDir()) {
        $zip->addEmptyDir("FULL_CODE/{$relPath}");
    } else {
        $zip->addFile($file->getPathname(), "FULL_CODE/{$relPath}");
        $fullCodeCount++;
    }
}

$zip->close();

$zipSizeMb = round(filesize($zipPath) / (1024 * 1024), 2);
echo "==================================================================\n";
echo "  Submission ZIP Created Successfully!                            \n";
echo "==================================================================\n";
echo "File: {$zipPath} ({$zipSizeMb} MB)\n";
echo "Included {$fullCodeCount} files in FULL_CODE/\n";
echo "Ready for upload to the hackathon evaluation portal!\n";
echo "==================================================================\n";
