<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BookedConsoleController extends Controller
{
    // Mengambil pemesanan console berdasarkan id_playstation dan tanggal
    public function index(Request $request)
    {
        $playstationId = $request->query('playstation_id');
        $date = $request->query('date');

        $bookings = DB::select("
            SELECT start_time, end_time
            FROM booked_console
            WHERE id_playstation = ?
            AND DATE(start_time) = ?
            AND booking_status IN ('pending', 'confirmed')
        ", [$playstationId, $date]);

        return response()->json($bookings);
    }

    // Menyimpan pemesanan console baru
    public function store(Request $request)
    {
        $request->validate([
            'playstation_id' => 'required|integer|exists:playstations,id_playstation',
            'start_time' => 'required|date_format:Y-m-d H:i:s',
            'end_time' => 'required|date_format:Y-m-d H:i:s|after:start_time',
        ]);

        $startTime = Carbon::parse($request->input('start_time'));
        $endTime = Carbon::parse($request->input('end_time'));

        // Cek apakah ada pemesanan yang bentrok
        $conflict = DB::select("
            SELECT *
            FROM booked_console
            WHERE id_playstation = ?
            AND booking_status IN ('pending', 'confirmed')
            AND (
                (start_time <= ? AND end_time >= ?)
                OR (start_time <= ? AND end_time >= ?)
                OR (start_time >= ? AND end_time <= ?)
            )
        ", [
            $request->input('playstation_id'),
            $startTime, $startTime,
            $endTime, $endTime,
            $startTime, $endTime
        ]);

        if (!empty($conflict)) {
            return response()->json([
                'message' => 'The selected time slot is already booked.',
            ], 409);
        }

        // Simpan pemesanan baru
        $bookingId = DB::table('booked_console')->insertGetId([
            'id_playstation' => $request->input('playstation_id'),
            // 'id_user' => auth()->id(),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'booking_status' => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Simpan transaksi
        DB::table('transactions')->insert([
            // 'id_user' => auth()->id(),
            'transaction_type' => 'console_booking',
            'reference_id' => $bookingId,
            'amount' => $this->calculateAmount($startTime, $endTime),
            'created_at' => now(),
        ]);

        return response()->json([
            'message' => 'Console booking created successfully',
            'booking_id' => $bookingId,
        ], 201);
    }

    private function calculateAmount(Carbon $startTime, Carbon $endTime)
    {
        $hours = $endTime->diffInHours($startTime);
        return $hours * 75000; // Harga per jam untuk console: IDR 75,000
    }
}