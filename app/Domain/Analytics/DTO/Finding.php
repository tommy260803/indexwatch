<?php

namespace App\Domain\Analytics\DTO;

use App\Domain\Analytics\Enums\ConfidenceLevel;
use App\Domain\Analytics\Enums\FindingSeverity;
use App\Domain\Analytics\Enums\FindingType;

final readonly class Finding
{
    /**
     * @param list<Evidence> $evidence
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public FindingType $type,
        public string $fingerprint,
        public FindingSeverity $severity,
        public ConfidenceLevel $confidence,
        public string $title,
        public string $description,
        public array $evidence,
        public string $recommendedAction,
        public array $metadata = [],
        public ?int $serverId = null,
        public ?int $sqlIndexId = null,
        public ?string $subjectType = null,
        public ?int $subjectId = null,
    ) {}

    public function toAlertData(): array
    {
        return [
            'alert_type' => $this->type->value,
            'severity' => $this->severity,
            'status' => 'pending',
            'recommended_action' => $this->recommendedAction,
            'fragmentation_percent' => $this->metadata['fragmentation_percent'] ?? null,
            'metadata' => array_merge($this->metadata, [
                'finding_confidence' => $this->confidence->value,
                'finding_type' => $this->type->value,
                'evidence' => array_map(fn (Evidence $e) => [
                    'key' => $e->key,
                    'label' => $e->label,
                    'value' => $e->value,
                    'unit' => $e->unit,
                    'description' => $e->description,
                ], $this->evidence),
            ]),
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
        ];
    }
}