<?php

namespace App\Controllers\Api\Customer;

use App\Controllers\Api\BaseApiController;
use App\Helpers\AuthHelper;
use App\Repositories\CustomerRepository;
use App\Repositories\NotificationRepository;
use App\Services\NotificationTemplates;
use RuntimeException;

class NotificationController extends BaseApiController
{
    public function index()
    {
        $customer = $this->ownCustomerOrFail();
        if ($customer instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $customer;
        }

        [$page, $perPage] = $this->paginationParams();
        $unreadOnly = $this->request->getGet('unread_only') === '1';

        $result = (new NotificationRepository())->paginateForNotifiable(
            'customer',
            (int) $customer['id'],
            ['unread_only' => $unreadOnly],
            $page,
            $perPage
        );

        $templates          = new NotificationTemplates();
        $result['items'] = array_map(static function (array $row) use ($templates) {
            try {
                $rendered = $templates->render($row);
            } catch (RuntimeException) {
                $rendered = ['subject' => 'Notifikasi', 'message' => ''];
            }

            return [
                'id'         => (int) $row['id'],
                'type'       => $row['type'],
                'title'      => $rendered['subject'],
                'message'    => $rendered['message'],
                'read_at'    => $row['read_at'],
                'created_at' => $row['created_at'],
            ];
        }, $result['items']);

        return $this->paginated($result);
    }

    public function unreadCount()
    {
        $customer = $this->ownCustomerOrFail();
        if ($customer instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $customer;
        }

        $count = (new NotificationRepository())->unreadCount('customer', (int) $customer['id']);

        return $this->ok(['count' => $count]);
    }

    public function markRead($id = null)
    {
        $customer = $this->ownCustomerOrFail();
        if ($customer instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $customer;
        }

        $ok = (new NotificationRepository())->markRead((int) $id, 'customer', (int) $customer['id']);

        if (! $ok) {
            return $this->notFound('Notifikasi tidak ditemukan.');
        }

        return $this->ok(null, 'Notifikasi ditandai sudah dibaca.');
    }

    public function markAllRead()
    {
        $customer = $this->ownCustomerOrFail();
        if ($customer instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $customer;
        }

        $count = (new NotificationRepository())->markAllRead('customer', (int) $customer['id']);

        return $this->ok(['marked' => $count], 'Semua notifikasi ditandai sudah dibaca.');
    }

    /**
     * @return array|\CodeIgniter\HTTP\ResponseInterface
     */
    private function ownCustomerOrFail()
    {
        $customer = (new CustomerRepository())->findByUserId(AuthHelper::id());

        return $customer ?? $this->notFound('Data pelanggan tidak ditemukan untuk akun ini.');
    }
}
