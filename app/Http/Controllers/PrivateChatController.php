<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PrivateChatController extends Controller
{
    /**
     * Get private chat messages between the authenticated user and another user.
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'other_user_id' => 'required|integer|exists:users,id_user',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId = Auth::id();
        $otherUserId = $request->other_user_id;

        $messages = DB::select(
            "SELECT pc.id_private_chat, pc.id_sender, u.name AS sender_name, pc.message, pc.created_at
             FROM private_chats pc
             JOIN users u ON pc.id_sender = u.id_user
             WHERE (pc.id_sender = ? AND pc.id_receiver = ?)
                OR (pc.id_sender = ? AND pc.id_receiver = ?)
             ORDER BY pc.created_at ASC",
            [$userId, $otherUserId, $otherUserId, $userId]
        );

        return response()->json($messages, 200);
    }

    /**
     * Send a private chat message.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_receiver' => 'required|integer|exists:users,id_user',
            'message' => 'required|string',
            'message_type' => 'required|in:text,image,file',
            'status' => 'required|in:sent,delivered,read',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $id = DB::insert(
            "INSERT INTO private_chats (id_sender, id_receiver, message, message_type, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
            [
                Auth::id(),
                $request->id_receiver,
                $request->message,
                $request->message_type,
                $request->status
            ]
        );

        $insertedId = DB::getPdo()->lastInsertId();

        return response()->json([
            'id_private_chat' => $insertedId,
            'message' => 'Message sent',
        ], 201);
    }
}