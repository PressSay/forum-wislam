<?php

namespace App\Http\Controllers;

use App\Models\Like;
use App\Models\Thread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function like(Request $request, $threadId)
    {
        $user = $request->user();
        if ($user == null) {
            return new JsonResponse('Error when like.', 500);
        }
        $thread = Thread::findOrFail($threadId);
        $like = Like::where('user_id', '=', $user->user_id)
            ->where('thread_id', '=', $thread->thread_id)
            ->first();
        if ($like == null) {
            Like::create([
                'user_id' => $user->user_id,
                'thread_id' => $threadId
            ]);
        } else {
            Like::where('thread_id', '=', $threadId)
                ->where('user_id', '=', $user->user_id)
                ->delete();
        }
        return new JsonResponse('', 200);
    }
}
