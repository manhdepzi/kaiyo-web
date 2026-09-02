<?php

declare(strict_types=1);

return [
    /* Provider/account identifiers stay outside application source. These values only attest whether operational bindings exist. */
    'backup_binding_configured' => env('DR_BACKUP_BINDING_CONFIGURED', false),
    'mysql_pitr_configured' => env('DR_MYSQL_PITR_CONFIGURED', false),
    'object_recovery_configured' => env('DR_OBJECT_RECOVERY_CONFIGURED', false),
    'config_metadata_backup_configured' => env('DR_CONFIG_METADATA_BACKUP_CONFIGURED', false),
    'restore_evidence_at' => env('DR_RESTORE_EVIDENCE_AT'),
    'achieved_rpo_seconds' => env('DR_ACHIEVED_RPO_SECONDS'),
    'achieved_rto_seconds' => env('DR_ACHIEVED_RTO_SECONDS'),
    'targets' => [
        'rpo_seconds' => 900,
        'rto_seconds' => 7200,
    ],
];
