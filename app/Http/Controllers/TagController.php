<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Exception;
use DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function all(Request $request)
    {
        try {
            if (!$request->user()) {
                throw new \Exception("");
            }
            $query = Tag::query();
            if ($request['search'] != '') {
                $query->where('name', 'like', '%' . $request['search'] . '%');
            }

            $tags = $query->simplePaginate(12);
            return $request->wantsJson() ? new JsonResponse($tags, 200) : Inertia::render('NotFound');
        } catch (\Exception $e) {
            // Log the error (optional)
            \Log::error('Error fetching tags: ' . $e->getMessage());

            // Return a user-friendly response
            return Inertia::render('NotFound');
        }
    }

    public function index(Request $request)
    {
        try {
            if (!Gate::allows('view-page-admin')) {
                throw new \Exception('Not accessible');
            }

            $search = $request->query('search');


            // \DB::enableQueryLog();
            // Start with a Query Builder instance
            $query = Tag::query();

            // Apply search filters if search term is provided
            if (!empty($search)) {

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('slug', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%');
                });
            }

            // Apply pagination
            $tags = $query->simplePaginate(12);

            return Inertia::render('Admin/Tag', [
                'tags' => $tags
            ]);
        } catch (\Exception $e) {
            // Log the error (optional)
            \Log::error('Error fetching tags: ' . $e->getMessage());

            // Return a user-friendly response
            return Inertia::render('NotFound');
        }
    }

    public function store(Request $request)
    {
        Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:tags,slug'],
        ])->validateWithBag('storeTag');

        try {
            if (!Gate::allows('view-page-admin')) {
                throw new \Exception('Not accessible');
            }
            // dd($request);

            // Tạo tag
            Tag::create([
                'name' => $request['name'],
                'description' => $request['description'],
                'slug' => $request['slug'],
            ]);

            // Trả về phản hồi
            return $request->wantsJson()
                ? new JsonResponse('', 200)
                : back()->with('status', 'tag-created');
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

    public function update(Request $request, $uuid)
    {
        Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:App\Models\Tag,slug,' . $uuid],
        ])->validateWithBag('updateTag');
        try {
            if (!Gate::allows('view-page-admin')) {
                throw new \Exception('Not accessible');
            }
            DB::beginTransaction();
            $tag = Tag::find($uuid);
            $tag->name = $request['name'];
            $tag->description = $request['description'];
            $tag->slug = $request['slug'];
            $tag->save();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error($e->getMessage());
            return $request->wantsJson()
                ? new JsonResponse(['error' => 'An error occurred'], 500)
                : back()->withErrors(['error' => 'An error occurred while processing your request']);
        }
        return $request->wantsJson()
            ? new JsonResponse('', 200)
            : back()->with('status', 'tag-updated');
    }

    public function destroy(Request $request, $uuid)
    {
        try {
            if (!Gate::allows('view-page-admin')) {
                throw new \Exception('Not accessible');
            }
            $tag = Tag::findOrFail($uuid);
            $tag->delete();
            return $request->wantsJson()
                ? new JsonResponse('', 200)
                : back()->with('status', 'tag-deleted');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $request->wantsJson()
                ? new JsonResponse(['error' => 'Tag not found'], 404)
                : back()->withErrors(['error' => 'Tag not found']);
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
