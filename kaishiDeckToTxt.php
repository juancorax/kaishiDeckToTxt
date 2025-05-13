<?php

if (version_compare(PHP_VERSION, '8.4', '<')) {
    exit('Error: PHP 8.4 or higher is required'.PHP_EOL);
}
if (! class_exists('ZipArchive')) {
    exit("Error: The 'ZipArchive' extension is required".PHP_EOL);
}
if (! shell_exec('which zstd')) {
    exit("Error: The 'zstd' CLI tool is required".PHP_EOL);
}

$apkgFile = 'Kaishi.1.5k.apkg';

if (! file_exists($apkgFile)) {
    exit("Error: '$apkgFile' not found in this directory".PHP_EOL);
}

echo "'$apkgFile' found!".PHP_EOL;

$zip = new ZipArchive;
$apkgFileOpened = $zip->open($apkgFile);

if ($apkgFileOpened !== true) {
    exit('Error: Failed to open the .apkg file'.PHP_EOL);
}

$compressedDbFile = 'collection.anki21b';

$dbExtractionSucceeded = $zip->extractTo(__DIR__, $compressedDbFile);
$zip->close();

if ($dbExtractionSucceeded !== true) {
    exit('Error: Failed to extract the database'.PHP_EOL);
}

$dbFile = 'collection.sqlite';

exec("zstd -d $compressedDbFile -o $dbFile > /dev/null 2>&1");

$dbConnection = new SQLite3($dbFile);

$outputFile = 'kaishi.txt';
$outputFilePointer = fopen($outputFile, 'w');

$dbQuery = 'SELECT flds FROM notes';
$deckNotes = $dbConnection->query($dbQuery);

$deckNotes->fetchArray(SQLITE3_ASSOC);

while ($note = $deckNotes->fetchArray(SQLITE3_ASSOC)) {
    $fields = explode("\x1f", $note['flds']);

    $word = trim(strip_tags($fields[0] ?? ''));
    $sentence = trim(strip_tags($fields[5] ?? ''));

    if ($word && $sentence) {
        fwrite($outputFilePointer, $word.PHP_EOL);
        fwrite($outputFilePointer, $sentence.PHP_EOL.PHP_EOL);
    }
}

fclose($outputFilePointer);
$dbConnection->close();

unlink($compressedDbFile);
unlink($dbFile);

echo "'$outputFile' created!".PHP_EOL;
