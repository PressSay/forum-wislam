<?php

namespace App\Http\Controllers;
use App\Models\Thread;
use DB;
use Inertia\Inertia;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use Log;

class CategoryControler extends Controller
{
    public function index(Request $request)
    {
        try {
            if (!Gate::allows('view-page-admin')) {
                throw new \Exception('Not accessible');
            }

            $noParrent = $request->query('noParrent') ?? false;
            $search = $request->query('search');


            // \DB::enableQueryLog();
            // Start with a Query Builder instance
            $query = Category::query();

            // Apply search filters if search term is provided
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('categories.title', 'like', '%' . $search . '%')
                        ->orWhere('categories.slug', 'like', '%' . $search . '%')
                        ->orWhere('categories.description', 'like', '%' . $search . '%');
                });
            }


            $query->leftJoin('categories AS parrent', 'parrent.category_id', 'categories.parrent_id');

            if ($noParrent) {
                $query->whereNotNull('categories.parrent_id')->select('categories.category_id as category_id', 'categories.title as title', 'categories.description as description', 'categories.slug as slug');
            } else {
                $query->select('categories.category_id as category_id', 'categories.title as title', 'categories.description as description', 'categories.slug as slug', 'parrent.slug as slug_parrent');
            }

            // Apply pagination
            $categories = $query->simplePaginate(12);

            return $request->wantsJson() ? new JsonResponse($categories, 200) : Inertia::render('Admin/Category', [
                'categories' => $categories
            ]);
        } catch (\Exception $e) {
            // Log the error (optional)
            \Log::error('Error fetching categories: ' . $e->getMessage());

            // Return a user-friendly response
            return Inertia::render('NotFound');
        }
    }

    public function subCategories(Request $request, $uuid)
    {
        $query = Category::query();
        try {
            $categories = $query->where('parrent_id', '=', $uuid)->simplePaginate(12)->through(function ($item) {
                $queryThread = Thread::query();
                $topic = $queryThread->where('category_id', $item->category_id)->orderBy('created_at', 'asc')->select('thread_id', 'created_at', 'user_id')->first();
                $lastTopic = ($topic != null) ? [
                    'thread_id' => $topic->thread_id,
                    'created_at' => $topic->created_at->format('Y-m-d H:i'),
                    'user' => User::find($topic->user_id)->only(['user_id', 'name'])
                ] : null;
                return [
                    'category_id' => $item->category_id,
                    'title' => $item->title,
                    'description' => $item->description,
                    'slug' => $item->slug,
                    'topic' => $queryThread->where('category_id', $item->category_id)->count(),
                    'post' => $queryThread->where('category_id', $item->category_id)->join('posts', 'posts.thread_id', '=', 'threads.thread_id')->count('posts.post_id'),
                    'lastTopic' => $lastTopic,
                ];
            });

            return $request->wantsJson() ? new JsonResponse($categories, 200) : Inertia::render('NotFound');
        } catch (\Exception $e) {
            return $request->wantsJson()
                ? new JsonResponse(['error' => 'An error occurred'], 500)
                : Inertia::render('NotFound');
        }
    }

    public function userCategories(Request $request)
    {
        try {
            $search = $request->get('search');

            $query = DB::table('follow_categories')
                ->where('follow_categories.user_id', '=', $request->user()->user_id)
                ->join('categories', 'categories.category_id', '=', 'follow_categories.category_id')
                ->join('users', 'users.user_id', '=', 'follow_categories.user_id');

            if ($search != '') {
                // Sử dụng closure để nhóm các điều kiện OR, đảm bảo chúng không ảnh hưởng đến các điều kiện WHERE khác
                $query->where(function ($q) use ($search) {
                    $q->where('categories.title', 'LIKE', '%' . $search . '%')
                        ->orWhere('categories.slug', 'LIKE', '%' . $search . '%');
                });
            }

            $topics = $query->simplePaginate(12);

            $formattedTopics = $topics->through(function ($item) {
                return [
                    'category_id' => $item->category_id,
                    'title' => $item->title,
                    'description' => $item->description,
                    'slug' => $item->slug
                ];
            });

            return $request->wantsJson() ? new JsonResponse($formattedTopics, 200) : Inertia::render('NotFound');
        } catch (\Exception $e) {
            \Log::error('' . $e->getMessage() . $request->get('search'));
            return Inertia::render('NotFound');
        }
    }

    public function follow(Request $request, $uuid)
    {
        try {
            // Find the thread or fail with 404
            $category = Category::where('slug', $uuid)->firstOrFail();

            // Get the authenticated user
            $user = $request->user();
            if (!$user) {
                return $request->wantsJson()
                    ? new JsonResponse(['error' => 'Unauthorized'], 401)
                    : back()->withErrors(['error' => 'You must be logged in to follow a category']);
            }

            // Check if the user is already following the thread
            $follow = DB::table('follow_categories')
                ->where('user_id', $user->user_id)
                ->where('category_id', $category->category_id)
                ->first();

            if ($follow == null) {
                // Insert follow record
                DB::table('follow_categories')->insert([
                    'user_id' => $user->user_id,
                    'category_id' => $category->category_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                // Delete follow record (unfollow)
                DB::table('follow_categories')
                    ->where('user_id', $user->user_id)
                    ->where('category_id', $category->category_id)
                    ->delete();
            }

            return $request->wantsJson()
                ? new JsonResponse(['message' => $follow ? 'Unfollowed successfully' : 'Followed successfully'], 200)
                : back()->with('status', $follow ? 'unfollowed' : 'followed');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Handle category not found
            \Log::error('Category not found: ' . $uuid);
            return $request->wantsJson()
                ? new JsonResponse(['error' => 'Category not found'], 404)
                : back()->withErrors(['error' => 'Category not found']);
        } catch (\Exception $e) {
            // Handle other unexpected errors
            \Log::error('Follow error: ' . $e->getMessage());
            return $request->wantsJson()
                ? new JsonResponse(['error' => 'An error occurred'], 500)
                : back()->withErrors(['error' => 'An error occurred while processing your request']);
        }
    }

    public function explore(Request $request)
    {
        $query = Category::query()->where(column: 'parrent_id', value: null);

        $parrentCategories = $query->simplePaginate(12);

        return Inertia::render('Member/Category', [
            'categories' => $parrentCategories
        ]);
    }

    public function store(Request $request)
    {
        Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:categories,slug'],
            'slug_parrent' => ['nullable', 'string', 'exists:categories,slug']
        ])->validateWithBag('storeCategory');

        try {
            if (!Gate::allows('update-category')) {
                throw new \Exception('Not accessible');
            }

            $slugParrent = strval($request['slug_parrent']) ?? '';

            // Tạo category
            if ($slugParrent != '') {
                $parrentCategory = Category::where('slug', $slugParrent)->first();
                Category::create([
                    'title' => $request['title'],
                    'description' => $request['description'],
                    'slug' => $request['slug'],
                    'parrent_id' => $parrentCategory->category_id
                ]);
            } else {
                Category::create([
                    'title' => $request['title'],
                    'description' => $request['description'],
                    'slug' => $request['slug'],
                ]);
            }

            // Trả về phản hồi
            return $request->wantsJson()
                ? new JsonResponse('', 200)
                : back()->with('status', 'category-created');
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

    public function update(Request $request, $id)
    {
        // Khởi tạo validator với quy tắc unique cho slug
        Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:App\Models\Category,slug,' . $id],
            'slug_parrent' => ['nullable', 'string', 'max:255', 'exists:categories,slug']
        ])->validateWithBag('updateCategory');

        try {

            if (!Gate::allows('update-category')) {
                throw new \Exception('Not accessible');
            }

            $slugParrent = strval($request['slug_parrent']) ?? '';
            $category = Category::findOrFail($id);
            // Tạo category
            if ($slugParrent != '') {
                $parrentCategory = Category::where('slug', $slugParrent)->first();
                $category->update([
                    'title' => $request['title'],
                    'description' => $request['description'],
                    'slug' => $request['slug'],
                    'parrent_id' => $parrentCategory->category_id
                ]);
            } else {
                $category->update([
                    'title' => $request['title'],
                    'description' => $request['description'],
                    'slug' => $request['slug'],
                    'parrent_id' => null
                ]);
            }
            return $request->wantsJson()
                ? new JsonResponse('', 200)
                : back()->with('status', 'category-updated');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::error('Error updating category ID ' . $id . ': ' . $e->getMessage());
            return $request->wantsJson()
                ? new JsonResponse(['error' => 'Category not found'], 404)
                : back()->withErrors(['error' => 'Category not found']);
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

    public function destroy(Request $request, $id)
    {
        try {
            if (!Gate::allows('update-category')) {
                throw new \Exception('Not accessible');
            }

            $category = Category::findOrFail($id);
            $category->delete();

            return $request->wantsJson()
                ? new JsonResponse('', 200)
                : back()->with('status', 'category-deleted');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::error('Error updating category ID ' . $id . ': ' . $e->getMessage());
            return $request->wantsJson()
                ? new JsonResponse(['error' => 'Category not found'], 404)
                : back()->withErrors(['error' => 'Category not found']);
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