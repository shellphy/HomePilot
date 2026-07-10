<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use App\Models\Matter;
use App\Models\Record;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JoinController extends Controller
{
    use ResolvesResident;

    /**
     * 接龙表态（幂等：重复报名不会产生第二条）。
     */
    public function store(Request $request, Matter $matter): JsonResponse
    {
        $resident = $this->resident($request);

        abort_unless($matter->typeDef()->allowsJoin($matter), 422, '当前不能报名');

        $matter->joins()->firstOrCreate([
            'resident_id' => $resident->id,
            'mode' => Record::MODE_JOIN,
        ]);

        return response()->json([
            'joined' => true,
            'join_count' => $matter->joins()->count(),
        ], 201);
    }

    /**
     * 取消接龙。
     */
    public function destroy(Request $request, Matter $matter): JsonResponse
    {
        $matter->joins()->whereBelongsTo($this->resident($request), 'resident')->delete();

        return response()->json([
            'joined' => false,
            'join_count' => $matter->joins()->count(),
        ]);
    }
}
