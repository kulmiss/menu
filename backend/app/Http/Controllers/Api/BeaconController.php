<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * Receives the guest page's visit beacon (sent after load, PLAN.md §15.2.7).
 * Counting starts with Insights (Partition 10); until then it only answers.
 */
class BeaconController extends Controller
{
    public function __invoke(): Response
    {
        return response()->noContent();
    }
}
