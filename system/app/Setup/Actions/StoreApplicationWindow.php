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

final class StoreApplicationWindow
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @param  array{academic_cycle_id: int|string, code: string, name: string, opens_at: string, closes_at: string, status: string}  $attributes
     */
    public function handle(array $attributes, User|string $actor, ?Institution $institution = null): ApplicationWindow
    {
        return DB::transaction(function () use ($attributes, $actor, $institution): ApplicationWindow {
            $currentInstitution = $institution ?? Institution::current();
            $cycle = AcademicCycle::query()->lockForUpdate()->findOrFail($attributes['academic_cycle_id']);

            if ((int) $cycle->institution_id !== (int) $currentInstitution->id) {
                throw ValidationException::withMessages([
                    'academic_cycle_id' => 'The selected academic cycle does not belong to the current institution.',
                ]);
            }

            $opensAt = Carbon::parse($attributes['opens_at']);
            $closesAt = Carbon::parse($attributes['closes_at']);

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

            $code = strtoupper(trim((string) $attributes['code']));

            $window = ApplicationWindow::query()
                ->where('academic_cycle_id', $cycle->id)
                ->where('code', $code)
                ->lockForUpdate()
                ->first();

            $payload = [
                'name' => trim((string) $attributes['name']),
                'opens_at' => $attributes['opens_at'],
                'closes_at' => $attributes['closes_at'],
                'status' => $attributes['status'],
            ];

            if ($window !== null) {
                $window->update($payload);
            } else {
                $window = ApplicationWindow::query()->create([
                    'academic_cycle_id' => $cycle->id,
                    'code' => $code,
                    ...$payload,
                ]);
            }

            $actorId = is_string($actor) ? $actor : (string) $actor->id;

            $this->audit->handle('setup.window_upserted', [
                'window_id' => $window->id,
                'code' => $window->code,
                'status' => $window->status,
            ], $actorId);

            return $window;
        });
    }
}
