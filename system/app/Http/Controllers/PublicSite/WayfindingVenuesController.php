<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Foundation\SpaceKind;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class WayfindingVenuesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $venues = Room::query()
            ->select([
                'id',
                'code',
                'name',
                'space_kind',
                'building_name',
                'floor_level',
                'directions_hint',
                'photo_path',
                'latitude',
                'longitude',
            ])
            ->where('institution_id', Institution::current()->id)
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderByRaw(
                'CASE space_kind '.implode(' ', array_map(fn ($i) => 'WHEN ? THEN '.($i + 1), array_keys(SpaceKind::cases()))).' ELSE '.(count(SpaceKind::cases()) + 1).' END',
                array_map(fn (SpaceKind $k) => $k->value, SpaceKind::cases()),
            )
            ->orderBy('code')
            ->get()
            ->map(fn (Room $room): array => [
                'id' => $room->id,
                'code' => $room->code,
                'name' => $room->name,
                'space_kind' => $room->space_kind?->value,
                'building_name' => $room->building_name,
                'floor_level' => $room->floor_level,
                'directions_hint' => $room->directions_hint,
                'photo_path' => $room->photo_path === null
                    ? null
                    : (str_starts_with($room->photo_path, '/')
                        ? $room->photo_path
                        : Storage::disk('public')->url($room->photo_path)),
                'latitude' => $room->latitude,
                'longitude' => $room->longitude,
            ]);

        return response()->json($venues);
    }
}
