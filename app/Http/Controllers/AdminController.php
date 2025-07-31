<?php

namespace App\Http\Controllers;


use App\Enums\Role;
use App\Models\Category;
use App\Models\Thread;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;


class AdminController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (Gate::allows('view-page-admin')) {
            return Inertia::render('Dashboard');
        }
        return Inertia::render('NotFound');
    }

    public function getAllThreadGrowthData(Request $request)
    {
        if (!Gate::allows('view-page-admin')) {
            return Inertia::render('NotFound');
        }

        $year = $request->input('year', now()->year);

        $allThreadsGrowth = Thread::select(
            DB::raw('EXTRACT(MONTH FROM created_at) as month'),
            DB::raw('COUNT(thread_id) as thread_count')
        )
            ->whereYear('created_at', $year)
            ->groupBy(DB::raw('EXTRACT(MONTH FROM created_at)'))
            ->orderBy(DB::raw('EXTRACT(MONTH FROM created_at)'))
            ->get();

        $allThreadsFormatted = $this->formatGrowthData($allThreadsGrowth);

        return response()->json([
            'title' => 'Total threads',
            'data' => $allThreadsFormatted,
            'year' => $year
        ]);
    }

    public function getCategoryThreadGrowthData(Request $request, string $categoryId)
    {
        if (!(Gate::allows('view-page-admin') || Gate::allows('moderator-have-category', $categoryId))) {
            return Inertia::render('NotFound');
        }

        $year = $request->input('year', now()->year);

        $category = Category::find($categoryId);

        if (!$category) {
            return response()->json(['message' => 'Category not found.'], 404);
        }

        $categoryName = $category->title;

        $categoryThreadsGrowth = Thread::select(
            DB::raw('EXTRACT(MONTH FROM created_at) as month'),
            DB::raw('COUNT(thread_id) as thread_count')
        )
            ->where('category_id', $categoryId)
            ->whereYear('created_at', $year)
            ->groupBy(DB::raw('EXTRACT(MONTH FROM created_at)'))
            ->orderBy(DB::raw('EXTRACT(MONTH FROM created_at)'))
            ->get();

        $categoryThreadsFormatted = $this->formatGrowthData($categoryThreadsGrowth);

        return response()->json([
            'title' => 'Threads theo Category: ' . $categoryName,
            'data' => $categoryThreadsFormatted,
            'year' => $year,
            'category_id' => $categoryId
        ]);
    }

    private function generateRandomHexColor(): string
    {
        return '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
    }

    public function getCategoryThreadCountsForPieChart(Request $request)
    {
        if (!Gate::allows('view-page-admin')) {
            return Inertia::render('NotFound');
        }

        $categoryThreadCounts = Category::select(
            'categories.title as category_title',
            DB::raw('COUNT(threads.thread_id) as thread_count')
        )
            ->join('threads', 'categories.category_id', '=', 'threads.category_id')
            ->whereNotNull('categories.parrent_id')
            ->groupBy('categories.category_id', 'categories.title')
            ->orderBy('categories.title')
            ->get();

        $totalThreads = $categoryThreadCounts->sum('thread_count'); // Tính tổng số lượng thread

        $thresholdPercentage = 5; // Ngưỡng phần trăm để gộp (ví dụ: 5%)

        $formattedData = [];
        $otherAmount = 0;

        foreach ($categoryThreadCounts as $item) {
            $percentage = ($item->thread_count / $totalThreads) * 100;
            if ($percentage < $thresholdPercentage) {
                $otherAmount += (int) $item->thread_count;
            } else {
                $formattedData[] = [
                    'nameCategory' => $item->category_title,
                    'amount' => (int) $item->thread_count,
                    'color' => $this->generateRandomHexColor(), // Thêm màu ngẫu nhiên
                ];
            }
        }

        if ($otherAmount > 0) {
            $formattedData[] = [
                'nameCategory' => 'Other',
                'amount' => $otherAmount,
                'color' => $this->generateRandomHexColor(),
            ];
        }

        return response()->json([
            'title' => 'Threads Ratio by Sub-Category',
            'data' => $formattedData,
        ]);
    }

    private function formatGrowthData($growthData)
    {
        $months = range(1, 12);
        $threadsByMonth = [];

        foreach ($months as $month) {
            $threadsByMonth[$month] = 0;
        }

        foreach ($growthData as $data) {
            $threadsByMonth[$data->month] = (int) $data->thread_count;
        }

        return [
            'Thread' => array_values($threadsByMonth)
        ];
    }

    public function getUsersFollowingCategory(Request $request)
    {
        if (!Gate::allows('view-page-admin-or-moderator')) {
            return Inertia::render('NotFound');
        }

        $categoryId = $request->query('category_id');
        if (Gate::allows('view-page-moderator') && !Gate::allows('moderator-have-category', $categoryId)) {
            return Inertia::redner('NotFound');
        }

        $query = User::select(
            'users.user_id',
            'users.name',
            'users.email',
            'users.role'
        )
            ->join('follow_categories', 'users.user_id', '=', 'follow_categories.user_id');

        $query->where('users.role', '!=', Role::ADMIN->value);

        if ($categoryId) {
            $query->where('follow_categories.category_id', $categoryId);

            // Thêm điều kiện loại trừ Admin và Moderator sở hữu category được chỉ định
            $query->whereDoesntHave('decentralizationOfCategories', function ($q) use ($categoryId) {
                $q->where('decentralization_of_categories.category_id', $categoryId)
                    ->whereIn('users.role', [Role::ADMIN->value, Role::MODERATOR->value]); // Sử dụng giá trị chuỗi của enum
            });

        }


        $users = $query->distinct('users.user_id')->get();

        $formattedUsers = $users->map(function ($user) {
            return [
                'id' => $user->user_id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role
            ];
        });

        return response()->json([
            'message' => $categoryId ? 'Users following category ' . $categoryId . ' (excluding admins/mods of this category)' : 'All users who have followed any category (excluding all admins/mods)',
            'users' => $formattedUsers,
        ]);
    }

    public function getTopThreadsByPostCount(Request $request)
    {
        if (!Gate::allows('view-page-admin')) {
            return Inertia::render('NotFound'); // Hoặc trả về response lỗi khác
        }

        $topThreads = Thread::select(
            'threads.thread_id',
            'threads.title',
            'threads.slug',
            'threads.content',
            'threads.user_id',
            'threads.category_id',
            'threads.created_at',
            DB::raw('COUNT(posts.post_id) as posts_count')
        )
            ->leftJoin('posts', 'threads.thread_id', '=', 'posts.thread_id')
            ->groupBy(
                'threads.thread_id',
                'threads.title',
                'threads.slug',
                'threads.content',
                'threads.user_id',
                'threads.category_id',
                'threads.created_at'
            )
            ->orderByDesc('posts_count')
            ->limit(5)
            ->get();

        $formattedThreads = $topThreads->map(function ($thread) {
            return [
                'thread_id' => $thread->thread_id,
                'title' => $thread->title,
                'slug' => $thread->slug,
                'content' => $thread->content,
                'posts_count' => (int) $thread->posts_count,
                'created_at' => $thread->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'message' => 'Top 5 threads by post count',
            'threads' => $formattedThreads,
        ]);
    }

    public function monthlyGrowth(Request $request)
    {
        if (!Gate::allows('view-page-admin')) {
            return Inertia::render('NotFound');
        }

        $year = $request->input('year', Carbon::now()->year);

        $monthlyGrowth = [];

        for ($month = 1; $month <= 12; $month++) {
            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = Carbon::create($year, $month, 1)->endOfMonth();

            $userCount = User::whereBetween('created_at', [$startDate, $endDate])->count();

            $monthName = Carbon::createFromFormat('m', $month)->format('F');

            $monthlyGrowth[] = [
                'month' => $monthName,
                'count' => $userCount,
            ];
        }

        return response()->json($monthlyGrowth);
    }

    public function categoryFollowGrowth(Request $request)
    {
        $year = $request->input('year', Carbon::now()->year);
        $categoryId = $request->input('category_id'); // Lấy category_id nếu có
        if (!Gate::allows('view-page-admin-or-moderator')) {
            return Inertia::render('NotFound');
        }
        if (Gate::allows('view-page-moderator') && !Gate::allows('moderator-have-category', $categoryId)) {
            return Inertia::redner('NotFound');
        }
        if ($categoryId) {
            $categories = Category::where('category_id', $categoryId)->get();
            if ($categories->isEmpty()) {
                return response()->json(['message' => 'Category not found.'], 404);
            }
        } else {
            $categories = Category::whereNotNull('parrent_id')->get();
        }

        $allCategoriesGrowth = [];
        foreach ($categories as $category) {
            $monthlyCategoryFollows = [];
            for ($month = 1; $month <= 12; $month++) {
                $startDate = Carbon::create($year, $month, 1)->startOfMonth();
                $endDate = Carbon::create($year, $month, 1)->endOfMonth();

                $followCount = DB::table('follow_categories')
                    ->where('category_id', $category->category_id)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count();

                $monthName = Carbon::createFromFormat('m', $month)->format('F');

                $monthlyCategoryFollows[] = [
                    'month' => $monthName,
                    'count' => $followCount,
                ];
            }
            $allCategoriesGrowth[] = [
                'category_id' => $category->category_id,
                'title' => $category->title,
                'monthly_follows' => $monthlyCategoryFollows,
            ];
        }

        return response()->json($allCategoriesGrowth);
    }

    public function getGrowthThreadsData(Request $request)
    {
        $year = $request->input('year', Carbon::now()->year);
        $categoryId = $request->input('category_id');
        if (!Gate::allows('view-page-admin-or-moderator')) {
            return Inertia::render('NotFound');
        }
        if (Gate::allows('view-page-moderator') && !Gate::allows('moderator-have-category', $categoryId)) {
            return Inertia::redner('NotFound');
        }
        // Lấy tham số phân trang từ request
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        $startDate = Carbon::create($year, 1, 1)->startOfYear();
        $endDate = Carbon::create($year, 12, 31)->endOfYear();

        $categoriesQuery = Category::query()->select('category_id', 'title');
        if ($categoryId) {
            $categoriesQuery->where('category_id', $categoryId);
        } else {
            $categoriesQuery->whereNotNull('parrent_id');
        }

        $paginatedCategories = $categoriesQuery->paginate($perPage, ['*'], 'page', $page);
        $categories = $paginatedCategories->items();

        if (empty($categories)) {
            return response()->json(['message' => 'No categories found for the given criteria or page.'], 404);
        }

        $categoryIdsToQuery = collect($categories)->pluck('category_id')->toArray();

        $growthData = Thread::select(
            DB::raw('EXTRACT(YEAR FROM created_at) as year'), // Modified for PostgreSQL
            DB::raw('EXTRACT(MONTH FROM created_at) as month'), // Modified for PostgreSQL
            'category_id',
            DB::raw('COUNT(thread_id) as thread_count') // Count threads
        )
            ->whereIn('category_id', $categoryIdsToQuery) // Filter by selected categories
            ->whereBetween('created_at', [$startDate, $endDate]) // Filter by the selected year's time range
            ->groupBy('year', 'month', 'category_id') // Group results by year, month, and category
            ->orderBy('year', 'asc')                   // Order by year ascending
            ->orderBy('month', 'asc')                  // Order by month ascending
            ->get();

        // Prepare an array of month names for chart labels
        $allMonths = [];
        for ($month = 1; $month <= 12; $month++) {
            $allMonths[] = Carbon::createFromFormat('m', $month)->format('F'); // Example: "January", "February"
        }

        $categoryData = [];

        foreach ($categories as $category) {
            $categoryData[$category->category_id] = [
                'name' => $category->title,
                'data' => array_fill_keys(range(1, 12), 0),
                'color' => $this->generateRandomHexColor(),
                'background' => $this->generateRandomHexColor(),
            ];
        }

        foreach ($growthData as $item) {
            $categoryId = $item->category_id;
            $month = $item->month;
            $threadCount = $item->thread_count;

            if (isset($categoryData[$categoryId])) {
                $categoryData[$categoryId]['data'][$month] = $threadCount;
            }
        }

        $formattedData = [];
        foreach ($categoryData as $categoryId => $data) {
            $formattedData[] = [
                'category_id' => $categoryId,
                'category_name' => $data['name'],
                'monthly_counts' => array_values($data['data']),
                'color' => $data['color'],
                'background' => $data['background'],
            ];
        }

        return response()->json([
            'status' => 'success',
            'year' => $year,
            'data' => $formattedData,
            'months' => $allMonths,
            'pagination' => [
                'total' => $paginatedCategories->total(),
                'current_page' => $paginatedCategories->currentPage(),
                'per_page' => $paginatedCategories->perPage(),
                'last_page' => $paginatedCategories->lastPage(),
                'from' => $paginatedCategories->firstItem(),
                'to' => $paginatedCategories->lastItem(),
            ]
        ]);
    }

    public function getAllUser(Request $request)
{
    if (!Gate::allows('view-page-admin')) {
        return Inertia::render('NotFound');
    }
    try {
        $perPage = $request->input('per_page', 5);
        $search = $request->input('search');

        $query = User::query();
        $query->where('role', '!=', Role::ADMIN->value);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $users = $query->with('blockedCategories')->paginate($perPage);
        $usersData = $users->items();

        $formattedUsers = collect($usersData)->map(function ($user) {
            $userArray = $user->toArray();
            $userArray['blocked_categories'] = $user->blockedCategories->map(function ($category) {
                return [
                    'category_id' => $category->category_id,
                    'title' => $category->title,
                    'slug' => $category->slug,
                ];
            })->all();
            return $userArray;
        })->all();

        return response()->json([
            'message' => 'Get list of users successfully',
            'data' => $formattedUsers,
            'pagination' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ]
        ], 200);

    } catch (\Exception $e) {
        \Log::error('Error while getting user list: ' . $e->getMessage());
        return response()->json([
            'message' => 'An error occurred while retrieving the user list.',
            'error' => $e->getMessage()
        ], 500);
    }
}

    public function getUserCategories(Request $request, string $userId)
    {
        try {
            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'message' => 'User not found.'
                ], 404);
            }

            $perPage = $request->input('per_page', 12);
            $search = $request->input('search');
            $query = $user->decentralizationOfCategories();

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%');
                });
            }

            $categories = $query->paginate($perPage);

            return response()->json([
                'message' => 'Get user category list successfully',
                'data' => $categories->items(),
                'pagination' => [
                    'total' => $categories->total(),
                    'per_page' => $categories->perPage(),
                    'current_page' => $categories->currentPage(),
                    'last_page' => $categories->lastPage(),
                    'from' => $categories->firstItem(),
                    'to' => $categories->lastItem(),
                ]
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error when getting user category list: ' . $e->getMessage());
            return response()->json([
                'message' => 'An error occurred while retrieving the user\'s category list.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function grantOrRevokeAccess(Request $request)
    {
        if (!Gate::allows('view-page-admin')) {
            return Inertia::render('NotFound');
        }
        $userId = $request->input('userId', '');
        $categorySlug = $request->input('categorySlug', '');
        $user = User::find($userId);
        $category = Category::where('slug', $categorySlug)->firstOrFail();

        if (!$user || !$category) {
            return response()->json([
                'message' => 'User or Category not found.'
            ], 404);
        }

        try {
            $haveAccess = DB::table('decentralization_of_categories')
                ->where('user_id', $user->user_id)
                ->where('category_id', $category->category_id)
                ->first();

            if ($haveAccess == null) {
                DB::table('decentralization_of_categories')->insert([
                    'user_id' => $user->user_id,
                    'category_id' => $category->category_id
                ]);
            } else {
                DB::table('decentralization_of_categories')
                    ->where('user_id', $user->user_id)
                    ->where('category_id', $category->category_id)
                    ->delete();
            }

            return response()->json(['message' => $haveAccess ? 'Revoke successfully' : 'Grant successfully'], 200);
        } catch (\Exception $e) {
            \Log::error('Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'An error occurred.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function toggleBlockFromCategory(Request $request)
    {
        $categorySlug = $request->input('categorySlug', '');
        $userId = $request->input('userId', '');
        try {
            $category = Category::where('slug', $categorySlug)->firstOrFail();
            $user = User::findOrFail($userId);
            if (Gate::allows('have-blocked-from-category', [$user->user_id, $category->category_id])) {
                DB::table('users_blocked_from_categories')
                ->where('user_id', $user->user_id)
                ->where('category_id', $category->category_id)
                ->delete();
            } else {
                DB::table('users_blocked_from_categories')
                ->insert([
                    'user_id' => $user->user_id,
                    'category_id' => $category->category_id,
                    'created_at' => now(), 
                    'updated_at' => now(),
                ]);
            }
            return response()->json([
                'message' => 'operation successful.'
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'An error occurred.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
