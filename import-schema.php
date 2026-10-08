<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$dump = file_get_contents(__DIR__ . '/database.sql');
if ($dump === false) {
    fwrite(STDERR, "Could not read database.sql.\n");
    exit(1);
}

preg_match_all('/(?:CREATE TABLE|ALTER TABLE)\s+`[^`]+`.*?;/is', $dump, $matches);
$dumpStatements = $matches[0];
if (count($dumpStatements) !== 33) {
    fwrite(STDERR, 'Expected 33 source schema statements, found ' . count($dumpStatements) . ". No SQL was executed.\n");
    exit(1);
}

$primaryKeys = [];
foreach ($dumpStatements as $statement) {
    if (preg_match('/^ALTER TABLE `([^`]+)`\s+ADD PRIMARY KEY\s+\(([^)]+)\)/i', $statement, $keyMatch)) {
        $primaryKeys[$keyMatch[1]] = $keyMatch[2];
    }
}

$statements = [];
$schemaOnly = in_array('--schema-only', $argv, true);
foreach ($dumpStatements as $statement) {
    if (preg_match('/^CREATE TABLE `([^`]+)`/i', $statement, $tableMatch)) {
        $table = $tableMatch[1];
        if (!isset($primaryKeys[$table])) {
            fwrite(STDERR, "No primary key found in the dump for table $table. No SQL was executed.\n");
            exit(1);
        }

        $statement = preg_replace(
            '/\)\s*ENGINE=/i',
            ', PRIMARY KEY (' . $primaryKeys[$table] . ')) ENGINE=',
            $statement,
            1,
            $replacements
        );
        if ($replacements !== 1) {
            fwrite(STDERR, "Could not inline primary key for table $table. No SQL was executed.\n");
            exit(1);
        }
        $statement = preg_replace('/^CREATE TABLE `/i', 'CREATE TABLE IF NOT EXISTS `', $statement, 1);
        $statement = preg_replace('/ENGINE=MyISAM/i', 'ENGINE=InnoDB', $statement);
        $statements[] = $statement;
        continue;
    }

    if (!preg_match('/^ALTER TABLE `[^`]+`\s+ADD PRIMARY KEY/i', $statement)) {
        $statements[] = $statement;
    }
}

if (count($primaryKeys) !== 11 || count($statements) !== 22) {
    fwrite(STDERR, 'Expected 11 inline primary keys and 22 executable schema statements, found ' . count($primaryKeys) . ' keys and ' . count($statements) . " statements. No SQL was executed.\n");
    exit(1);
}

if (($argv[1] ?? '') === '--check') {
    echo count($primaryKeys) . ' primary keys inlined; ' . substr_count(implode("\n", $statements), 'ENGINE=InnoDB') . ' InnoDB table definitions; ' . count($statements) . " schema statements ready; no SQL executed.\n";
    exit(0);
}

require_once __DIR__ . '/db.php';

$existingTables = mysqli_query($link, 'SHOW TABLES');
if ($existingTables === false) {
    fwrite(STDERR, 'Could not inspect the target database: ' . mysqli_error($link) . "\n");
    exit(1);
}
$allowedTables = array_fill_keys(array_keys($primaryKeys), true);
while ($existingTable = mysqli_fetch_row($existingTables)) {
    if (!isset($allowedTables[$existingTable[0]])) {
        fwrite(STDERR, 'Unexpected table ' . $existingTable[0] . ' found; import was not run.\n');
        exit(1);
    }
}

foreach ($statements as $index => $statement) {
    if (!mysqli_query($link, $statement)) {
        fwrite(STDERR, 'Schema statement ' . ($index + 1) . ' failed: ' . mysqli_error($link) . "\n");
        exit(1);
    }
}

if (!$schemaOnly) {
    preg_match_all('/INSERT INTO\s+`[^`]+`.*?;/is', $dump, $insertMatches);
    foreach ($insertMatches[0] as $insertStmt) {
        $ignoreStmt = preg_replace('/^INSERT INTO/i', 'INSERT IGNORE INTO', $insertStmt);
        if (!mysqli_query($link, $ignoreStmt)) {
            fwrite(STDERR, 'Data statement failed: ' . mysqli_error($link) . "\n");
            exit(1);
        }
    }
}

$tables = mysqli_query($link, 'SHOW TABLES');
if ($tables === false || mysqli_num_rows($tables) !== 11) {
    fwrite(STDERR, "Schema statements ran, but the expected 11 tables were not found.\n");
    exit(1);
}

if ($schemaOnly) {
    echo 'Imported schema only; no seed rows were inserted: ' . mysqli_num_rows($tables) . " tables ready.\n";
} else {
    echo 'Imported schema and seed data successfully: ' . mysqli_num_rows($tables) . " tables ready.\n";
}
?>