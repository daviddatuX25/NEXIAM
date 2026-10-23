<?php

namespace App\Ai\Telemetry;

/**
 * Contextual holder for run ID and correlation ID during GenAI executions (D-XIAM-03).
 */
final class GenAiTelemetryContext
{
    private static ?string $runId = null;

    private static ?string $correlationId = null;

    public static function set(?string $runId, ?string $correlationId = null): void
    {
        self::$runId = $runId;
        self::$correlationId = $correlationId;
    }

    public static function runId(): ?string
    {
        return self::$runId;
    }

    public static function correlationId(): ?string
    {
        return self::$correlationId;
    }

    public static function clear(): void
    {
        self::$runId = null;
        self::$correlationId = null;
    }
}
