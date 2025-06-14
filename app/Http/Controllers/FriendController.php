<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FriendController extends Controller
{
    /**
     * Get the authenticated user's friends.
     */
    public function index(Request $request)
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        try {
            // Fetch friends
            $friends = DB::select(
                "SELECT u.id_user, u.name
                 FROM friends f
                 JOIN users u ON (u.id_user = f.id_user1 OR u.id_user = f.id_user2)
                 WHERE (f.id_user1 = ? OR f.id_user2 = ?) AND u.id_user != ?
                 ORDER BY u.name ASC",
                [$userId, $userId, $userId]
            );

            // Fetch last message and unread count for each friend
            foreach ($friends as $friend) {
                // Last message
                $lastMessage = DB::selectOne(
                    "SELECT message, created_at
                     FROM private_chats
                     WHERE (id_sender = ? AND id_receiver = ?)
                        OR (id_sender = ? AND id_receiver = ?)
                     ORDER BY created_at DESC
                     LIMIT 1",
                    [$userId, $friend->id_user, $friend->id_user, $userId]
                );

                // Unread message count (only 'sent' status)
                $unreadCount = DB::selectOne(
                    "SELECT COUNT(*) as count
                     FROM private_chats
                     WHERE id_receiver = ? AND id_sender = ? AND status = 'sent'",
                    [$userId, $friend->id_user]
                );

                $friend->last_message = $lastMessage ? $lastMessage->message : null;
                $friend->last_message_time = $lastMessage ? $lastMessage->created_at : null;
                $friend->unread_count = $unreadCount->count;
            }

      

            return response()->json(['data' => $friends], 200);
        } catch (\Exception $e) {
       
            return response()->json(['message' => 'Server error'], 500);
        }
    }
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_user1' => 'required|integer|exists:users,id_user',
            'id_user2' => 'required|integer|exists:users,id_user|different:id_user1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $idUser1 = min($request->id_user1, $request->id_user2);
        $idUser2 = max($request->id_user1, $request->id_user2);

        $existingFriendship = DB::selectOne(
            "SELECT id_friendship
             FROM friends
             WHERE id_user1 = ? AND id_user2 = ?",
            [$idUser1, $idUser2]
        );

        if ($existingFriendship) {
            return response()->json(['message' => 'Friendship already exists'], 409);
        }

        DB::insert(
            "INSERT INTO friends (id_user1, id_user2, created_at, updated_at)
             VALUES (?, ?, NOW(), NOW())",
            [$idUser1, $idUser2]
        );

        return response()->json(['message' => 'Friendship created'], 201);
    }
}
