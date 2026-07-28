<?php

/*
 * Test bootstrap: scrub host-machine storage configuration out of the
 * environment before the framework boots.
 *
 * The suite runs on whatever machine has the repo — a laptop, CI, a cloud
 * workspace. If that machine's shell happens to export PHOTO_DISK=r2 and real
 * bucket credentials (a deploy operator's environment, for example), the
 * photo tests would quietly upload their fixtures to the production bucket.
 * Tests must exercise the disks THEY configure, never the host's.
 */

require __DIR__.'/../vendor/autoload.php';

foreach (['PHOTO_DISK', 'R2_ACCESS_KEY_ID', 'R2_SECRET_ACCESS_KEY', 'R2_BUCKET', 'R2_ENDPOINT', 'FILESYSTEM_DISK'] as $name) {
    putenv($name);
    unset($_ENV[$name], $_SERVER[$name]);
}
