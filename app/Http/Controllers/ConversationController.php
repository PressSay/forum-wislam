<?php

namespace App\Http\Controllers;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Example;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Validator;


class ConversationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('User/Conversation');
    }

    public function users(Request $request)
    {
        try {
            $currentUser = $request->user();
            $search = '';
            if ($request->has('search')) {
                $search = $request->get('search');
            }

            $otherUsers = DB::table('conversation_user as cu1')
                ->join('conversation_user as cu2', 'cu1.conversation_id', '=', 'cu2.conversation_id')
                ->join('users as u', 'cu2.user_id', '=', 'u.user_id')
                ->where('cu1.user_id', '=', $currentUser->user_id)
                ->where('cu2.user_id', '!=', $currentUser->user_id)
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('messages as m')
                        ->whereRaw('m.conversation_id = cu2.conversation_id');
                })
                ->leftJoinSub(function ($query) {
                    $query->from('messages')
                        ->select('conversation_id', DB::raw('MAX(id) as last_message_id'))
                        ->groupBy('conversation_id');
                }, 'latest_messages', 'cu2.conversation_id', '=', 'latest_messages.conversation_id')
                ->leftJoin('messages as m', 'latest_messages.last_message_id', '=', 'm.id');

            // --- Thêm điều kiện tìm kiếm tại đây ---
            if (!empty($search)) {
                $otherUsers->where('u.name', 'like', '%' . $search . '%');
            }
            // ------------------------------------

            $otherUsers = $otherUsers->select(
                'u.user_id',
                'u.name',
                'cu2.conversation_id',
                'm.content as last_message_content',
                DB::raw("CASE WHEN u.profile_photo_path IS NOT NULL THEN '" . Storage::disk($this->getDiskStorage())->url('') . "' || u.profile_photo_path ELSE NULL END as profile_photo_path")
            )
                ->distinct()
                ->simplePaginate(12); // hoặc ->paginate(12)

            $selfConversationId = DB::table('conversation_user')
                ->select('conversation_id')
                ->where('user_id', $currentUser->user_id)
                ->groupBy('conversation_id')
                ->havingRaw('COUNT(*) = 2') // chính là 2 dòng user_id giống nhau
                ->limit(1)
                ->value('conversation_id'); // chỉ lấy ID

            $selfUser = DB::table('users as u')
                ->where('u.user_id', $currentUser->user_id)
                ->select(
                    'u.user_id',
                    'u.name',
                    DB::raw("'" . $selfConversationId . "' as conversation_id"),
                    DB::raw('NULL as last_message_content'),
                    DB::raw("CASE WHEN u.profile_photo_path IS NOT NULL THEN '" . Storage::disk($this->getDiskStorage())->url('') . "' || u.profile_photo_path ELSE NULL END as profile_photo_path")
                )
                ->first();


            return $request->wantsJson() ? new JsonResponse(['otherUser' => $otherUsers, 'selfUser' => $selfUser], 200) : Inertia::render('NotFound');
        } catch (\Exception $e) {
            return Inertia::render('NotFound');
        }
    }

    public function access(Request $request, $uuid)
    {
        try {
            $user = $request->user();

            $conversation = Conversation::findOrFail($uuid);

            // Check if user is in conversation
            $isParticipant = DB::table('conversation_user')
                ->where('conversation_id', $uuid)
                ->where('user_id', $user->user_id)
                ->exists();

            // Check if you are blocked by someone in the chat
            $otherUserId = DB::table('conversation_user')
                ->where('conversation_id', $uuid)
                ->where('user_id', '!=', $user->user_id)
                ->value('user_id');

            $isBlocked = DB::table('blocked_users')
                ->where('user_id', $otherUserId)
                ->where('blocked_user_id', $user->user_id)
                ->exists();

            return response()->json([
                'authorized' => $isParticipant && !$isBlocked
            ]);
        } catch (\Exception $e) {
            return response()->json(['authorized' => false], 403);
        }
    }

    public function toggleBlock(Request $request)
    {
        Validator::make($request->all(), [
            'blocked_user_id' => ['required', 'uuid', 'exists:users,user_id'],
        ])->validateWithBag('toggleBlockConversation');

        try {
            $currentUser = $request->user();

            if ($currentUser->user_id === $request->blocked_user_id) {
                return response()->json(['error' => 'You can\'t block yourself.'], 400);
            }

            $existing = DB::table('blocked_users')
                ->where('user_id', $currentUser->user_id)
                ->where('blocked_user_id', $request->blocked_user_id)
                ->first();

            if ($existing) {
                // Blocked → unblock
                DB::table('blocked_users')
                    ->where('user_id', $currentUser->user_id)
                    ->where('blocked_user_id', $request->blocked_user_id)
                    ->delete();

                return response()->json(['message' => 'User unblocked.'], 200);
            } else {
                // Not blocked → blocked
                DB::table('blocked_users')->insert([
                    'user_id' => $currentUser->user_id,
                    'blocked_user_id' => $request->blocked_user_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return response()->json(['message' => 'User blocked.'], 200);
            }
        } catch (\Exception $e) {
            \Log::error('Toggle block error: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred.'], 500);
        }
    }

    public function show(Request $request, $uuid)
    {
        try {
            $allowShow = false;
            $currentUser = $request->user();

            $conversation = Conversation::findOrFail($uuid);
            foreach ($conversation->users as $user) {
                if ($currentUser->user_id == $user->user_id) {
                    $allowShow = true;
                    break;
                }
            }
            if (!$allowShow) {
                throw new \Exception('Not accessible');
            }
            $page = request()->get('page', 1);
            $perPage = 12;
            $offset = ($page - 1) * $perPage;

            $sub = $conversation->messages()
                ->orderByDesc('created_at')
                ->offset($offset)
                ->limit($perPage);

            $messages = DB::table(DB::raw("({$sub->toSql()}) as recent_messages"))
                ->mergeBindings($sub->getQuery()->getQuery())
                ->join('users as u', 'recent_messages.user_id', '=', 'u.user_id')
                ->select('u.user_id', 'u.name', 'recent_messages.content', 'is_read')
                ->selectRaw(
                    'CASE WHEN u.profile_photo_path IS NOT NULL THEN ? || u.profile_photo_path ELSE NULL END as profile_photo_path',
                    [Storage::disk($this->getDiskStorage())->url('')]
                )
                ->selectRaw("TO_CHAR(recent_messages.created_at, 'DD-MM-YYYY HH24:MI') as created_at")
                ->selectRaw("TO_CHAR(recent_messages.updated_at, 'DD-MM-YYYY HH24:MI') as updated_at")
                ->orderBy('recent_messages.created_at', 'asc') // đảo ngược lại
                ->get();

            $user = $conversation->users()->where('users.user_id', '!=', $currentUser->user_id)->select('users.user_id', 'users.name', 'users.profile_photo_path')
                ->selectRaw('CASE WHEN users.profile_photo_path IS NOT NULL THEN ? || users.profile_photo_path ELSE NULL END as profile_photo_path', [Storage::disk($this->getDiskStorage())->url('')])
                ->first();

            if (!$user) {
                $user = $conversation->users()->where('users.user_id', '=', $currentUser->user_id)->select('users.user_id', 'users.name', 'users.profile_photo_path')
                    ->selectRaw('CASE WHEN users.profile_photo_path IS NOT NULL THEN ? || users.profile_photo_path ELSE NULL END as profile_photo_path', [Storage::disk($this->getDiskStorage())->url('')])
                    ->first();
            }

            $userBlock = DB::table('blocked_users')->where('user_id', '=', $currentUser->user_id)->where('blocked_user_id', '=', $user->user_id)->select('blocked_user_id')->first();

            $isBlocked = $userBlock != null;

            return $request->wantsJson() ? new JsonResponse($messages, 200) : Inertia::render('User/Conversation', [
                'messages' => $messages,
                'conversation' => $conversation,
                'user' => $user,
                'isBlocked' => $isBlocked
            ]);
        } catch (\Exception $e) {
            \Log::error($e->getMessage());
            return Inertia::render('NotFound');
        }
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Validator::make($request->all(), [
            'user_id' => ['required', 'string'],
            'conversation_id' => ['nullable', 'string']
        ])->validateWithBag('storeConversation');

        if ($request->has('conversation_id') && $request['conversation_id'] != '') {
            Validator::make($request->all(), [
                'content' => ['required', 'string'],
            ])->validateWithBag('storeConversation');
        }

        try {
            Redis::connect('127.0.0.1', 6379);

            $currentUser = $request->user();
            $user = User::findOrFail($request['user_id']);

            $selfConversation = DB::table('conversation_user')
                ->select('conversation_id')
                ->where('user_id', $user->user_id)
                ->groupBy('conversation_id')
                ->havingRaw('COUNT(*) = 2') // Phải có 2 bản ghi cùng user_id
                ->first();

            $sharedConversation = DB::table('conversation_user')
                ->select('conversation_id')
                ->whereIn('user_id', [$currentUser->user_id, $user->user_id])
                ->groupBy('conversation_id')
                ->havingRaw('COUNT(DISTINCT user_id) = 2') // Cả hai user phải có mặt
                ->first();

            $conversation = null;

            DB::beginTransaction();
            if ($sharedConversation || $selfConversation) {
                $conversation = Conversation::find(($user->user_id != $currentUser->user_id) ? $sharedConversation->conversation_id : $selfConversation->conversation_id);

                if (Gate::allows('user-blocked-by-user', [$user, $currentUser])) {
                    throw new \Exception('Not accessible');
                }
                if ($request['content'] != '') {
                    // Kiểm tra xem người nhận có đang trong hội thoại không
                    $isRecipientOnline = Redis::sismember('conversation:' . $conversation->conversation_id . ':online', $user->user_id);

                    Message::create([
                        'user_id' => $currentUser->user_id,
                        'content' => $request['content'],
                        'conversation_id' => $conversation->conversation_id,
                        'is_read' => $user->user_id == $currentUser->user_id || $isRecipientOnline, // Đánh dấu đã đọc nếu người nhận online
                    ]);

                    // Đẩy tin nhắn vào Redis Stream
                    Redis::xadd('chat_messages', '*', [
                        'user_id' => $currentUser->user_id,
                        'content' => $request['content'],
                        'conversation_id' => $conversation->conversation_id,
                        'created_at' => now()->toDateTimeString(),
                        'profile_photo_path' => Storage::disk($this->getDiskStorage())->url($currentUser->profile_photo_path),
                        'is_read' => $user->user_id == $currentUser->user_id || $isRecipientOnline, // Cập nhật is_read
                    ]);
                }
            } else {
                $conversation = Conversation::create([]);
                DB::table('conversation_user')->insert([
                    'user_id' => $currentUser->user_id,
                    'conversation_id' => $conversation->conversation_id,
                ]);
                DB::table('conversation_user')->insert([
                    'user_id' => $user->user_id,
                    'conversation_id' => $conversation->conversation_id,
                ]);
                if (Gate::allows('user-blocked-by-user', [$user, $currentUser])) {
                    throw new \Exception('Not accessible');
                }
                if ($request['content'] != '') {
                    // Kiểm tra xem người nhận có đang trong hội thoại không
                    $isRecipientOnline = Redis::sismember('conversation:' . $conversation->conversation_id . ':online', $user->user_id);

                    Message::create([
                        'user_id' => $currentUser->user_id,
                        'content' => $request['content'],
                        'conversation_id' => $conversation->conversation_id,
                        'is_read' => $user->user_id == $currentUser->user_id || $isRecipientOnline, // Đánh dấu đã đọc nếu người nhận online
                    ]);

                    // Đẩy tin nhắn vào Redis Stream
                    Redis::xadd('chat_messages', '*', [
                        'user_id' => $currentUser->user_id,
                        'content' => $request['content'],
                        'conversation_id' => $conversation->conversation_id,
                        'created_at' => now()->toDateTimeString(),
                        'profile_photo_path' => Storage::disk($this->getDiskStorage())->url($currentUser->profile_photo_path),
                        'is_read' => $user->user_id == $currentUser->user_id || $isRecipientOnline, // Cập nhật is_read
                    ]);
                }
            }
            DB::commit();
            // Trả về phản hồi
            return $request->wantsJson()
                ? new JsonResponse($conversation, 200)
                : back()->with('status', 'conversation-created');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Create Conversation ' . $e->getMessage());
            return $request->wantsJson()
                ? new JsonResponse(['error' => 'An error occurred ' . $e->getMessage()], 500)
                : back()->withErrors(['error' => 'An error occurred while processing your request']);
        }
    }

    public function markRead(Request $request, $uuid)
    {
        $message = Message::where('conversation_id', $uuid)
            ->where('user_id', '!=', $request->user()->user_id)
            ->update(['is_read' => true]);
        return $request->wantsJson() ? new JsonResponse('', 200) : Inertia::render('NotFound');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $uuid)
    {
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Example $example)
    {
        //
    }

    private function getDiskStorage()
    {
        return isset($_ENV['VAPOR_ARTIFACT_NAME']) ? 's3' : 'public';
    }
}
