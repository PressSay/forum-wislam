<?php

namespace App\Http\Controllers;

use App\Models\Example;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function countUnread(Request $request)
    {
        try {
            $userId = $request->user()->user_id;

            if (!$userId) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                    'errors' => 'User not logged in.'
                ], 401);
            }

            $unreadNotificationsCount = Notification::where('user_id', $userId)
                ->where('is_read', '=', false)
                ->count();

            return response()->json([
                'success' => true,
                'message' => 'Unread notification count retrieved successfully.',
                'data' => [
                    'unread_notifications' => $unreadNotificationsCount,
                ]
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error fetching unread notification count: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving unread notification count.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function index(Request $request)
    {
        try {
            $userId = $request->user()->user_id;

            if (!$userId) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                    'errors' => 'User not logged in.'
                ], 401);
            }

            $perPage = $request->input('per_page', 12);

            $notifications = Notification::where('user_id', $userId)
                ->orderBy('is_read', 'asc')
                ->orderBy('created_at', 'desc')
                ->with([
                    'thread' => function ($query) {
                        $query->with('category:category_id,slug');
                    },
                    'post' => function ($query) {
                        $query->with(['thread' => function ($threadQuery) {
                            $threadQuery->with('category:category_id,slug');
                        }]);
                    }
                ])
                ->paginate($perPage);

            $notifications->getCollection()->transform(function ($notification) {
                $link = null;
                if ($notification->thread) {
                    $categorySlug = $notification->thread->category->slug ?? 'default-category';
                    $threadSlug = $notification->thread->slug;
                    $link = "/explore/{$categorySlug}/{$threadSlug}";
                } elseif ($notification->post) {
                    $threadSlug = $notification->post->thread->slug ?? 'default-thread';
                    $categorySlug = $notification->post->thread->category->slug ?? 'default-category';
                    $postId = $notification->post->post_id;
                    $link = "/explore/{$categorySlug}/{$threadSlug}#post-{$postId}";
                }

                $notification->link = $link;
                return $notification;
            });

            return response()->json([
                'success' => true,
                'message' => 'Notifications retrieved successfully.',
                'data' => $notifications
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error fetching notifications with links: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving notifications.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function markAsRead(Request $request)
    {
        $request->validate([
            'notification_ids' => 'required|array',
            'notification_ids.*' => 'uuid|exists:notifications,notification_id',
        ]);

        try {
            $userId = $request->user()->user_id;

            if (!$userId) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                    'errors' => 'User not logged in.'
                ], 401);
            }

            $notificationIds = $request->input('notification_ids');

            $updatedCount = Notification::where('user_id', $userId)
                ->whereIn('notification_id', $notificationIds)
                ->update(['is_read' => true]);

            if ($updatedCount > 0) {
                return response()->json([
                    'success' => true,
                    'message' => "$updatedCount notifications marked as read successfully.",
                    'data' => ['updated_count' => $updatedCount]
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No notifications found or updated for the current user with the provided IDs.'
                ], 404);
            }

        } catch (\Exception $e) {
            \Log::error('Error marking notifications as read: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while marking notifications as read.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'notification_ids' => 'required|array',
            'notification_ids.*' => 'uuid|exists:notifications,notification_id',
        ]);

        try {
            $userId = $request->user()->user_id;

            if (!$userId) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                    'errors' => 'User not logged in.'
                ], 401);
            }

            $notificationIds = $request->input('notification_ids');

            $deletedCount = Notification::where('user_id', $userId)
                ->whereIn('notification_id', $notificationIds)
                ->delete();

            if ($deletedCount > 0) {
                return response()->json([
                    'success' => true,
                    'message' => "$deletedCount notifications deleted successfully.",
                    'data' => ['deleted_count' => $deletedCount]
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No notifications found or deleted for the current user with the provided IDs.'
                ], 404);
            }

        } catch (\Exception $e) {
            \Log::error('Error deleting notifications: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting notifications.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
