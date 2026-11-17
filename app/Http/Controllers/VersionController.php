<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class VersionController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['commit' => config('tracker.commit')]);
    }
}
