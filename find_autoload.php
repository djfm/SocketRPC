<?php

$base = __DIR__;
$found = false;

for (;;) {

    $vendorCandidate = $base . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    $sameDirCandidate = $base . DIRECTORY_SEPARATOR . 'autoload.php';

    if (file_exists($vendorCandidate)) {
        require $vendorCandidate;
        $found = true;
        break;
    }

    if (file_exists($sameDirCandidate)) {
        require $sameDirCandidate;
        $found = true;
        break;
    }

    if (dirname($base) === $base) {
        break;
    } else {
        $base = dirname($base);
    }
}

if (!$found) {
    throw new Exception('Could not find autoloader.');
}

unset($base, $found);
