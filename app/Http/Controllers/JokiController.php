<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JokiController extends Controller
{
    public function index()
    {
        $jokiServices = DB::select("SELECT * FROM jasa_joki");
        return response()->json($jokiServices);
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_user' => 'required|exists:users,id_user',
            'game_name' => 'required|string',
            'package' => 'required|in:Epic Rank,Legend Rank,Mythic Rank',
            'star_count' => 'required|integer|min:1',
            'payment_method' => 'required|in:Gopay,OVO,Dana,Bank',
            'game_id' => 'required|string',
            'email' => 'required|email',
            'whatsapp' => 'required|string',
            'amount' => 'required|integer',
            'promo_code' => 'nullable|string',
        ]);

        $userId = $request->input('id_user');
        $gameName = $request->input('game_name');
        $package = $request->input('package');
        $starCount = $request->input('star_count');
        $paymentMethod = $request->input('payment_method');
        $gameId = $request->input('game_id');
        $email = $request->input('email');
        $whatsapp = $request->input('whatsapp');
        $promoCode = $request->input('promo_code');
        $amount = $request->input('amount');

        // Mulai transaksi database untuk konsistensi
        DB::beginTransaction();

        try {
            // Simpan ke tabel jasa_joki
            DB::insert(
                "INSERT INTO jasa_joki (id_user, game_name, package, star_count, payment_method, game_id, email, whatsapp, promo_code, amount, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')",
                [$userId, $gameName, $package, $starCount, $paymentMethod, $gameId, $email, $whatsapp, $promoCode, $amount]
            );

            // Ambil id_jasa_joki terakhir yang dimasukkan
            $jokiId = DB::getPdo()->lastInsertId();

            // Simpan ke tabel transactions
            DB::insert(
                "INSERT INTO transactions (id_user, transaction_type, reference_id, amount) VALUES (?, ?, ?, ?)",
                [$userId, 'joki', $jokiId, $amount]
            );

            DB::commit();

            return response()->json(['message' => 'Joki service created successfully', 'amount' => $amount, 'joki_id' => $jokiId], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create joki service', 'error' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $jokiService = DB::selectOne("SELECT * FROM jasa_joki WHERE id_jasa_joki = ?", [$id]);
        if (!$jokiService) {
            return response()->json(['message' => 'Joki service not found'], 404);
        }
        return response()->json($jokiService);
    }
}
