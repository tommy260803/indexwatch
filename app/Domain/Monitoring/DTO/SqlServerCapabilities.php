<?php

namespace App\Domain\Monitoring\DTO;

use DateTimeInterface;

final readonly class SqlServerCapabilities
{
    public function __construct(
        public string $serverName,
        public string $databaseName,
        public string $productVersion,
        public int $productMajorVersion,
        public string $edition,
        public int $engineEdition,
        public ?bool $hasViewDefinition,
        public ?bool $hasViewDatabaseState,
        public ?bool $hasViewDatabasePerformanceState,
        public ?bool $hasViewServerState,
        public ?bool $hasViewServerPerformanceState,
        public ?bool $hasDatabaseSelect,
        public ?DateTimeInterface $sampledAt,
    ) {}

    public static function fromRow(object $row): self
    {
        return new self(
            serverName: (string) $row->server_name,
            databaseName: (string) $row->database_name,
            productVersion: (string) $row->product_version,
            productMajorVersion: (int) $row->product_major_version,
            edition: (string) $row->edition,
            engineEdition: (int) $row->engine_edition,
            hasViewDefinition: self::nullableBool($row->has_view_definition),
            hasViewDatabaseState: self::nullableBool($row->has_view_database_state),
            hasViewDatabasePerformanceState: self::nullableBool($row->has_view_database_performance_state),
            hasViewServerState: self::nullableBool($row->has_view_server_state),
            hasViewServerPerformanceState: self::nullableBool($row->has_view_server_performance_state),
            hasDatabaseSelect: self::nullableBool($row->has_database_select),
            sampledAt: self::date($row->sampled_at_utc),
        );
    }

    public function toArray(): array
    {
        return [
            'server_name' => $this->serverName,
            'database_name' => $this->databaseName,
            'product_version' => $this->productVersion,
            'product_major_version' => $this->productMajorVersion,
            'edition' => $this->edition,
            'engine_edition' => $this->engineEdition,
            'permissions' => [
                'view_definition' => $this->hasViewDefinition,
                'view_database_state' => $this->hasViewDatabaseState,
                'view_database_performance_state' => $this->hasViewDatabasePerformanceState,
                'view_server_state' => $this->hasViewServerState,
                'view_server_performance_state' => $this->hasViewServerPerformanceState,
                'database_select' => $this->hasDatabaseSelect,
            ],
            'features' => [
                'online_index_operations' => $this->supportsOnlineIndexOperations(),
                'resumable_index_operations' => $this->supportsResumableIndexOperations(),
                'index_operational_stats' => $this->productMajorVersion >= 13,
            ],
            'sampled_at' => $this->sampledAt?->format(DATE_ATOM),
        ];
    }

    public function supportsOnlineIndexOperations(): bool
    {
        return in_array($this->engineEdition, [5, 8], true)
            || str_contains(strtolower($this->edition), 'enterprise')
            || str_contains(strtolower($this->edition), 'developer');
    }

    public function supportsResumableIndexOperations(): bool
    {
        return $this->productMajorVersion >= 14 && $this->supportsOnlineIndexOperations();
    }

    private static function nullableBool(mixed $value): ?bool
    {
        return $value === null ? null : (bool) $value;
    }

    private static function date(mixed $value): ?DateTimeInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof DateTimeInterface ? $value : new \DateTimeImmutable((string) $value);
    }
}
