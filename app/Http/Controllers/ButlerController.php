<?php

namespace App\Http\Controllers;

use App\Services\GroqButler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ButlerController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:1200',
            'context' => 'nullable|array',
        ]);

        $reply = GroqButler::reply(
            $validated['message'],
            $validated['context'] ?? [],
        );

        return response()->json($reply);
    }
}
