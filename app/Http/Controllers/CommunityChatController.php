<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CommunityChatController extends Controller
{
    /**
     * Get community chat messages for a specific game.
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'game_id' => 'required|integer|exists:games,id_game',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $messages = DB::select(
            "SELECT cc.id_chat, cc.id_user, u.name AS username, cc.message, cc.created_at
             FROM community_chats cc
             JOIN users u ON cc.id_user = u.id_user
             WHERE cc.id_game = ?
             ORDER BY cc.created_at ASC",
            [$request->game_id]
        );

        return response()->json($messages, 200);
    }

    /**
     * Send a community chat message.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_game' => 'required|integer|exists:games,id_game',
            'message' => 'required|string',
            'message_type' => 'required|in:text,image,file',
            'status' => 'required|in:sent,delivered,read',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::insert(
            "INSERT INTO community_chats (id_user, id_game, message, message_type, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
            [
                Auth::id(),
                $request->id_game,
                $request->message,
                $request->message_type,
                $request->status
            ]
        );

        $insertedId = DB::getPdo()->lastInsertId();

        return response()->json([
            'id_chat' => $insertedId,
            'message' => 'Message sent',
        ], 201);
    }
}