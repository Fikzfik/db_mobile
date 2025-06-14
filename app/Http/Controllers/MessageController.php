<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    /**
     * Fetch private messages between the authenticated user and another user.
     */
    public function index(Request $request)
    {
        $userId = Auth::id();
        Log::info('Fetching messages', [
            'user_id' => $userId,
            'other_user_id' => $request->query('other_user_id'),
            'request_headers' => $request->headers->all(),
        ]);

        if (!$userId) {
            Log::error('Unauthenticated access to messages');
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'other_user_id' => 'required|integer|exists:users,id_user',
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed for fetching messages', [
                'errors' => $validator->errors()->toArray(),
            ]);
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $otherUserId = $request->query('other_user_id');

        try {
            $messages = DB::select(
                "SELECT id_private_chat, id_sender, id_receiver, message, message_type, status, created_at
                 FROM private_chats
                 WHERE (id_sender = ? AND id_receiver = ?)
                    OR (id_sender = ? AND id_receiver = ?)
                 ORDER BY created_at ASC",
                [$userId, $otherUserId, $otherUserId, $userId]
            );

            // Update status to 'read' for received messages
            $updated = DB::update(
                "UPDATE private_chats
                 SET status = 'read', updated_at = NOW()
                 WHERE id_receiver = ? AND id_sender = ? AND status IN ('sent', 'delivered')",
                [$userId, $otherUserId]
            );

            Log::info('Messages fetched successfully', [
                'user_id' => $userId,
                'other_user_id' => $otherUserId,
                'message_count' => count($messages),
                'updated_read' => $updated,
            ]);

            return response()->json($messages, 200);
        } catch (\Exception $e) {
            Log::error('Error fetching messages', [
                'user_id' => $userId,
                'other_user_id' => $otherUserId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Store a new private message.
     */
    public function store(Request $request)
    {
        $userId = Auth::id();
        Log::info('Storing message', [
            'user_id' => $userId,
            'request_data' => $request->all(),
            'request_headers' => $request->headers->all(),
        ]);

        if (!$userId) {
            Log::error('Unauthenticated access to store message');
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'id_receiver' => 'required|integer|exists:users,id_user',
            'message' => 'required|string|max:1000',
            'message_type' => 'in:text,image,file',
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed for storing message', [
                'errors' => $validator->errors()->toArray(),
            ]);
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $idReceiver = $request->id_receiver;
        $message = $request->message;
        $messageType = $request->message_type ?? 'text';

        try {
            $isFriend = DB::selectOne(
                "SELECT 1
                 FROM friends
                 WHERE (id_user1 = ? AND id_user2 = ?)
                    OR (id_user1 = ? AND id_user2 = ?)",
                [$userId, $idReceiver, $idReceiver, $userId]
            );

            if (!$isFriend) {
                Log::warning('Attempt to message non-friend', [
                    'user_id' => $userId,
                    'receiver_id' => $idReceiver,
                ]);
                return response()->json(['message' => 'You can only message friends'], 403);
            }

            DB::insert(
                "INSERT INTO private_chats (id_sender, id_receiver, message, message_type, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 'sent', NOW(), NOW())",
                [$userId, $idReceiver, $message, $messageType]
            );

            Log::info('Message stored successfully', [
                'user_id' => $userId,
                'receiver_id' => $idReceiver,
                'message' => $message,
            ]);

            return response()->json(['message' => 'Message sent'], 201);
        } catch (\Exception $e) {
            Log::error('Error storing message', [
                'user_id' => $userId,
                'receiver_id' => $idReceiver,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Server error: ' . $e->getMessage()], 500);
        }
    }
}
