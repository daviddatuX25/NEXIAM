<?php

namespace App\Foundation\Projections;

use App\Staff\Queries\AuditLogProjection as StaffAuditLogProjection;

/**
 * Foundation projection alias/extension for AuditLogProjection.
 */
final class AuditLogProjection
{
    public function __construct(private readonly StaffAuditLogProjection $projection) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function get(array $filters = []): array
    {
        return $this->projection->get($filters);
    }
}
