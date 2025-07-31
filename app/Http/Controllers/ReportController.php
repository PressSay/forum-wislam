<?php

namespace App\Http\Controllers;

use App\Enums\Status;
use App\Models\Report;
use App\Models\Thread;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function indexForUser(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return new JsonResponse([
                'message' => 'User not authenticated.'
            ], 401);
        }

        try {
            $query = Report::where('user_id', $user->user_id);

            if ($request->has('status') && in_array($request->input('status'), array_map(fn($case) => $case->value, Status::cases()))) {
                $query->where('status', $request->input('status'));
            }

            if ($request->has('thread_search') && !empty($request->input('thread_search'))) {
                $searchTerm = $request->input('thread_search');
                $query->whereHas('thread', function ($q) use ($searchTerm) {
                    $q->where('title', 'like', '%' . $searchTerm . '%');
                });
            }
            $query->with(['thread']);
            $reports = $query->latest()->get();

            return new JsonResponse([
                'message' => 'User reports retrieved successfully!',
                'reports' => $reports
            ], 200);
        } catch (\Exception $e) {
            return new JsonResponse([
                'message' => 'Failed to retrieve user reports.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function indexForAdmin(Request $request)
    {
        if (Gate::denies('view-page-admin')) {
            return new JsonResponse([
                'message' => 'You do not have permission to view all reports.'
            ], 403);
        }

        try {
            $query = Report::query();

            if ($request->has('status') && in_array($request->input('status'), array_map(fn($case) => $case->value, Status::cases()))) {
                $query->where('status', $request->input('status'));
            }

            if ($request->has('user_id') && !empty($request->input('user_id'))) {
                $query->where('user_id', $request->input('user_id'));
            }

            if ($request->has('thread_id') && !empty($request->input('thread_id'))) {
                $query->where('thread_id', $request->input('thread_id'));
            }

            if ($request->has('user_search') && !empty($request->input('user_search'))) {
                $searchTerm = $request->input('user_search');
                $query->whereHas('user', function ($q) use ($searchTerm) {
                    $q->where('name', 'like', '%' . $searchTerm . '%')
                        ->orWhere('email', 'like', '%' . $searchTerm . '%');
                });
            }

            if ($request->has('thread_search') && !empty($request->input('thread_search'))) {
                $searchTerm = $request->input('thread_search');
                $query->whereHas('thread', function ($q) use ($searchTerm) {
                    $q->where('title', 'like', '%' . $searchTerm . '%');
                });
            }

            $query->with(['user', 'thread']);
            $query->latest();

            $reports = $query->paginate(15);

            return new JsonResponse([
                'message' => 'All reports retrieved successfully!',
                'reports' => $reports
            ], 200);
        } catch (\Exception $e) {
            return new JsonResponse([
                'message' => 'Failed to retrieve reports.',
                'error' => $e->getMessage()
            ], 500); // 500 Internal Server Error
        }
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $threadId = $request->input('thread_id');
        $reason = $request->input('reason');

        if (empty($threadId) || empty($reason)) {
            return new JsonResponse([
                'message' => 'Thread ID and reason are required.'
            ], 400);
        }

        $thread = Thread::find($threadId);
        if (!$thread) {
            return new JsonResponse([
                'message' => 'Thread not found.'
            ], 404);
        }

        if (!$user) {
            return new JsonResponse([
                'message' => 'User not authenticated.'
            ], 401);
        }

        \Log::info('Authenticated User:', ['user' => $user]);
        \Log::info('User ID being used:', ['user_id' => $user->user_id]);

        $existingReport = Report::where('user_id', $user->user_id)
            ->where('thread_id', $threadId)
            ->first();

        if ($existingReport) {
            return new JsonResponse([
                'message' => 'You have already reported this thread.'
            ], 409);
        }

        try {
            $report = Report::create([
                'user_id' => $user->user_id,
                'thread_id' => $threadId,
                'reason' => $reason,
                'status' => Status::PENDING->value,
            ]);

            return new JsonResponse([
                'message' => 'Report submitted successfully!',
                'report' => $report
            ], 201);
        } catch (\Exception $e) {
            \Log::error($e);
            return new JsonResponse([
                'message' => 'Failed to submit report.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request)
    {

        if (Gate::denies('view-page-admin')) {
            return new JsonResponse([
                'message' => 'You do not have permission to update reports.'
            ], 403);
        }

        $threadId = $request->input('thread_id');
        $userId = $request->input('user_id');
        $newStatus = $request->input('status');
        $reasonForUpdate = $request->input('reason_for_update', null);

        if (empty($threadId) || empty($userId) || empty($newStatus)) {
            return new JsonResponse([
                'message' => 'User ID, Thread ID, and Status are required for update.'
            ], 400); // 400 Bad Request
        }

        $validStatuses = array_map(fn($case) => $case->value, Status::cases());
        if (!in_array($newStatus, $validStatuses)) {
            return new JsonResponse([
                'message' => 'Invalid status provided. Valid statuses are: ' . implode(', ', $validStatuses) . '.'
            ], 400); // 400 Bad Request
        }

        $report = Report::where('user_id', $userId)
            ->where('thread_id', $threadId)
            ->first();

        if (!$report) {
            return new JsonResponse([
                'message' => 'Report not found.'
            ], 404);
        }

        try {
            $report->status = $newStatus;
            $report->save();

            return new JsonResponse([
                'message' => 'Report updated successfully!',
                'report' => $report
            ], 200); // 200 OK
        } catch (\Exception $e) {
            return new JsonResponse([
                'message' => 'Failed to update report.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request)
    {
        $userId = $request->input('user_id');
        $threadId = $request->input('thread_id');

        if ((Gate::denies('view-page-admin') && $request->user()->user_id != $userId)) {
            return new JsonResponse([
                'message' => 'You do not have permission to delete reports.'
            ], 403); // 403 Forbidden
        }
        

        if (empty($userId) || empty($threadId)) {
            return new JsonResponse([
                'message' => 'User ID and Thread ID are required to delete a report.'
            ], 400); // 400 Bad Request
        }

        $report = Report::where('user_id', $userId)
            ->where('thread_id', $threadId)
            ->first();

        if (!$report) {
            return new JsonResponse([
                'message' => 'Report not found.'
            ], 404); // 404 Not Found
        }

        try {
            $report->delete();

            return new JsonResponse([
                'message' => 'Report deleted successfully!'
            ], 200);
        } catch (\Exception $e) {
            return new JsonResponse([
                'message' => 'Failed to delete report.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
