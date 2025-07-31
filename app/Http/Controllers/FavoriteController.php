<?php

namespace App\Http\Controllers;

use App\Models\Thread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;



class FavoriteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        // Get the base URL for images from your storage disk
        $baseUrl = Storage::disk($this->getDiskStorage())->url('images/');

        $threads = DB::table('follow_threads as ft')
            ->where('ft.user_id', '=', $user->user_id)
            ->join('threads as th', 'ft.thread_id', '=', 'th.thread_id')
            ->leftJoin('categories as c', 'th.category_id', '=', 'c.category_id')
            ->select(
                'th.thread_id',
                'th.title',
                'th.content',
                'th.slug as slug_thread',
                'c.title as category_title',
                'c.slug as slug_category',
                'th.created_at',
                'th.updated_at'
            )
            ->simplePaginate(12);

        // Loop through each thread to attach its images
        // We need to transform the collection to modify the items
        $threads->getCollection()->transform(function ($thread) use ($baseUrl) {
            // Select all images associated with the current thread_id
            $images = DB::table('images')
                ->where('thread_id', $thread->thread_id)
                ->select('image_id', 'url') // Select specific image columns
                ->get(); // Get all images for this thread

            // Transform the images to include the full URL
            $images->transform(function ($image) use ($baseUrl) {
                $image->full_url = $baseUrl . $image->url;
                return $image;
            });

            // Assign the images to the thread object
            $thread->images = $images;

            return $thread; // Return the modified thread
        });

        return Inertia::render("User/Favorite", ['favorites' => $threads]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $uuid)
    {
        try {
            // Find the thread or fail with 404
            $thread = Thread::findOrFail($uuid);

            // Get the authenticated user
            $user = $request->user();
            if (!$user) {
                return $request->wantsJson()
                    ? new JsonResponse(['error' => 'Unauthorized'], 401)
                    : back()->withErrors(['error' => 'You must be logged in to follow a thread']);
            }

            // Check if the user is already following the thread
            $follow = DB::table('follow_threads')
                ->where('user_id', $user->user_id)
                ->where('thread_id', $thread->thread_id)
                ->first();

            if ($follow == null) {
                // Insert follow record
                DB::table('follow_threads')->insert([
                    'user_id' => $user->user_id,
                    'thread_id' => $thread->thread_id,
                ]);
            } else {
                // Delete follow record (unfollow)
                DB::table('follow_threads')
                    ->where('user_id', $user->user_id)
                    ->where('thread_id', $thread->thread_id)
                    ->delete();
            }

            return $request->wantsJson()
                ? new JsonResponse(['message' => $follow ? 'Unfollowed successfully' : 'Followed successfully'], 200)
                : back()->with('status', $follow ? 'unfollowed' : 'followed');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Handle thread not found
            \Log::error('Thread not found: ' . $uuid);
            return $request->wantsJson()
                ? new JsonResponse(['error' => 'Thread not found'], 404)
                : back()->withErrors(['error' => 'Thread not found']);
        } catch (\Exception $e) {
            // Handle other unexpected errors
            \Log::error('Follow error: ' . $e->getMessage());
            return $request->wantsJson()
                ? new JsonResponse(['error' => 'An error occurred'], 500)
                : back()->withErrors(['error' => 'An error occurred while processing your request']);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $uui)
    {
        try {
            DB::table('follow_threads')->where('user_id', '=', $request->user()->user_id)->where('thread_id', '=', $uui)->delete();
        } catch (\Exception $e) {
            return ($request->wantsJson()) ? new JsonResponse(['error' => 'An error occurred'], 500) : back()->with('error', $e->getMessage());
        }
        return ($request->wantsJson()) ? new JsonResponse(['success' => true]) : back()->with('success', '');
    }

    private function getDiskStorage()
    {
        return isset($_ENV['VAPOR_ARTIFACT_NAME']) ? 's3' : 'public';
    }
}
