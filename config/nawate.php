<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Nawate registers no routes and is fully inert unless explicitly opted
    | in. Default to false so it can never be reachable in production by
    | accident — flip to true only in local/staging/demo environments.
    |
    */
    'enabled' => env('NAWATE_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Signed URL TTL (minutes)
    |--------------------------------------------------------------------------
    |
    | How long a nawate state-switch link remains valid after issuance.
    |
    */
    'signed_url_ttl' => env('NAWATE_SIGNED_URL_TTL', 60),

    /*
    |--------------------------------------------------------------------------
    | Demo DB storage path
    |--------------------------------------------------------------------------
    |
    | Where per-session SQLite file copies are written. Relative to
    | storage_path() unless an absolute path is given.
    |
    */
    'demo_db_storage_path' => env('NAWATE_DEMO_DB_STORAGE_PATH', 'app/nawate/demo-sessions'),

    /*
    |--------------------------------------------------------------------------
    | Template DB path
    |--------------------------------------------------------------------------
    |
    | Absolute path to a migrated, empty-data SQLite file that the host app
    | prepares and keeps in sync with its schema. Nawate copies this file
    | per demo session rather than owning migrations for the demo data.
    |
    */
    'template_db_path' => env('NAWATE_TEMPLATE_DB_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Demo connection name
    |--------------------------------------------------------------------------
    |
    | The database connection name nawate registers and repoints at each
    | demo session's SQLite copy. Kept out of the host app's own connection
    | names so fragments/Seeders never need to reference it explicitly.
    |
    */
    'connection' => env('NAWATE_CONNECTION', 'nawate_demo'),

    /*
    |--------------------------------------------------------------------------
    | Cleanup after (hours)
    |--------------------------------------------------------------------------
    |
    | Per-session SQLite copies older than this are eligible for cleanup by
    | the nawate cleanup command/schedule.
    |
    */
    'cleanup_after_hours' => env('NAWATE_CLEANUP_AFTER_HOURS', 24),
];
