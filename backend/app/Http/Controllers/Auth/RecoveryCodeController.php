<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Http\Controllers\RecoveryCodeController as FortifyRecoveryCodeController;

/**
 * Fortify's "show recovery codes": only right after they were made, because
 * only their hashes are stored (SECURITY_REPORT.md M5). Making new ones is unchanged.
 */
class RecoveryCodeController extends FortifyRecoveryCodeController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json($user instanceof User && $user->two_factor_secret ? $user->recoveryCodes() : [])
            ->header('Cache-Control', 'no-store');
    }
}
