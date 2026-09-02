<?php

declare(strict_types=1);

namespace App\Modules\Foundation\Data;

final readonly class DisasterRecoveryStatus
{
    /** @param array{backup_binding:bool,mysql_pitr:bool,object_recovery:bool,config_metadata_backup:bool} $controls
     * @param  array{rpo_seconds:int,rto_seconds:int}  $targets
     */
    public function __construct(
        public array $controls,
        public ?string $restoreEvidenceAt,
        public ?int $achievedRpoSeconds,
        public ?int $achievedRtoSeconds,
        public array $targets,
    ) {}

    /** @return array{controls:array{backup_binding:bool,mysql_pitr:bool,object_recovery:bool,config_metadata_backup:bool},restore_evidence_at:?string,achieved_rpo_seconds:?int,achieved_rto_seconds:?int,targets:array{rpo_seconds:int,rto_seconds:int}} */
    public function toArray(): array
    {
        return [
            'controls' => $this->controls,
            'restore_evidence_at' => $this->restoreEvidenceAt,
            'achieved_rpo_seconds' => $this->achievedRpoSeconds,
            'achieved_rto_seconds' => $this->achievedRtoSeconds,
            'targets' => $this->targets,
        ];
    }
}
