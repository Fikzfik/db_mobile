<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PcController extends Controller
{
    public function index(Request $request)
    {
        $warnetId = $request->query('warnet_id');
        $pcs = DB::select("
            SELECT id_pc, pc_name
            FROM pcs
            WHERE id_warnet = ?
        ", [$warnetId]);

        return response()->json($pcs);
    }
}