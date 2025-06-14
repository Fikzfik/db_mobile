<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class BookedPsController extends Controller
{
    public function getBookedTimes(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'warnet_id' => 'required|integer|exists:warnets,id_warnet',
            'ps_id' => 'required|integer|exists:playstations,id_playstation',
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
            $psId = $request->input('ps_id');
            $date = Carbon::parse($request->input('date'))->startOfDay();

            $psExists = DB::table('playstations')
                ->where('id_playstation', $psId)
                ->where('id_warnet', $warnetId)
                ->exists();

            if (!$psExists) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'The specified PlayStation does not belong to the given warnet.',
                ], 400);
            }

            $bookedTimes = DB::table('booked_console')
                ->where('id_playstation', $psId)
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
                'id_playstation' => 'required|integer|exists:playstations,id_playstation',
                'id_user' => 'required|integer|exists:users,id_user',
                'start_time' => 'required|date_format:Y-m-d H:i:s',
                'end_time' => 'required|date_format:Y-m-d H:i:s|after:start_time',
                'booking_status' => 'required|string|in:pending,confirmed,cancelled',
                'amount' => 'required|integer',
            ]);

            $startTime = Carbon::parse($request->input('start_time'));
            $endTime = Carbon::parse($request->input('end_time'));
            $currentTime = Carbon::now('Asia/Jakarta'); // 08:44 PM WIB, 09 June 2025

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
                FROM booked_console
                WHERE id_playstation = ?
                AND booking_status IN ('pending', 'confirmed')
                AND (
                    (start_time <= ? AND end_time >= ?) OR
                    (start_time <= ? AND end_time >= ?) OR
                    (start_time >= ? AND end_time <= ?)
                )
            ",
                [
                    $request->input('id_playstation'),
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
            $bookingId = DB::table('booked_console')->insertGetId([
                'id_playstation' => $request->input('id_playstation'),
                'id_user' => $request->input('id_user'),
                'start_time' => $startTime->toDateTimeString(),
                'end_time' => $endTime->toDateTimeString(),
                'booking_status' => $request->input('booking_status'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Simpan transaksi
            DB::table('transactions')->insert([
                'id_user' => $request->input('id_user'),
                'transaction_type' => 'console_booking',
                'reference_id' => $bookingId,
                'amount' => $request->input('amount'),
                'created_at' => now(),
            ]);

            return response()->json([
                'message' => 'PlayStation booking created successfully',
                'booking_id' => $bookingId,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error creating booking: ' . $e->getMessage(),
            ], 500);
        }
    }
}