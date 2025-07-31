<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Models\Like;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Tag;
use App\Models\Thread;
use DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\QueryException;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ThreadController extends Controller
{
    public function indexAdmin(Request $request, $slugCategory)
    {
        try {
            if (!Gate::allows('view-page-admin', $request->user())) {
                throw new \Exception('Not accessible');
            }
            $search = $request->query('search');

            $query = Thread::query();
            $query = $query->orderByRaw('CASE WHEN is_pinned = true THEN 0 ELSE 1 END ASC')
                ->orderBy('created_at', 'asc');

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', '%' . $search . '%')
                        ->orWhere('content', 'like', '%' . $search . '%');
                });
            }

            $category = Category::where('slug', $slugCategory)->first();
            $threads = $query->where('category_id', $category->category_id)->simplePaginate(10);
            return Inertia::render("Admin/Thread", [
                'threads' => $threads,
                'category' => $category
            ]);
        } catch (\Exception $e) {
            \Log::error($e->getMessage());
            return Inertia::render("NotFound");
        }
    }

    public function index(Request $request, string $slug)
    {
        try {
            $search = $request->query('search');

            $category = Category::where('slug', $slug)->firstOrFail();

            $query = Thread::query();
            $query = $query->withCount('likes');
            $query = $query->orderByRaw('CASE WHEN is_pinned = true THEN 0 ELSE 1 END ASC')
                ->orderBy('created_at', 'asc');
            $query = $query->where('category_id', $category->category_id);

            // Apply search filters if search term is provided
            if (!empty($search)) {

                // $query->where(function ($q) use ($search) {
                //     $q->where('title', 'like', '%' . $search . '%')
                //         ->orWhere('slug', 'like', '%' . $search . '%')
                //         ->orWhere('description', 'like', '%' . $search . '%');
                // });
            }

            // Apply pagination
            $threads = $query->simplePaginate(10)->through(fn($item) => [
                'thread_id' => $item->thread_id,
                'title' => $item->title,
                'slug' => $item->slug,
                'category' => Category::find($item->category_id)->only(['category_id', 'title']),
                'user' => User::find($item->user_id)->only(['user_id', 'name']),
                'tags' => $item->tags,
                'is_locked' => $item->is_locked,
                'is_pinned' => $item->is_pinned,
                'totalReply' => $item->posts->count(),
                'totalLikes' => $item->likes_count,
                'views' => $item->posts->groupBy('user_id')->count(),
                'lastPost' =>
                    (function () use ($item) {
                        $post = Post::where('thread_id', '=', $item->thread_id)->orderBy('created_at', 'asc')->select('post_id', 'created_at', 'user_id')->first();
                        return ($post) ? [
                            'post_id' => $post->post_id,
                            'created_at' => $post->created_at->format('Y-m-d H:i'),
                            'user' => User::find($post->user_id)->only(['user_id', 'name'])
                        ] : null;
                    }),
            ]);

            $isFollow = ($request->user()) ? DB::table('follow_categories')->where('user_id', '=', $request->user()->user_id)->where('category_id', '=', $category->category_id)->first() != null : false;

            return Inertia::render('Member/Thread', [
                'topic' => $category,
                'isFollow' => $isFollow,
                'threads' => $threads,
            ]);
        } catch (\Exception $e) {
            return Inertia::render('NotFound');
        }
    }

    public function show(Request $request, $slug_category, $slug_thread)
    {
        try {
            $category = Category::where('slug', '=', $slug_category)->firstOrFail();
            $threadModel = Thread::where('slug', '=', $slug_thread)
                ->withCount('likes')->firstOrFail();
            $isLikedByUser = false;
            if ($request->user()) {
                $isLikedByUser = Like::where('user_id', $request->user()->user_id)
                    ->where('thread_id', $threadModel->thread_id)
                    ->exists();
            }

            if ($threadModel) {
                $thread = [
                    'thread_id' => $threadModel->thread_id,
                    'title' => $threadModel->title,
                    'slug' => $threadModel->slug,
                    'content' => $threadModel->content,
                    'user' => (function () use ($threadModel) {
                        $user = User::find($threadModel->user_id);
                        return [
                            'user_id' => $user->user_id,
                            'name' => $user->name,
                            'profile_photo_path' => Storage::disk($this->getDiskStorage())->url($user->profile_photo_path),
                            'created_at' => $user->created_at->format('Y-m-d H:i')
                        ];
                    }),
                    'category' => Category::find($threadModel->category_id)->only(['category_id', 'title']),
                    'tags' => $threadModel->tags,
                    'is_locked' => $threadModel->is_locked,
                    'is_pinned' => $threadModel->is_pinned,
                    'totalLikes' => $threadModel->likes_count,
                    'isLikedByUser' => $isLikedByUser,
                    'updated_at' => $threadModel->updated_at->format('Y-m-d H:i'),
                ];
            } else {
                throw new \Exception('Thread not found');
            }
            if ($threadModel->is_locked) {
                throw new \Exception('Thread not found');
            }

            $isFollow = ($request->user()) ? DB::table('follow_threads')->where('user_id', '=', $request->user()->user_id)->where('thread_id', '=', $threadModel->thread_id)->first() != null : false;

            return Inertia::render('Member/ThreadShow', [
                'topic' => $category,
                'thread' => $thread,
                'isFollow' => $isFollow,
                'pagePost' => (int) $request->query('page') ?? 1
            ]);
        } catch (\Exception $e) {
            \Log::error($e->getMessage());
            return Inertia::render('NotFound');
        }
    }

    public function create(Request $request)
    {
        try {
            $topic = null;
            if ($request->query('slug_topic')) {
                Validator::make($request->all(), [
                    'slug_topic' => ['nullable', 'string', 'max:255', 'exists:categories,slug']
                ])->validateWithBag('createThread');
                $topic = Category::where('slug', '=', $request['slug_topic'])->first() ?: null;
            }

            return Inertia::render(
                'User/Thread',
                ['specific_topic' => $topic]
            );
        } catch (\Exception $e) {
            return Inertia::render(
                'NotFound'
            );
        }
    }

    public function edit(Request $request, $uuid)
    {
        try {
            $threadModel = Thread::find($uuid);

            if (!$threadModel || !Gate::allows('update-thread', $threadModel)) {
                throw new \Exception("Thread not found");
            }

            $query = DB::table('follow_categories');
            $topics = $query->where('follow_categories.user_id', '=', $request->user()->user_id)->join('categories', 'categories.category_id', '=', 'follow_categories.category_id')->join('users', 'users.user_id', '=', 'follow_categories.user_id')->simplePaginate(10)->through(function ($item) {
                return [
                    'category_id' => $item->category_id,
                    'title' => $item->title,
                    'description' => $item->description,
                    'slug' => $item->slug
                ];
            });

            $thread = [
                'thread_id' => $threadModel->thread_id,
                'title' => $threadModel->title,
                'content' => $threadModel->content,
                'slug' => $threadModel->slug,
                'category' => Category::find($threadModel->category_id)->only(['category_id', 'title', 'slug']),
                'tags' => $threadModel->tags->select('name', 'slug')
            ];

            return Inertia::render('User/Thread', ['thread' => $thread, 'topics' => $topics]);
        } catch (\Exception $e) {
            return Inertia::render('NotFound');
        }
    }

    private function generateSlugForSEO(string $text): string
    {
        // 1. Chuyển đổi dấu tiếng Việt thành không dấu (Latinh hóa).
        $text = preg_replace('/[áàảãạăắằẳẵặâấầẩẫậ]/u', 'a', $text);
        $text = preg_replace('/[éèẻẽẹêếềểễệ]/u', 'e', $text);
        $text = preg_replace('/[íìỉĩị]/u', 'i', $text);
        $text = preg_replace('/[óòỏõọôốồổỗộơớờởỡợ]/u', 'o', $text);
        $text = preg_replace('/[úùủũụưứừửữự]/u', 'u', $text);
        $text = preg_replace('/[ýỳỷỹỵ]/u', 'y', $text);
        $text = preg_replace('/[đ]/u', 'd', $text);
        $text = preg_replace('/[^a-z0-9\-\s]/i', '', $text); // Loại bỏ ký tự đặc biệt
        $text = preg_replace('/\s+/', '-', $text); // Thay thế khoảng trắng bằng dấu gạch ngang
        $slug = trim(strtolower($text), '-'); // Chuyển thành chữ thường và loại bỏ dấu gạch ngang ở đầu/cuối
        return $slug;
    }

    private function getUrlImage(string $url)
    {
        return Storage::disk($this->getDiskStorage())->url('images/' . $url);
    }

    private function getDiskStorage()
    {
        return isset($_ENV['VAPOR_ARTIFACT_NAME']) ? 's3' : 'public';
    }

    private function extractImagesFromOps(array $ops, $__currentImage = false): array
    {
        $cleanOps = [];
        $imagesData = [];
        $currentImage = [];
        foreach ($ops as &$op) {
            if (isset($op['insert']['image'])) {
                $imageData = $op['insert']['image'];

                if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $type)) {
                    $image = base64_decode(substr($imageData, strpos($imageData, ',') + 1));
                    $extension = strtolower($type[1]);
                    $filename = Str::uuid() . '.' . $extension;

                    $op['insert']['image'] = $this->getUrlImage($filename);
                    $imagesData[] = [$filename, $image];
                } else {
                    $currentImage[] = $imageData;
                }
            }
            $cleanOps[] = $op;
        }
        if ($__currentImage) {
            return [$cleanOps, $imagesData, $currentImage];
        }
        return [$cleanOps, $imagesData];
    }

    public function getUserThreadsByCategory(Request $request, $userId)
    {
        $user = User::findOrFail($userId);

        $perPage = $request->input('per_page', 15);

        $threadsPaginator = $user->threads()
            ->with(['category', 'tags'])
            ->withCount('posts')
            ->withMax('posts', 'created_at')
            ->paginate($perPage);

        $categorizedThreads = [];

        foreach ($threadsPaginator as $thread) {
            $categoryName = $thread->category->title;
            $categorySlug = $thread->category->slug;

            if (!isset($categorizedThreads[$categoryName])) {
                $categorizedThreads[$categoryName] = [
                    'category_id' => $thread->category->category_id,
                    'category_slug' => $categorySlug,
                    'category_title' => $categoryName,
                    'threads' => []
                ];
            }

            $threadTags = $thread->tags->map(function ($tag) {
                return [
                    'tag_id' => $tag->tag_id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                ];
            })->toArray();

            $lastPostData = null;
            if ($thread->posts_max_created_at) {
                $lastPost = Post::where('thread_id', $thread->thread_id)
                    ->where('created_at', $thread->posts_max_created_at)
                    ->with('user')
                    ->orderBy('post_id', 'desc')
                    ->first();

                if ($lastPost) {
                    $lastPostData = [
                        'post_id' => $lastPost->post_id,
                        'content' => $lastPost->content,
                        'created_at' => $lastPost->created_at->toIso8601String(),
                        'user' => [
                            'user_id' => $lastPost->user->user_id,
                            'name' => $lastPost->user->name,
                        ],
                    ];
                }
            }

            $categorizedThreads[$categoryName]['threads'][] = [
                'thread_id' => $thread->thread_id,
                'title' => $thread->title,
                'slug' => $thread->slug,
                'tags' => $threadTags,
                'lastPost' => $lastPostData,
                'totalReply' => $thread->posts_count > 0 ? $thread->posts_count - 1 : 0,
                'views' => $thread->views ?? 0,
            ];
        }

        $currentPage = $threadsPaginator->currentPage();
        $perPageUsed = $threadsPaginator->perPage();
        $total = $threadsPaginator->total();
        $url = $request->url();

        $paginatedCategorizedThreads = new \Illuminate\Pagination\LengthAwarePaginator(
            array_values($categorizedThreads),
            $total,
            $perPageUsed,
            $currentPage,
            ['path' => $url]
        );

        return response()->json($paginatedCategorizedThreads);
    }

    public function store(Request $request)
    {
        Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'slug_category' => ['required', 'string', 'exists:categories,slug'],
            'content' => ['required', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*.name' => ['nullable', 'string', 'max:255'],
            'tags.*.slug' => ['nullable', 'string', 'max:255'],
        ])->validateWithBag('storeThread');

        $slug = $this->generateSlugForSEO($request->title);
        $request['slug'] = $slug;
        Validator::make($request->all(), [
            'slug' => ['required', 'string', 'unique:threads,slug']
        ])->validateWithBag('storeThread');

        try {
            $category = Category::where('slug', $request->slug_category)->first();
            $followers = DB::table('follow_categories')
                ->where('user_id', '!=', $request->user()->user_id)
                ->get()
                ->all();


            if (Gate::allows('have-blocked-from-category', [$request->user()->user_id, $category->category_id])) {
                throw new \Exception('Have no right.');
            }

            $content = json_decode($request->input('content'), true);
            $request['content'] = $content['ops'][0]['insert'] == "\n" ? '' : $content['ops'][0]['insert'];
            if ($request['content'] == '') {
                throw new \Exception('Content is empty.');
            }

            DB::beginTransaction();
            [$cleanOps, $imagesData] = $this->extractImagesFromOps($content['ops']);

            $cleanedDelta = ['ops' => array_values($cleanOps)];
            $contentString = json_encode($cleanedDelta);


            $thread = Thread::create([
                'title' => $request->title,
                'content' => $contentString,
                'slug' => $slug,
                'category_id' => $category->category_id,
                'user_id' => $request->user()->user_id
            ]);
            $tags = [];
            foreach ($request->tags as $tag) {
                $tag = Tag::where('slug', '=', $tag['slug'])->select('tag_id')->firstOrFail();
                $thread_tag = [
                    'tag_id' => $tag->tag_id,
                    'thread_id' => $thread->thread_id
                ];
                $tags[] = $thread_tag;
            }
            DB::table('thread_tag')->insert($tags);
            foreach ($imagesData as [$filename, $binary]) {
                Storage::disk($this->getDiskStorage())->put("images/$filename", contents: $binary);
                Image::create([
                    'url' => $filename,
                    'thread_id' => $thread->thread_id
                ]);
            }
            foreach ($followers as $follower) {
                Notification::create([
                    'user_id' => $follower->user_id,
                    'content' => 'New Thread On Category ' . $category->title,
                    'thread_id' => $thread->thread_id
                ]);
            }

            DB::commit();

        } catch (\Exception $e) {
            if ($e->getMessage() == 'Content is empty.') {
                Validator::make(
                    $request->all(),
                    ['content' => 'required', 'string']
                )->validateWithBag('storeThread');
            }
            DB::rollBack();
            \Log::error('Create Thread ' . $e->getMessage());
            return $request->wantsJson()
                ? new JsonResponse(['error' => 'An error occurred'], 500)
                : back()->withErrors(['error' => 'An error occurred while processing your request']);
        }

        return $request->wantsJson()
            ? new JsonResponse('', 200)
            : back()->with('status', 'thread-created');
    }

    public function update(Request $request, $uuid)
    {
        if ($request->has('pin') || $request->has('lock')) {
            Validator::make($request->all(), [
                'pin' => ['nullable', 'boolean'],
                'lock' => ['nullable', 'boolean']
            ])->validateWithBag('updateThread');
        } else {
            Validator::make($request->all(), [
                'title' => ['required', 'string', 'max:255'],
                'slug_category' => ['required', 'string', 'exists:categories,slug'],
                'content' => ['required', 'string'],
                'tags' => ['nullable', 'array'],
                'tags.*.name' => ['nullable', 'string', 'max:255'],
                'tags.*.slug' => ['nullable', 'string', 'max:255'],
            ])->validateWithBag('updateThread');

            $slug = $this->generateSlugForSEO($request->title);
            $request['slug'] = $slug;
            Validator::make($request->all(), [
                'slug' => ['required', 'string', 'unique:App\Models\Thread,slug,' . $uuid]
            ])->validateWithBag('updateThread');
        }

        try {
            $content = json_decode($request->input('content'), true);
            $request['content'] = $content['ops'][0]['insert'] == "\n" ? '' : $content['ops'][0]['insert'];
            if ($request['content'] == '') {
                throw new \Exception('Content is empty.');
            }

            $thread = Thread::findOrFail($uuid);
            if (!Gate::allows('update-thread', $thread)) {
                throw new \Exception('Thread not found');
            }
            if ($request->has('pin') || $request->has('lock')) {
                $thread->update([
                    'is_locked' => $request['lock'] ?? false,
                    'is_pinned' => $request['pin'] ?? false
                ]);
                return $request->wantsJson()
                    ? new JsonResponse('', 200)
                    : back()->with('status', 'thread-updated');
            }

            DB::beginTransaction();
            $category = Category::where('slug', $request->slug_category)->firstOrFail();

            $imagesThread = Image::where('thread_id', $uuid)->get()->map(
                fn($item) =>
                $this->getUrlImage($item->url)
            )->toArray();
            [$cleanOps, $imagesData, $imagesThreadCurrent] = $this->extractImagesFromOps($content['ops'], true);
            $cleanedDelta = ['ops' => array_values($cleanOps)];
            $contentString = json_encode($cleanedDelta);
            $imagesDeleted = array_diff($imagesThread, $imagesThreadCurrent);

            $thread->update([
                'title' => $request->title,
                'content' => $contentString,
                'slug' => $slug,
                'category_id' => $category->category_id,
                'user_id' => $request->user()->user_id
            ]);

            $tags = [];
            foreach ($request->tags as $tag) {
                $tag = Tag::where('slug', '=', $tag['slug'])->select('tag_id')->firstOrFail();
                $thread_tag = [
                    'tag_id' => $tag->tag_id,
                    'thread_id' => $thread->thread_id
                ];
                $tags[] = $thread_tag;
            }
            if (!empty($tags) && count($tags) > 0) {
                DB::table('thread_tag')->where('thread_id', '=', $thread->thread_id)->delete();
                DB::table('thread_tag')->insert($tags);
            }
            //delete old image
            foreach ($imagesDeleted as $imageDeleted) {
                $arrimageDeleted = explode('/', $imageDeleted);
                $imageDeleted = end($arrimageDeleted);
                Image::where('url', $imageDeleted)->delete();
            }
            // storage new image
            foreach ($imagesData as [$filename, $binary]) {
                Storage::disk($this->getDiskStorage())->put("images/$filename", contents: $binary);
                Image::create([
                    'url' => $filename,
                    'thread_id' => $uuid
                ]);
            }
            DB::commit();
        } catch (\Exception $e) {
            if ($e->getMessage() == 'Content is empty.') {
                Validator::make(
                    $request->all(),
                    ['content' => 'required', 'string']
                )->validateWithBag('updateThread');
            }
            DB::rollBack();
            \Log::error($e->getMessage());
            return $request->wantsJson()
                ? new JsonResponse(['error' => 'An error occurred'], 500)
                : back()->withErrors(['error' => 'An error occurred while processing your request']);
        }

        return $request->wantsJson()
            ? new JsonResponse('', 200)
            : back()->with('status', 'thread-updated');
    }

    public function destroy(Request $request, $uuid)
    {
        try {
            $thread = Thread::findOrFail($uuid);
            if (!Gate::allows('update-thread', $thread)) {
                throw new \Exception("Thread not found");
            }
            $thread->delete();
            return $request->wantsJson()
                ? new JsonResponse('', 200)
                : back()->with('status', 'thread-deleted');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $request->wantsJson()
                ? new JsonResponse(['error' => 'Thread not found'], 404)
                : back()->withErrors(['error' => 'Thread not found']);
        } catch (QueryException $e) {

            return $request->wantsJson()
                ? new JsonResponse(['error' => 'Unprocessable Entity'], 422)
                : back()->withErrors(['error' => 'Unprocessable Entity']);
        } catch (\Exception $e) {

            return $request->wantsJson()
                ? new JsonResponse(['error' => 'An error occurred'], 500)
                : back()->withErrors(['error' => 'An error occurred while processing your request']);
        }
    }
}