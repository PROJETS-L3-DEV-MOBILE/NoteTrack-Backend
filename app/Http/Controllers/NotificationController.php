<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'status'        => 'success',
            'notifications' => $notifications,
            'unread_count'  => $user->unreadNotifications()->count(),
        ], 200);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->where('id', $id)
            ->firstOrFail();

        $notification->markAsRead();

        return response()->json([
            'status'       => 'success',
            'message'      => 'Notification marked as read.',
            'notification' => $notification,
        ], 200);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'status'  => 'success',
            'message' => 'All notifications marked as read.',
        ], 200);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->where('id', $id)
            ->firstOrFail();

        $notification->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Notification deleted successfully.',
        ], 200);
    }

    public function destroyAll(Request $request): JsonResponse
    {
        $request->user()->notifications()->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Deleted all notifications.',
        ], 200);
    }
}
