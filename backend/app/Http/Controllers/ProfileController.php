<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('assets');

        $assets = $user->assets->map(function ($asset) {
            return [
                'symbol' => $asset->symbol,
                'amount' => $asset->amount,
                'locked_amount' => $asset->locked_amount,
                'available' => bcsub($asset->amount, $asset->locked_amount, 8),
            ];
        });

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'balance' => $user->balance,
            'assets' => $assets,
        ]);
    }
}
