<?php

return [
    'path' => env('BACKUP_PATH', '/backups'),
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 7),
    'time' => env('BACKUP_TIME', '03:00'),
];
