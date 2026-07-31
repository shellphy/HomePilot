<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyOwnerRequest;
use App\Http\Resources\ResidentResource;
use App\Services\VerifyOwner;

class OwnerVerificationController extends Controller
{
    use ResolvesResident;

    public function store(VerifyOwnerRequest $request, VerifyOwner $verifyOwner): ResidentResource
    {
        $resident = $this->resident($request);
        $verifyOwner->handle($resident, $request->validated('invite_code'));

        return ResidentResource::make($resident->refresh());
    }
}
