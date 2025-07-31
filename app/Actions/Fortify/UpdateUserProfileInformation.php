<?php

namespace App\Actions\Fortify;

use App\Models\User;
use DB;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;


class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:1024'],
        ])->validateWithBag('updateProfileInformation');

        if (isset($input['photo'])) {
            $user->updateProfilePhoto($input['photo']);
        }

        if (
            $input['email'] !== $user->email &&
            $user instanceof MustVerifyEmail
        ) {
            $this->updateVerifiedUser($user, $input);
        } else {
            $user->forceFill([
                'name' => $input['name'],
                'email' => $input['email'],
            ])->save();
        }
    }

    public function updateImage(User $user, array $input): void
    {
        Validator::make($input, [
            'photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:1024'],
            'cover' => ['nullable', 'mimes:jpg,jpeg,png', 'max:1024'],
            'original_photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:1024'],
            'original_cover' => ['nullable', 'mimes:jpg,jpeg,png', 'max:1024'],
        ])->validateWithBag('updateProfileInformation');

        if (isset($input['original_photo'])) {
            // Store new Image
            Validator::make($input, [
                'photo' => ['required', 'mimes:jpg,jpeg,png', 'max:1024']
            ])->validateWithBag('updateProfileInformation');
            try {
                DB::beginTransaction();
                // because updateProfilePhoto will delete previous images so leave it in front
                $user->updateProfilePhoto($input['photo']);
                $user->storeProfileOriginalImage($input['original_photo']);
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
            }
            return;
        }

        if (isset($input['original_cover'])) {
            // Store new Cover
            Validator::make($input, [
                'cover' => ['required', 'mimes:jpg,jpeg,png', 'max:1024']
            ])->validateWithBag('updateProfileInformation');
            try {
                DB::beginTransaction();
                $user->updateProfileCover($input['cover']);
                $user->storeProfileOriginalImage($input['original_cover']);
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
            }
            return;
        }

        if (isset($input['photo'])) {
            $user->updateProfilePhoto($input['photo']);
            return;
        }

        if (isset($input['cover'])) {
            $user->updateProfileCover($input['cover']);
            return;
        }
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<string, string>  $input
     */
    protected function updateVerifiedUser(User $user, array $input): void
    {
        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();
    }
}
