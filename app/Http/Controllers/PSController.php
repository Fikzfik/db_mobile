<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PsController extends Controller
{
    public function index(Request $request)
    {
        $warnetId = $request->input('warnet_id');

        // Raw SQL query to fetch PlayStations, optionally filtered by warnet_id
        $sql = "SELECT id_playstation, ps_name, id_warnet FROM playstations";
        $params = [];
        
        if ($warnetId) {
            $sql .= " WHERE id_warnet = :warnet_id";
            $params['warnet_id'] = $warnetId;
        }

        $playstations = DB::select($sql, $params);

        return response()->json($playstations);
    }

    public function show($id)
    {
        // Raw SQL query to fetch a specific PlayStation by id_playstation
        $playstation = DB::selectOne("
            SELECT id_playstation, ps_name, id_warnet 
            FROM playstations 
            WHERE id_playstation = :id
        ", ['id' => $id]);

        if (!$playstation) {
            return response()->json(['message' => 'Playstation not found'], 404);
        }

        return response()->json($playstation);
    }
}