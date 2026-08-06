<?php

namespace App\Helpers;

/**
 * Reusable validation rule sets shared across auth/API controllers, so the
 * same "what makes a valid password/phone/etc." rule isn't repeated inline.
 */
class ValidationHelper
{
    public static function registerRules(): array
    {
        return [
            'tenant_name' => 'required|min_length[3]|max_length[150]',
            'owner_name'  => 'required|min_length[2]|max_length[150]',
            'email'       => 'required|valid_email|max_length[150]|is_unique[users.email]',
            'phone'       => 'permit_empty|max_length[20]',
            'password'    => 'required|min_length[8]',
        ];
    }

    public static function loginRules(): array
    {
        return [
            'email'    => 'required|valid_email',
            'password' => 'required',
        ];
    }

    /**
     * `tenant_name`/`owner_name`/`phone` are only required on the SECOND
     * call, once AuthController::google() has already told the app the
     * email isn't registered yet — permit_empty here since the first call
     * (plain login attempt) only ever sends `id_token`.
     */
    public static function googleAuthRules(): array
    {
        return [
            'id_token'    => 'required|string',
            'tenant_name' => 'permit_empty|min_length[3]|max_length[150]',
            'owner_name'  => 'permit_empty|min_length[2]|max_length[150]',
            'phone'       => 'permit_empty|max_length[20]',
        ];
    }

    public static function refreshRules(): array
    {
        return [
            'refresh_token' => 'required|string',
        ];
    }

    public static function forgotPasswordRules(): array
    {
        return [
            'email' => 'required|valid_email',
        ];
    }

    public static function resetPasswordRules(): array
    {
        return [
            'email'    => 'required|valid_email',
            'token'    => 'required|string',
            'password' => 'required|min_length[8]',
        ];
    }

    public static function biometricRegisterRules(): array
    {
        return [
            'device_id'   => 'required|string|max_length[100]',
            'device_name' => 'permit_empty|string|max_length[150]',
            'public_key'  => 'required|string',
        ];
    }

    public static function biometricChallengeRules(): array
    {
        return [
            'device_id' => 'required|string|max_length[100]',
        ];
    }

    public static function biometricLoginRules(): array
    {
        return [
            'device_id' => 'required|string|max_length[100]',
            'signature' => 'required|string',
        ];
    }

    public static function biometricRevokeRules(): array
    {
        return [
            'device_id' => 'required|string|max_length[100]',
        ];
    }
}
