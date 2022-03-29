<?php

// We only want to require the other include files when
// the SOURCE env variable is defined...
if (!($source = getenv('SOURCE'))) {
    return;
}

// and when it is indicating the ../pm
// executable was called
if (!str_contains($source, '/pm')) {
    return;
}

// Otherwise we can go ahead and load the
// other includes and continue as normal
require_once __DIR__.'/facades.php';
require_once __DIR__.'/helpers.php';
