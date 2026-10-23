<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Place;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PlaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->places()->orderBy('id')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $max = config('veiligbuiten.places.max_per_user');
        if ($user->places()->count() >= $max) {
            throw ValidationException::withMessages(['name' => __('places.max', ['max' => $max])]);
        }
        $place = $user->places()->create($this->validated($request, required: true));

        return response()->json(['data' => $place], 201);
    }

    public function update(Request $request, Place $place): JsonResponse
    {
        abort_unless($place->user_id === $request->user()->id, 404);
        $place->update($this->validated($request, required: false));

        return response()->json(['data' => $place]);
    }

    public function destroy(Request $request, Place $place): JsonResponse
    {
        abort_unless($place->user_id === $request->user()->id, 404);
        $place->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, bool $required): array
    {
        $rule = $required ? 'required' : 'sometimes';
        [$min, $max] = [config('veiligbuiten.places.min_radius_m'), config('veiligbuiten.places.max_radius_m')];

        return $request->validate([
            'name' => "{$rule}|string|max:40",
            'label' => 'nullable|string|max:120',
            'latitude' => "{$rule}|numeric|between:50.5,53.7",
            'longitude' => "{$rule}|numeric|between:3.1,7.3",
            'radius_m' => "sometimes|integer|between:{$min},{$max}",
            'alerts_enabled' => 'sometimes|boolean',
        ]);
    }
}
