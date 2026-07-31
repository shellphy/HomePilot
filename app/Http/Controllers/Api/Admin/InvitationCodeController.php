<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use App\Services\GetCurrentOwnerInvitationCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvitationCodeController extends Controller
{
    use ResolvesResident;

    public function store(Request $request, GetCurrentOwnerInvitationCode $getCurrentOwnerInvitationCode): JsonResponse
    {
        $invitation = $getCurrentOwnerInvitationCode->handle($this->resident($request));

        return response()->json([
            'data' => [
                'code' => $invitation['code'],
                'expires_at' => $invitation['expires_at']->toIso8601String(),
            ],
        ]);
    }
}
