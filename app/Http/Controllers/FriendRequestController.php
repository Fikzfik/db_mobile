<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FriendRequestController extends Controller
{
    /**
     * Send a friend request.
     */
    public function store(Request $request)
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'id_receiver' => [
                'required',
                'integer',
                'exists:users,id_user',
                function ($attribute, $value, $fail) use ($userId) {
                    if ($value == $userId) {
                        $fail('Cannot send friend request to yourself');
                    }
                },
            ],
        ], [
            'id_receiver.exists' => 'User not found',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $receiverId = $request->id_receiver;

        try {
            $existingRequest = DB::selectOne(
                "SELECT id_request
                 FROM friend_requests
                 WHERE (id_sender = ? AND id_receiver = ?)
                    OR (id_sender = ? AND id_receiver = ?)
                    AND status = 'pending'",
                [$userId, $receiverId, $receiverId, $userId]
            );

            if ($existingRequest) {
                return response()->json(['message' => 'Friend request already pending'], 409);
            }

            DB::insert(
                "INSERT INTO friend_requests (id_sender, id_receiver, status, created_at, updated_at)
                 VALUES (?, ?, 'pending', NOW(), NOW())",
                [$userId, $receiverId]
            );

            return response()->json(['message' => 'Friend request sent'], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get pending friend requests for the authenticated user.
     */
    public function index(Request $request)
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $status = $request->query('status', 'pending');

        try {
            $requests = DB::select(
                "SELECT fr.id_request, fr.id_sender, u.name AS sender_name
                 FROM friend_requests fr
                 JOIN users u ON fr.id_sender = u.id_user
                 WHERE fr.id_receiver = ? AND fr.status = ?",
                [$userId, $status]
            );

            return response()->json($requests, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Server error'], 500);
        }
    }

    /**
     * Update friend request status (accept/reject).
     */
    public function update(Request $request, $id)
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:accepted,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $requestExists = DB::selectOne(
                "SELECT id_request, id_sender
                 FROM friend_requests
                 WHERE id_request = ? AND id_receiver = ?",
                [$id, $userId]
            );

            if (!$requestExists) {
                return response()->json(['message' => 'Friend request not found'], 404);
            }

            DB::beginTransaction();

            DB::update(
                "UPDATE friend_requests
                 SET status = ?, updated_at = NOW()
                 WHERE id_request = ?",
                [$request->status, $id]
            );

            if ($request->status == 'accepted') {
                $idUser1 = min($requestExists->id_sender, $userId);
                $idUser2 = max($requestExists->id_sender, $userId);

                $existingFriendship = DB::selectOne(
                    "SELECT id_friendship
                     FROM friends
                     WHERE id_user1 = ? AND id_user2 = ?",
                    [$idUser1, $idUser2]
                );

                if (!$existingFriendship) {
                    DB::insert(
                        "INSERT INTO friends (id_user1, id_user2, created_at)
                         VALUES (?, ?, NOW())",
                        [$idUser1, $idUser2]
                    );
                }
            }

            DB::commit();
            return response()->json(['message' => 'Friend request updated'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Server error: ' . $e->getMessage()], 500);
        }
    }
}