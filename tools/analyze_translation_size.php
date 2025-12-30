<?php
// Compare English and French generated JSON sizes and show top diffs per card
if (PHP_SAPI !== 'cli') { echo "Run from CLI\n"; exit(1); }
$enPath = __DIR__ . '/data/cards-all-en.json';
$frPath = __DIR__ . '/data/cards-all-fr.json';
if (!file_exists($enPath) || !file_exists($frPath)) {
    fwrite(STDERR, "Missing files: ensure $enPath and $frPath exist\n");
    exit(1);
}
$enRaw = file_get_contents($enPath);
$frRaw = file_get_contents($frPath);
echo "EN bytes: " . strlen($enRaw) . "\n";
echo "FR bytes: " . strlen($frRaw) . "\n";
$en = json_decode($enRaw, true);
$fr = json_decode($frRaw, true);
if ($en === null || $fr === null) { fwrite(STDERR, "Invalid JSON\n"); exit(1); }
echo "EN count: " . count($en) . "\n";
echo "FR count: " . count($fr) . "\n";

// Check if EN was compact and FR pretty-printed
$enHasNewlines = strpos($enRaw, "\n") !== false;
$frHasNewlines = strpos($frRaw, "\n") !== false;
echo "EN contains newlines: " . ($enHasNewlines? 'yes':'no') . "\n";
echo "FR contains newlines: " . ($frHasNewlines? 'yes':'no') . "\n";

// Re-encode EN in pretty form to see expected pretty size
$enPretty = json_encode($en, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "EN pretty bytes: " . strlen($enPretty) . "\n";

// Re-encode FR without pretty to compare compact size
$frCompact = json_encode($fr, JSON_UNESCAPED_UNICODE);
echo "FR compact bytes: " . strlen($frCompact) . "\n";

// Build maps by code
$mapEn = [];
foreach ($en as $c) if (isset($c['code'])) $mapEn[$c['code']] = $c;
$mapFr = [];
foreach ($fr as $c) if (isset($c['code'])) $mapFr[$c['code']] = $c;

$diffs = [];
foreach ($mapEn as $code => $cEn) {
    $je = json_encode($cEn, JSON_UNESCAPED_UNICODE);
    $jf = isset($mapFr[$code]) ? json_encode($mapFr[$code], JSON_UNESCAPED_UNICODE) : '';
    $diffs[$code] = strlen($jf) - strlen($je);
}
arsort($diffs);
echo "Top 10 increases (code delta):\n";
$i=0; foreach ($diffs as $code => $d) { if ($i++>9) break; printf("%3d: %s %+d\n", $i, $code, $d); }

// Summarize total extra bytes for top 50
$totalTop=0; $j=0; foreach($diffs as $d) { if ($j++>49) break; $totalTop += max(0,$d); }
echo "Total positive delta for top 50 cards: $totalTop bytes\n";

// Heuristic explanation
echo "\nHeuristics:\n";
if (!$enHasNewlines && $frHasNewlines) {
    echo "- EN was compact, FR is pretty-printed; that explains most of the size increase.\n";
}
if (strlen($frRaw) - strlen($enRaw) > 1024*1024) {
    echo "- Size difference is >1MB; likely due to pretty-printing plus localized text being longer on average and possibly additional fields/traits expansions in FR packs.\n";
}
echo "\nDone.\n";
