<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WarnetController extends Controller
{
    public function index()
    {
        // Raw SQL untuk mengambil data warnet beserta total PC
        $warnets = DB::select("
            SELECT w.id_warnet, w.warnet_name, w.address, w.stars,
                   COUNT(p.id_pc) as total_pcs
            FROM warnets w
            LEFT JOIN pcs p ON w.id_warnet = p.id_warnet
            GROUP BY w.id_warnet, w.warnet_name, w.address, w.stars
        ");

        // Konversi ke array untuk respons JSON
        $formattedWarnets = array_map(function ($warnet) {
            return [
                'id_warnet' => $warnet->id_warnet,
                'warnet_name' => $warnet->warnet_name,
                'address' => $warnet->address,
                'stars' => $warnet->stars,
                'total_pcs' => (int)$warnet->total_pcs,
            ];
        }, $warnets);

        return response()->json($formattedWarnets);
    }
}