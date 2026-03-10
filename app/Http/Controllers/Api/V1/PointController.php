<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserPointResource;
use App\Models\UserPoint;
use App\Services\Scoring\ScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PointController extends Controller
{
    public function __construct(private ScoringService $scoring) {}

    /**
     * Return current balance and the 20 most recent point entries.
     */
    public function index(Request $request): JsonResponse
    {
        $user    = $request->user();
        $balance = $this->scoring->getBalance($user);

        $recent = UserPoint::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return response()->json([
            'balance' => $balance,
            'recent'  => UserPointResource::collection($recent),
        ]);
    }

    /**
     * Return paginated full points ledger for the authenticated user.
     */
    public function history(Request $request): AnonymousResourceCollection
    {
        $points = UserPoint::withoutGlobalScopes()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(50);

        return UserPointResource::collection($points);
    }
}
