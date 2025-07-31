<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TagController;
use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\PageController;
use App\Http\Controllers\CategoryControler;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\ProfileInformationController;
use App\Http\Controllers\ThreadController;
use Laravel\Fortify\Features;
use Illuminate\Http\JsonResponse;

Route::get('/explore', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->name('welcome');

Route::get('/', [CategoryControler::class, 'explore'])->name('categories.explore');

Route::controller(CategoryControler::class)->prefix('categories')->group(function () {
    Route::get('/sub-categories/{uuid}', 'subCategories')->name('categories.subCategories');
    Route::get('/user', 'userCategories')->name('categories.user');
})->middleware('throttle:120,1');

Route::controller(ThreadController::class)->prefix('explore')->group(function () {
    Route::get('{slug}', 'index')->name('threads.index');
    Route::get('{slug_category}/{slug_thread}', 'show')->name('threads.detail');
});

Route::controller(ThreadController::class)->prefix('threads/user')->group(function () {
    Route::get('{userId}', 'getUserThreadsByCategory')->name('threads.getUserThreadsByCategory');
});

Route::controller(PostController::class)->prefix('posts')->group(function () {
    Route::get('/{threadId}', 'index')->name('posts.index');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {

    Route::get('/user', function (Request $request) {
        return new JsonResponse($request->user());
    })->name('api.user');

    Route::controller(AdminController::class)->prefix('admin/dashboard')->group(
        function () {
            Route::get('/', 'index')
                ->name('dashboard');
            Route::get('/chart/categories/threads/{categoryId}', 'getCategoryThreadGrowthData')
                ->name('dashboard.getCategoryThreadGrowthData');
            Route::get('/chart/categories/threads', 'getAllThreadGrowthData')
                ->name('dashboard.getAllThreadGrowthData');
            Route::get('/chart/categories/threads-pie', 'getCategoryThreadCountsForPieChart')
                ->name('dashboard.getCategoryThreadCountsForPieChart');
            Route::get('/chart/categories/follow-growth', 'categoryFollowGrowth')
                ->name('dashboard.categoryFollowGrowth');
            Route::get('/chart/categories/growth-threads-data', 'getGrowthThreadsData')
                ->name('dashboard.getGrowthThreadsData');
            Route::get('/chart/users', 'monthlyGrowth')
                ->name('dashboard.userMonthlyGrowth');
            Route::get('/categories/users/following', 'getUsersFollowingCategory')
                ->name('dashboard.getUsersFollowingCategory');
            Route::get('/categories/users/decentralization-of-categories', 'getUserCategories')
                ->name('dashboard.getUserCategories');
            Route::post('/categories/users/grant-or-revokeAccess', 'grantOrRevokeAccess')
                ->name('dashboard.grantOrRevokeAccess');
            Route::post('/categories/users/toggle-block-from-category', 'toggleBlockFromCategory')
                ->name('dashboard.toggleBlockFromCategory');
            Route::get('/threads/top', 'getTopThreadsByPostCount')
                ->name('dashboard.getTopThreadsbyPostCount');
            Route::get('/users', 'getAllUser')
                ->name('dashboard.getAllUser');
        }
    );

    Route::controller(TagController::class)->prefix('admin/tags')->group(function () {
        Route::get('/', 'all')->name('tags.all');
        Route::get('/index', 'index')->name('tags.index');
        Route::post('/index', 'store')->name('tags.store');
        Route::put('/index/{uuid}', 'update')->name('tags.update');
        Route::delete('/index/{uuid}', 'destroy')->name('tags.destroy');
    });

    Route::controller(CategoryControler::class)->prefix('admin/categories')->group(function () {
        Route::get('/', 'index')->name('categories.index');
        Route::post('/', 'store')->name('categories.store');
        Route::put('/{id}', 'update')->name('categories.update');
        Route::delete('/{id}', 'destroy')->name('categories.destroy');
    });

    Route::controller(CategoryControler::class)->prefix('topic')->group(function () {
        Route::post('{slug}', action: 'follow')->name('categories.follow');
    });

    Route::controller(ThreadController::class)->prefix('admin')->group(function () {
        Route::get('{slugCategory}/threads', 'indexAdmin')->name('threads.indexAdmin');
    });

    Route::controller(ThreadController::class)->prefix('threads')->group(function () {
        Route::get('/', 'create')->name('threads.create');
        Route::get('{uuid}', 'edit')->name('threads.edit');
        Route::post('/', 'store')->name('threads.store');
        Route::put('{uuid}', 'update')->name('threads.update');
        Route::delete('{uuid}', 'destroy')->name('threads.destroy');
    })->middleware('throttle:10,1'); // Allow up to 10 requests per minute

    Route::controller(LikeController::class)->prefix('likes')->group(
        function () {
            Route::post('/threads/{uuid}', 'like')->name('likes.threads');
        }
    );

    Route::controller(PostController::class)->prefix('posts')->group(function () {
        Route::post('/{threadId}', 'store')->name('posts.store');
        Route::put('/{threadId}/{postId}', 'update')->name('posts.update');
        Route::delete('/{threadId}/{postId}', 'destroy')->name('posts.destroy');
    });

    Route::controller(ConversationController::class)->prefix('conversations')->group(function () {
        Route::get('/', 'index')->name('conversations.index');
        Route::get('/{uuid}', 'show')->name('conversations.show');
        Route::put('/{uuid}', 'update')->name('conversations.update');
        Route::post('/', 'store')->name('conversations.store');
    });

    Route::controller(ConversationController::class)->prefix('conversation-user')->group(function () {
        Route::get('/', 'users')->name('conversations.users');
        Route::get('/authority/{uuid}/access', 'access')->name('conversations.access');
        Route::post('/block-toggle', 'toggleBlock')->name('conversations.toggleBlock');
        Route::get('/mark-read/{uuid}', 'markRead')->name('conversations.markRead');
    });

    Route::controller(FavoriteController::class)->prefix('favorites')->group(function () {
        Route::get('/', 'index')->name('favorites.index');
        Route::post("{uuid}", 'store')->name('threads.follow');
        Route::delete('/{uuid}', 'destroy')->name('favorites.destroy');
    });

    Route::controller(ReportController::class)->prefix('reports')->group(function () {
        Route::get('/member', 'indexForUser')->name('reports.indexForUser');
        Route::get('/admin', 'indexForAdmin')->name('reports.indexForAdmin');
        Route::post('/member', 'store')->name('reporst.store');
        Route::put('/admin', 'update')->name('reports.update');
        Route::delete('/member', 'destroy')->name('reporst.destroy');
    });

    Route::prefix('notifications')->group(function () {
        Route::get('/count', [NotificationController::class, 'countUnread'])
            ->name('notification.countUnread');
        Route::get('/', [NotificationController::class, 'index'])
            ->name('notifications.index');
        Route::post('/mark-as-read', [NotificationController::class, 'markAsRead'])
            ->name('notifications.markAsRead');
        Route::delete('/', [NotificationController::class, 'destroy'])
            ->name('notifications.destroy');
    });
});

Route::group(['middleware' => config('jetstream.middleware', ['web'])], function () {
    $authMiddleware = config('jetstream.guard')
        ? 'auth:' . config('jetstream.guard')
        : 'auth';

    $authSessionMiddleware = config('jetstream.auth_session', false)
        ? config('jetstream.auth_session')
        : null;

    Route::group(['middleware' => array_values(array_filter([$authMiddleware, $authSessionMiddleware]))], function () {

        Route::get('/user/profile-detail', [UserProfileController::class, 'detail'])
            ->name('profile.detail');

    });

});

if (Features::enabled(Features::updateProfileInformation())) {
    Route::get('/user/profile-information-image', [ProfileInformationController::class, 'getUploaded'])->Middleware([config('fortify.auth_middleware', 'auth') . ':' . config('fortify.guard')])
        ->name('user-profile-information.history');
    ;

    Route::put('/user/profile-information-image', [ProfileInformationController::class, 'updateImage'])
        ->Middleware([config('fortify.auth_middleware', 'auth') . ':' . config('fortify.guard')])
        ->name('user-profile-information.updateImage');

    Route::delete('/user/profile-information-image/{id}', [ProfileInformationController::class, 'destroyImage'])
        ->middleware([config('fortify.auth_middleware', 'auth') . ':' . config('fortify.guard')])->name('user-profile-information.deleteImage');
}

Route::fallback([PageController::class, 'notfound']);