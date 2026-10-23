<?php

namespace App\Setup\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateApplicationWindow
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @param  array{code?: string, name?: string, opens_at?: string, closes_at?: string, status?: string}  $attributes
     */
    public function handle(ApplicationWindow $window, array $attributes, User|string $actor, ?Institution $institution = null): ApplicationWindow
    {
        return DB::transaction(function () use ($window, $attributes, $actor, $institution): ApplicationWindow {
            $currentInstitution = $institution ?? Institution::current();
            $lockedWindow = ApplicationWindow::query()->lockForUpdate()->findOrFail($window->id);
            $cycle = AcademicCycle::query()->lockForUpdate()->findOrFail($lockedWindow->academic_cycle_id);

            if ((int) $cycle->institution_id !== (int) $currentInstitution->id) {
                throw ValidationException::withMessages([
                    'academic_cycle_id' => 'The selected academic cycle does not belong to the current institution.',
                ]);
            }

            $opensAtStr = $attributes['opens_at'] ?? $lockedWindow->opens_at?->toDateTimeString();
            $closesAtStr = $attributes['closes_at'] ?? $lockedWindow->closes_at?->toDateTimeString();

            if ($opensAtStr !== null && $closesAtStr !== null) {
                $opensAt = Carbon::parse($opensAtStr);
                $closesAt = Carbon::parse($closesAtStr);

                if ($closesAt->lte($opensAt)) {
                    throw ValidationException::withMessages([
                        'closes_at' => 'The window closing time must be after the opening time.',
                    ]);
                }

                $cycleStart = $cycle->opens_on?->clone()->startOfDay();
                $cycleEnd = $cycle->closes_on?->clone()->endOfDay();

                if ($cycleStart !== null && $opensAt->lt($cycleStart)) {
                    throw ValidationException::withMessages([
                        'opens_at' => 'The application window opening date cannot be before the academic cycle start date.',
                    ]);
                }

                if ($cycleEnd !== null && $closesAt->gt($cycleEnd)) {
                    throw ValidationException::withMessages([
                        'closes_at' => 'The application window closing date cannot be after the academic cycle end date.',
                    ]);
                }
            }

            $updateData = [];
            if (isset($attributes['name'])) {
                $updateData['name'] = trim((string) $attributes['name']);
            }
            if (isset($attributes['code'])) {
                $updateData['code'] = strtoupper(trim((string) $attributes['code']));
            }
            if (isset($attributes['opens_at'])) {
                $updateData['opens_at'] = $attributes['opens_at'];
            }
            if (isset($attributes['closes_at'])) {
                $updateData['closes_at'] = $attributes['closes_at'];
            }
            if (isset($attributes['status'])) {
                $updateData['status'] = $attributes['status'];
            }

            if (! empty($updateData)) {
                $lockedWindow->update($updateData);
            }

            $actorId = is_string($actor) ? $actor : (string) $actor->id;

            $this->audit->handle('setup.window_upserted', [
                'window_id' => $lockedWindow->id,
                'code' => $lockedWindow->code,
                'status' => $lockedWindow->status,
            ], $actorId);

            return $lockedWindow->fresh();
        });
    }
}
