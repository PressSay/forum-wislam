<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Post;
use App\Models\Thread;
use DB;
use Gate;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    private function getDiskStorage()
    {
        return isset($_ENV['VAPOR_ARTIFACT_NAME']) ? 's3' : 'public';
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, $threadId)
    {
        try {
            $thread = Thread::findOrFail($threadId);
            $query = Post::query();
            $query->where('thread_id', '=', $thread->thread_id);
            $query->orderBy('created_at', 'desc');
            $query = $query->simplePaginate(12);
            
            $posts = $query->through(function ($item) {
                $parrentModel = Post::find($item->parrent_id);
                $parrent = ($parrentModel) ? [
                    'post_id' => $parrentModel->post_id,
                    'content' => $parrentModel->content,
                    'user' => [
                        'user_id' => optional($parrentModel->user)->user_id,
                        'name' => optional($parrentModel->user)->name,
                        'profile_photo_path' => $parrentModel->user && $parrentModel->user->profile_photo_path
                            ? Storage::disk($this->getDiskStorage())->url($parrentModel->user->profile_photo_path)
                            : null,
                        'created_at' => optional($parrentModel->user?->created_at)->format('Y-m-d H:i'),
                    ],
                    'created_at' => $parrentModel->created_at->format('Y-m-d H:i'),
                    'showMore' => 1,
                ] : null;
                return [
                    'parrent' => $parrent,
                    'post_id' => $item->post_id,
                    'content' => $item->content,
                    'user' => [
                        'user_id' => optional($item->user)->user_id,
                        'name' => optional($item->user)->name,
                        'profile_photo_path' => $item->user && $item->user->profile_photo_path
                            ? Storage::disk($this->getDiskStorage())->url($item->user->profile_photo_path)
                            : null,
                        'created_at' => optional($item->user?->created_at)->format('Y-m-d H:i'),
                    ],
                    'created_at' => $item->created_at->format('Y-m-d H:i'),
                    'showMore' => 1,
                ];
            });

            return $request->wantsJson() ? new JsonResponse($posts, 200) : Inertia::render('NotFound');
        } catch (\Exception $e) {
            \Log::error('Post Index' . $e->getMessage());
            return $request->wantsJson() ? new JsonResponse(['error' => 'An error occurred'], 500) : Inertia::render('NotFound');
        }
    }

    public function store(Request $request, $threadId)
    {
        Validator::make($request->all(), [
            'parrent_id' => ['nullable', 'exists:posts,post_id'],
            'content' => ['required'],
        ])->validateWithBag('storePost');

        try {
            $content = json_decode($request->input('content'), true);
            $firstContent = $content['ops'][0]['insert'] == "\n" ? '' : $content['ops'][0]['insert'];
            $followers = DB::table('follow_threads')->get()->all();

            if ($firstContent == '') {
                throw new \Exception('Content is empty.');
            }

            DB::beginTransaction();
            $thread = Thread::findOrFail($threadId);
            $data = [
                'content' => json_encode($content),
                'thread_id' => $thread->thread_id,
                'user_id' => $request->user()->user_id
            ];
            if ($request->input('parrent_id') != '') {
                $parrent = Post::findOrFail($request->parrent_id);
                $data['parrent_id'] = $parrent->post_id;
            }
            $post = Post::create($data);
            foreach ($followers as $follower) {
                Notification::create([
                    'user_id' => $follower->user_id,
                    'content' => 'New Post In Thread ' . $thread->title,
                    'post_id' => $post->post_id
                ]);
            }
            // dd(DB::getQueryLog());
            DB::commit();
        } catch (\Exception $e) {
            if ($e->getMessage() == 'Content is empty.') {
                Validator::make(
                    ["content" => $firstContent],
                    ['content' => 'required', 'string']
                )->validateWithBag('storePost');
            }
            DB::rollBack();
            \Log::error('Create Post ' . $e->getMessage());
            return $request->wantsJson()
                ? new JsonResponse(['error' => 'An error occurred'], 500)
                : back()->withErrors(['error' => 'An error occurred while processing your request']);
        }

        return $request->wantsJson()
            ? new JsonResponse('', 200)
            : back()->with('status', 'post-created');
    }

    public function update(Request $request, $threadId, $postId)
    {
        Validator::make($request->all(), [
            'content' => ['required', 'string'],
        ]);
        try {
            DB::beginTransaction();
            $post = Post::findOrFail($postId);
            if ($request->user()->user_id != $post->user_id) {
                throw new \Exception('no Authority');
            }
            Thread::findOrFail($threadId);
            $post->update($request->all());
            DB::commit();
        } catch (\Exception $e) {
            if (!Gate::allows('update-post', $post)) {
                throw new \Exception("Post not found");
            }
            DB::rollBack();
            \Log::error('Update Post ' . $e->getMessage());
            return $request->wantsJson() ? new JsonResponse(['error' => 'An error occurred'], 500) : back()->withErrors(['error' => 'An error occured while processing your request']);
        }
        return $request->wantsJson() ? new JsonResponse('', 200)
            : back()->with('status', 'post-updated');
    }

    public function destroy(Request $request, $threadId, $postId)
    {
        try {
            $thread = Thread::findOrFail($threadId);
            $post = Post::findOrFail($postId);
            if (!Gate::allows('update-post', $post)) {
                throw new \Exception("Post not found");
            }
            $post->delete();
            
            $query = Post::query();
            $query->where('thread_id', '=', $thread->thread_id);
            $query->orderBy('created_at', 'desc');
            $query = $query->simplePaginate(12);
            
            $posts = $query->through(function ($item) {
                $parrentModel = Post::find($item->parrent_id);
                $parrent = ($parrentModel) ? [
                    'post_id' => $parrentModel->post_id,
                    'content' => $parrentModel->content,
                    'user' => [
                        'user_id' => optional($parrentModel->user)->user_id,
                        'name' => optional($parrentModel->user)->name,
                        'profile_photo_path' => $parrentModel->user && $parrentModel->user->profile_photo_path
                            ? Storage::disk($this->getDiskStorage())->url($parrentModel->user->profile_photo_path)
                            : null,
                        'created_at' => optional($parrentModel->user?->created_at)->format('Y-m-d H:i'),
                    ],
                    'created_at' => $parrentModel->created_at->format('Y-m-d H:i'),
                    'showMore' => 1,
                ] : null;
                return [
                    'parrent' => $parrent,
                    'post_id' => $item->post_id,
                    'content' => $item->content,
                    'user' => [
                        'user_id' => optional($item->user)->user_id,
                        'name' => optional($item->user)->name,
                        'profile_photo_path' => $item->user && $item->user->profile_photo_path
                            ? Storage::disk($this->getDiskStorage())->url($item->user->profile_photo_path)
                            : null,
                        'created_at' => optional($item->user?->created_at)->format('Y-m-d H:i'),
                    ],
                    'created_at' => $item->created_at->format('Y-m-d H:i'),
                    'showMore' => 1,
                ];
            });

            return $request->wantsJson()
                ? new JsonResponse($posts, 200)
                : back()->with('status', 'post-deleted');
        } catch (\Exception $e) {

            return $request->wantsJson()
                ? new JsonResponse(['error' => 'An error occurred'], 500)
                : back()->withErrors(['error' => 'An error occurred while processing your request']);
        }
    }
}
