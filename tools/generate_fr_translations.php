<?php
/**
 * Generate a French cards JSON by taking the English reference JSON
 * and replacing translatable fields from French pack files.
 *
 * Usage:
 * php tools/generate_fr_translations.php --en=tools/data/cards-all-en.json --fr-dir=..\\marvelsdb_fanmade_data\\translations\\fr\\pack --out=tools/data/cards-all-fr.json
 */

if (PHP_SAPI !== 'cli') {
    echo "This script must be run from the CLI.\n";
    exit(1);
}

$opts = getopt('', ['en:', 'fr-dir:', 'out::', 'player-en::', 'player-out::', 'pretty']);
if (empty($opts['en']) || empty($opts['fr-dir'])) {
    echo "Usage: php tools/generate_fr_translations.php --en=PATH_TO_EN_JSON --fr-dir=PATH_TO_FR_PACKS_DIR [--out=OUTPUT_PATH] [--player-en=PATH_TO_PLAYER_EN_JSON] [--player-out=OUTPUT_PATH]\n";
    exit(1);
}

$enPath = $opts['en'];
$frDir = rtrim($opts['fr-dir'], "\\/");
$outPath = isset($opts['out']) ? $opts['out'] : __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'cards-all-fr.json';
$playerEn = isset($opts['player-en']) ? $opts['player-en'] : null;
$playerOut = isset($opts['player-out']) ? $opts['player-out'] : (__DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'cards-player-fr.json');
$pretty = isset($opts['pretty']);

function read_json_file($path) {
    if (!file_exists($path)) {
        throw new \Exception("File not found: $path");
    }
    $content = file_get_contents($path);
    // Strip UTF-8 BOM
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") $content = substr($content, 3);
    $data = json_decode($content, true);
    if ($data === null) throw new \Exception("Invalid JSON in $path: " . json_last_error_msg());
    return $data;
}

function load_fr_map_from_packs($dir) {
    $map = [];
    if (!is_dir($dir)) throw new \Exception("Directory not found: $dir");
    $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
    foreach ($it as $file) {
        if ($file->isDir()) continue;
        if (strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION)) !== 'json') continue;
        try {
            $data = read_json_file($file->getPathname());
        } catch (Exception $e) {
            // skip invalid file but print a warning
            fwrite(STDERR, "Warning: skipping invalid JSON " . $file->getPathname() . " - " . $e->getMessage() . "\n");
            continue;
        }
        if (!is_array($data)) continue;
        // packs files are arrays of cards
        foreach ($data as $card) {
            if (!isset($card['code'])) continue;
            $code = $card['code'];
            $map[$code] = $card;
        }
    }
    return $map;
}

try {
    echo "Reading English reference: $enPath\n";
    $enCards = read_json_file($enPath);

    echo "Loading French packs from: $frDir\n";
    $frMap = load_fr_map_from_packs($frDir);
    echo "Found " . count($frMap) . " French card entries.\n";

    $fieldsToReplace = ['name','text','flavor','traits','subname','back_name','back_text','back_flavor'];

    $process = function($inCards, $frMap, $fieldsToReplace) {
        $out = [];
        foreach ($inCards as $card) {
            if (!isset($card['code'])) { $out[] = $card; continue; }
            $code = $card['code'];
            if (isset($frMap[$code])) {
                $fr = $frMap[$code];
                foreach ($fieldsToReplace as $f) {
                    if (array_key_exists($f, $fr) && $fr[$f] !== null && $fr[$f] !== '') {
                        $card[$f] = $fr[$f];
                    }
                }
                // traits may be an array or string in sources; keep fr if present
                if (isset($fr['traits'])) $card['traits'] = $fr['traits'];
            }
            $out[] = $card;
        }
        return $out;
    };

    // process main cards-all file
    $out = $process($enCards, $frMap, $fieldsToReplace);
    // Ensure output directory exists
    $outDir = dirname($outPath);
    if (!is_dir($outDir)) mkdir($outDir, 0775, true);
    $flags = JSON_UNESCAPED_UNICODE;
    if ($pretty) $flags |= JSON_PRETTY_PRINT;
    file_put_contents($outPath, json_encode($out, $flags));
    echo "Wrote French cards JSON to: $outPath\n";

    // optional: process player file
    if ($playerEn) {
        echo "Processing player file: $playerEn -> $playerOut\n";
        $playerInCards = read_json_file($playerEn);
        $playerOutCards = $process($playerInCards, $frMap, $fieldsToReplace);
        $playerOutDir = dirname($playerOut);
        if (!is_dir($playerOutDir)) mkdir($playerOutDir, 0775, true);
        $pflags = JSON_UNESCAPED_UNICODE;
        if ($pretty) $pflags |= JSON_PRETTY_PRINT;
        file_put_contents($playerOut, json_encode($playerOutCards, $pflags));
        echo "Wrote French player cards JSON to: $playerOut\n";
    }
} catch (Exception $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}

exit(0);
