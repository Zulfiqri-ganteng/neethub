<?php

namespace App\Helpers;

/**
 * Builds the standard { success, data, message } envelope used by every
 * NetHub API response.
 */
class ResponseHelper
{
    public static function success($data = null, string $message = ''): array
    {
        return [
            'success' => true,
            'data'    => $data,
            'message' => $message,
        ];
    }

    public static function error(string $message, $errors = null): array
    {
        $payload = [
            'success' => false,
            'data'    => null,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return $payload;
    }

    public static function paginated(array $items, int $total, int $page, int $perPage): array
    {
        return self::success([
            'items'      => $items,
            'pagination' => [
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => (int) ceil($total / max($perPage, 1)),
            ],
        ]);
    }
}
