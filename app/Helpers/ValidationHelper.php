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

    public static function refreshRules(): array
    {
        return [
            'refresh_token' => 'required|string',
        ];
    }
}
