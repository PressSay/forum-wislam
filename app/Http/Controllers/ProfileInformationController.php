<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\ProfileInformationUpdatedResponse;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\ProfileImage;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Inertia\Inertia;
use Log;
use Illuminate\Support\Facades\Storage;

class ProfileInformationController extends Controller
{

    public function getUploaded(Request $request) {
        try {
            $user = $request->user();
            $avatarHistories = $user->avatarHistories()
                ->simplePaginate(12)
                ->through(function ($item) {
                    return [
                        'profile_image_id' => $item->profile_image_id,
                        'user_id' => $item->user_id,
                        'original_url' => Storage::disk(isset($_ENV['VAPOR_ARTIFACT_NAME']) ? 's3' : config('jetstream.profile_photo_disk', 'public'))->url( $item->original_url),
                        'created_at' => $item->created_at,
                        'updated_at' => $item->updated_at,
                    ];
                });
            return $avatarHistories;
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return Inertia::render('NotFound');
        }
    }

    public function updateImage(Request $request, UpdateUserProfileInformation $updater)
    {
        if (config('fortify.lowercase_usernames') && $request->has(Fortify::username())) {
            $request->merge([
                Fortify::username() => Str::lower($request->{Fortify::username()}),
            ]);
        }

        $updater->updateImage($request->user(), $request->all());
        return app(ProfileInformationUpdatedResponse::class);
    }

    public function destroyImage(Request $request, $id)
    {
        try {
            $profileImage = ProfileImage::findOrFail($id);
            Storage::disk(isset($_ENV['VAPOR_ARTIFACT_NAME']) ? 's3' : config('jetstream.profile_photo_disk', 'public'))->delete($profileImage->original_url);
            $profileImage->delete();

            return $request->wantsJson() ? new JsonResponse('', 200)
                : back()->with('status', 'profile-image-deleted');
        } catch (\Exception $e) {
            $validator = Validator::make([], []);
            $validator->errors()->add('id', 'Unable to delete category.');
            $validator->validateWithBag('destroyProfileImage');

            return $request->wantsJson() ? new JsonResponse('Unable to delete category', 500)
                : back()->withErrors($validator, 'destroyProfileImage');
        }
    }

}