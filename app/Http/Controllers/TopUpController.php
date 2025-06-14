<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class TopUpController extends Controller
{
    public function store(Request $request)
    {
        try {
            // Ensure user is authenticated
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized user.',
                ], 401);
            }

            // Validate input
            $validator = Validator::make($request->all(), [
                'game_name' => 'required|string|max:255',
                'amount' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $amount = $request->input('amount');
            $paymentMethod = $request->input('payment_method');
            $currentTime = Carbon::now('Asia/Jakarta'); // 06:28 PM WIB, 11 June 2025

            // Begin database transaction
            DB::beginTransaction();

            // If payment method is "Saldo", check and deduct balance
            if ($paymentMethod === 'Saldo') {
                $userBalance = DB::table('users')
                    ->where('id_user', $user->id_user)
                    ->value('money'); // Use 'money' column as per schema

                if ($userBalance < $amount) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient balance.',
                    ], 400);
                }

                // Deduct balance
                DB::table('users')
                    ->where('id_user', $user->id_user)
                    ->decrement('money', $amount);
            }

            // Save top-up record
            $topupId = DB::table('topups')->insertGetId([
                'id_user' => $user->id_user,
                'game_name' => $request->input('game_name'),
                'amount' => $amount,
                'created_at' => $currentTime,
                'updated_at' => $currentTime,
            ]);

            // Save transaction record
            DB::table('transactions')->insert([
                'id_user' => $user->id_user,
                'transaction_type' => 'topup',
                'reference_id' => $topupId,
                'amount' => $amount,
                'created_at' => $currentTime,
            ]);

            // Get updated balance
            $newBalance = DB::table('users')
                ->where('id_user', $user->id_user)
                ->value('money');

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Top-up created successfully',
                'data' => [
                    'topup_id' => $topupId,
                    'new_balance' => $newBalance ?? 0, // Fallback to 0 if null
                ],
            ], 201);

        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error creating top-up: ' . $e->getMessage(),
            ], 500);
        }
    }
}