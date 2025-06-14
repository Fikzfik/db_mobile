<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class BookedPcController extends Controller
{
    public function getBookedTimes(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'warnet_id' => 'required|integer|exists:warnets,id_warnet',
            'pc_id' => 'required|integer|exists:pcs,id_pc',
            'date' => 'required|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $warnetId = $request->input('warnet_id');
            $pcId = $request->input('pc_id');
            $date = Carbon::parse($request->input('date'))->startOfDay();

            $pcExists = DB::table('pcs')
                ->where('id_pc', $pcId)
                ->where('id_warnet', $warnetId)
                ->exists();

            if (!$pcExists) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'The specified PC does not belong to the given warnet.',
                ], 400);
            }

            $bookedTimes = DB::table('booked_pcs')
                ->where('id_pc', $pcId)
                ->where('booking_status', 'confirmed')
                ->whereDate('start_time', $date)
                ->select(
                    DB::raw("TIME_FORMAT(start_time, '%H:%i:%s') as start_time"),
                    DB::raw("TIME_FORMAT(end_time, '%H:%i:%s') as end_time")
                )
                ->get();

            return response()->json($bookedTimes, 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch booked times',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            // Validasi input
            $request->validate([
                'pc_id' => 'required|integer|exists:pcs,id_pc',
                'id_user' => 'required|integer|exists:users,id_user', // Tambahkan validasi id_user
                'start_time' => 'required|date_format:Y-m-d H:i:s',
                'end_time' => 'required|date_format:Y-m-d H:i:s|after:start_time',
                'booking_status' => 'required|string|in:pending,confirmed,cancelled',
                'amount' => 'required|integer',
            ]);

            $startTime = Carbon::parse($request->input('start_time'));
            $endTime = Carbon::parse($request->input('end_time'));
            $currentTime = Carbon::parse('2025-06-02 09:34:00'); // 09:34 AM WIB, 02 Juni 2025

            // Validasi: Pastikan start_time tidak di masa lalu
            if ($startTime->lessThan($currentTime)) {
                return response()->json([
                    'message' => 'The start time cannot be in the past.',
                ], 400);
            }

            // Cek apakah ada pemesanan yang bentrok (menggunakan raw SQL)
            $conflict = DB::select(
                "
                SELECT *
                FROM booked_pcs
                WHERE id_pc = ?
                AND booking_status IN ('pending', 'confirmed')
                AND (
                    (start_time <= ? AND end_time >= ?)
                    OR (start_time <= ? AND end_time >= ?)
                    OR (start_time >= ? AND end_time <= ?)
                )
            ",
                [
                    $request->input('pc_id'),
                    $startTime->toDateTimeString(),
                    $startTime->toDateTimeString(),
                    $endTime->toDateTimeString(),
                    $endTime->toDateTimeString(),
                    $startTime->toDateTimeString(),
                    $endTime->toDateTimeString()
                ]
            );

            if (!empty($conflict)) {
                return response()->json([
                    'message' => 'The selected time slot is already booked.',
                ], 409);
            }

            // Simpan pemesanan baru
            $bookingId = DB::table('booked_pcs')->insertGetId([
                'id_pc' => $request->input('pc_id'),
                'id_user' => $request->input('id_user'), // Simpan id_user dari request
                'start_time' => $startTime->toDateTimeString(),
                'end_time' => $endTime->toDateTimeString(),
                'booking_status' => $request->input('booking_status'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Simpan transaksi
            DB::table('transactions')->insert([
                'id_user' => $request->input('id_user'), // Simpan id_user dari request
                'transaction_type' => 'pc_booking',
                'reference_id' => $bookingId,
                'amount' => $request->input('amount'), // Gunakan amount dari request, bukan calculateAmount
                'created_at' => now(),
            ]);

            return response()->json([
                'message' => 'PC booking created successfully',
                'booking_id' => $bookingId,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error creating booking: ' . $e->getMessage(),
            ], 500);
        }
    }



    public function getHistory($userId)
    {
        try {
            // Validasi userId
            if (!is_numeric($userId) || $userId <= 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid user ID',
                ], 400);
            }

            // Set time zone to Asia/Jakarta
            Carbon::setToStringFormat('Asia/Jakarta');

            // Ambil semua transaksi berdasarkan id_user
            $transactions = DB::table('transactions')
                ->where('transactions.id_user', $userId)
                ->leftJoin('booked_pcs', function ($join) {
                    $join->on('transactions.reference_id', '=', 'booked_pcs.id_booked_pc')
                        ->where('transactions.transaction_type', '=', 'pc_booking');
                })
                ->leftJoin('pcs', 'booked_pcs.id_pc', '=', 'pcs.id_pc')
                ->leftJoin('warnets as pc_warnet', 'pcs.id_warnet', '=', 'pc_warnet.id_warnet')
                ->leftJoin('booked_console', function ($join) {
                    $join->on('transactions.reference_id', '=', 'booked_console.id_booked_console')
                        ->where('transactions.transaction_type', '=', 'console_booking');
                })
                ->leftJoin('playstations', 'booked_console.id_playstation', '=', 'playstations.id_playstation')
                ->leftJoin('warnets as console_warnet', 'playstations.id_warnet', '=', 'console_warnet.id_warnet')
                ->leftJoin('topups', function ($join) {
                    $join->on('transactions.reference_id', '=', 'topups.id_topup')
                        ->where('transactions.transaction_type', '=', 'topup');
                })
                ->leftJoin('jasa_joki', function ($join) {
                    $join->on('transactions.reference_id', '=', 'jasa_joki.id_jasa_joki')
                        ->where('transactions.transaction_type', '=', 'joki');
                })
                ->select(
                    'transactions.id_transaction as transactionId',
                    'transactions.transaction_type',
                    'transactions.amount',
                    'transactions.created_at as transaction_date',
                    'booked_pcs.start_time as pc_start_time',
                    'booked_pcs.end_time as pc_end_time',
                    'pcs.pc_name as pc_name',
                    'pc_warnet.warnet_name as pc_warnet_name',
                    'booked_console.start_time as console_start_time',
                    'booked_console.end_time as console_end_time',
                    'playstations.ps_name as ps_name',
                    'console_warnet.warnet_name as console_warnet_name',
                    'topups.game_name as topup_game_name',
                    'jasa_joki.game_name as joki_game_name',
                    'jasa_joki.service_description as joki_service_description'
                )
                ->get();

            // Format data untuk frontend with error handling for invalid dates
            $formattedTransactions = $transactions->map(function ($transaction) {
                try {
                    $amount = "Rp " . number_format($transaction->amount, 0, ',', '.');
                    $transactionId = "TXN{$transaction->transactionId}";

                    if ($transaction->transaction_type === 'pc_booking' && $transaction->pc_start_time && $transaction->pc_end_time) {
                        $startTime = Carbon::parse($transaction->pc_start_time);
                        $endTime = Carbon::parse($transaction->pc_end_time);
                        $date = $startTime->format('d M y H:m') . ' to ' . $endTime->format('H:m');
                        return [
                            'transaction_type' => $transaction->transaction_type,
                            'title' => "Rental PC {$transaction->pc_name} at {$transaction->pc_warnet_name}",
                            'amount' => $amount,
                            'date' => $date,
                            'details' => "Booked PC {$transaction->pc_name} from {$startTime->format('H:i')} to {$endTime->format('H:i')} on {$startTime->format('d M y')}",
                            'transactionId' => $transactionId,
                        ];
                    } elseif ($transaction->transaction_type === 'console_booking' && $transaction->console_start_time && $transaction->console_end_time) {
                        $startTime = Carbon::parse($transaction->console_start_time);
                        $endTime = Carbon::parse($transaction->console_end_time);
                        $date = $startTime->format('d M y H:m') . ' to ' . $endTime->format('H:m');
                        return [
                            'transaction_type' => $transaction->transaction_type,
                            'title' => "Rental PlayStation {$transaction->ps_name} at {$transaction->console_warnet_name}",
                            'amount' => $amount,
                            'date' => $date,
                            'details' => "Booked PlayStation {$transaction->ps_name} from {$startTime->format('H:i')} to {$endTime->format('H:i')} on {$startTime->format('d M y')}",
                            'transactionId' => $transactionId,
                        ];
                    } elseif ($transaction->transaction_type === 'topup') {
                        $date = Carbon::parse($transaction->transaction_date)->format('d M y H:m');
                        return [
                            'transaction_type' => $transaction->transaction_type,
                            'title' => "Top Up for {$transaction->topup_game_name}",
                            'amount' => $amount,
                            'date' => $date,
                            'details' => "Topped up {$amount} for {$transaction->topup_game_name}",
                            'transactionId' => $transactionId,
                        ];
                    } elseif ($transaction->transaction_type === 'joki') {
                        $date = Carbon::parse($transaction->transaction_date)->format('d M y H:m');
                        return [
                            'transaction_type' => $transaction->transaction_type,
                            'title' => "Joki Service for {$transaction->joki_game_name}",
                            'amount' => $amount,
                            'date' => $date,
                            'details' => $transaction->joki_service_description ?? "Joki service for {$transaction->joki_game_name}",
                            'transactionId' => $transactionId,
                        ];
                    } else {
                        $date = Carbon::parse($transaction->transaction_date)->format('d M y H:m');
                        return [
                            'transaction_type' => $transaction->transaction_type,
                            'title' => "Transaction {$transaction->transaction_type}",
                            'amount' => $amount,
                            'date' => $date,
                            'details' => "Details for {$transaction->transaction_type} transaction",
                            'transactionId' => $transactionId,
                        ];
                    }
                } catch (\Exception $e) {
                    // Fallback for invalid dates
                    return [
                        'transaction_type' => $transaction->transaction_type ?? 'unknown',
                        'title' => "Transaction Error",
                        'amount' => $amount ?? "Rp 0",
                        'date' => 'Invalid Date',
                        'details' => "Error processing transaction: Invalid date",
                        'transactionId' => $transactionId ?? 'Unknown ID',
                    ];
                }
            })->all();

            return response()->json([
                'status' => 'success',
                'data' => $formattedTransactions,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch history: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function calculateAmount(Carbon $startTime, Carbon $endTime)
    {
        $hours = $endTime->diffInHours($startTime);
        return $hours * 50000; // Harga per jam: IDR 50,000
    }
}
