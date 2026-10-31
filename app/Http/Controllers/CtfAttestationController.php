<?php

namespace App\Http\Controllers;

use App\Services\VerifierAttestationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CtfAttestationController extends Controller
{
    public function store(Request $request, VerifierAttestationService $attestations): JsonResponse
    {
        abort_unless(config('lab.ctf.enabled') === true, 404);

        $result = $attestations->accept($request);

        return response()->json($result['body'], $result['status']);
    }
}
