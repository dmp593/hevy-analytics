<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Progress photo disk
    |--------------------------------------------------------------------------
    | Its own setting rather than the default disk, because these are the one
    | kind of file that MUST survive a redeploy: an ephemeral container disk
    | silently loses months of someone's progress photos. 'local' is right for
    | development; set PHOTO_DISK=r2 in production.
    */

    'photos' => env('PHOTO_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        /*
         | Cloudflare R2 — where progress photos live in production.
         |
         | S3-compatible, so it is the same driver with a different endpoint.
         | Chosen over S3 or Cloudinary for one reason that matters here: R2
         | charges NOTHING for egress. Every photo is streamed through the app
         | (see ProgressPhotoController::show) rather than served from a public
         | URL, because these are pictures of someone's body and a guessable
         | public link is not access control. That design means every view is
         | an egress from the bucket — which on S3 would be a bill that grows
         | with usage, and on R2 is free.
         |
         | 'visibility' is deliberately absent: R2 buckets are private by
         | default and nothing here should ever make one public.
         */
        'r2' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'bucket' => env('R2_BUCKET'),
            'endpoint' => env('R2_ENDPOINT'),
            // R2 ignores the region but the SDK insists on one being present.
            'region' => 'auto',
            'use_path_style_endpoint' => true,
            // Loud, not silent: a photo that failed to store must not leave a
            // database row pointing at a file that was never written.
            'throw' => true,
            'report' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
