<?php

namespace App\Http\Controllers;

use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use App\Notifications\SendNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

class NotificationController extends Controller
{

    public function getNotification(Request $request)
    {
        $request->validate([
            'filter' => 'nullable|in:read,unread',
        ]);

        $notifications = User::find(Auth::id())->notifications()
            ->when($request->filter, function ($query) use ($request) {
                if ($request->filter == 'read') {
                    return $query->where('read_at', '!=', null);
                } else {
                    return $query->where('read_at', null);
                }
            })
            ->paginate(10)->through(function ($notification) {
                return [
                    'id' => $notification->id,
                    'title' => $notification->data['title'],
                    'body' => $notification->data['body'],
                    'name' => $notification->notifiable->name,
                    'created_at' => $notification->created_at,
                    'read_at' => $notification->read_at,
                ];
            });

        return response()->json([
            'count' => User::find(Auth::id())->notifications->count(),
            'unread_count' => User::find(Auth::id())->unreadNotifications->count(),
            'read_count' => User::find(Auth::id())->notifications()->where('read_at', '!=', null)->count(),
            'data' => $notifications,
        ]);
    }

    public function readNotifications()
    {
        $notifications = User::find(Auth::id())->notifications;

        $notifications->markAsRead();

        return response()->json([
            'success' => true,
            'status' => 200
        ]);
    }


    public function readNotification($id)
    {
        $notification = User::find(Auth::id())->notifications()->where('id', $id)->first();

        if ($notification) {
            $notification->markAsRead();
            return response()->json([
                'success' => true,
                'status' => 200
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
                'status' => 404
            ]);
        }
    }

    public function testNotification(Request $request)
    {
        $request->validate([
            'title' => 'required',
            'body' => 'required',
        ]);
        try {
            $data = [
                'title' => $request->title,
                'body' => [
                    'message' => $request->body,
                    // 'url' => 'https://erp-home.netlify.app',
                    // 'type' => 'Test',
                ],
            ];

            $this->notify($data, User::find(Auth::id()));


            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }



    public function notify($data, $user)
    {
        Notification::send($user, new SendNotification($data));

        // if (!empty($user->fcm_token)) {
        //     $this->sendFcmNotification(
        //         $data['title'],
        //         $data['body']['message'],
        //         $user->fcm_token
        //     );
        // }
    }
}
