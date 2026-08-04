<?php

namespace App\Controllers\Api;

use App\Helpers\ResponseHelper;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Base for every /api controller. Wraps ResponseHelper's envelope with the
 * matching HTTP status code so individual controllers just call ok()/fail()
 * instead of repeating setStatusCode()/setJSON() everywhere.
 */
abstract class BaseApiController extends Controller
{
    protected function ok($data = null, string $message = '', int $status = 200)
    {
        return $this->response
            ->setStatusCode($status)
            ->setJSON(ResponseHelper::success($data, $message));
    }

    protected function created($data = null, string $message = 'Created'): ResponseInterface
    {
        return $this->ok($data, $message, 201);
    }

    protected function fail(string $message, int $status = 400, $errors = null): ResponseInterface
    {
        return $this->response
            ->setStatusCode($status)
            ->setJSON(ResponseHelper::error($message, $errors));
    }

    protected function validationError($errors): ResponseInterface
    {
        return $this->fail('Validasi gagal.', 422, $errors);
    }

    protected function unauthorized(string $message = 'Unauthenticated.'): ResponseInterface
    {
        return $this->fail($message, 401);
    }

    protected function forbidden(string $message = 'Forbidden.'): ResponseInterface
    {
        return $this->fail($message, 403);
    }

    protected function notFound(string $message = 'Not found.'): ResponseInterface
    {
        return $this->fail($message, 404);
    }

    /**
     * @return array{0: int, 1: int} [page, perPage] — default 20, capped at 100.
     */
    protected function paginationParams(): array
    {
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = (int) ($this->request->getGet('per_page') ?? 20);
        $perPage = max(1, min(100, $perPage ?: 20));

        return [$page, $perPage];
    }

    protected function paginated(array $result): ResponseInterface
    {
        return $this->ok([
            'items'      => $result['items'],
            'pagination' => [
                'total'       => $result['total'],
                'page'        => $result['page'],
                'per_page'    => $result['per_page'],
                'total_pages' => (int) ceil($result['total'] / max($result['per_page'], 1)),
            ],
        ]);
    }
}
