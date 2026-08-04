<?php

namespace App\Services;

use App\Models\CustomerModel;
use App\Models\InvoiceModel;
use App\Models\TenantModel;
use RuntimeException;

/**
 * Renders the actual message text for a queued notification row. Kept
 * separate from NotificationDispatcher so adding a new notification type
 * only ever means adding one case here.
 */
class NotificationTemplates
{
    /**
     * @return array{subject: string, message: string}
     */
    public function render(array $notification): array
    {
        $payload = json_decode($notification['payload'], true) ?? [];
        $tenant  = (new TenantModel())->find($notification['tenant_id']);
        $tenantName = $tenant['name'] ?? 'NetHub';

        $customer = $notification['notifiable_type'] === 'customer'
            ? (new CustomerModel())->find($notification['notifiable_id'])
            : null;

        return match ($notification['type']) {
            'payment_confirmation' => $this->paymentConfirmation($tenantName, $customer, $payload),
            'invoice_reminder'     => $this->invoiceReminder($tenantName, $customer, $payload),
            'suspend'              => $this->statusChange($tenantName, $customer, 'disuspend sementara'),
            'reactivation'         => $this->statusChange($tenantName, $customer, 'diaktifkan kembali'),
            'broadcast'            => $this->broadcast($tenantName, $payload),
            'account_created'      => $this->accountCreated($tenantName, $customer, $payload),
            'password_reset'       => $this->passwordReset($payload),
            default                => throw new RuntimeException("Tidak ada template untuk tipe notifikasi '{$notification['type']}'."),
        };
    }

    private function paymentConfirmation(string $tenantName, ?array $customer, array $payload): array
    {
        $name    = $customer['name'] ?? 'Pelanggan';
        $invoice = $payload['invoice_number'] ?? '-';

        if ($payload['approved'] ?? false) {
            $message = "Halo {$name}, pembayaran Anda untuk tagihan {$invoice} telah *dikonfirmasi*. "
                . "Terima kasih telah menggunakan layanan {$tenantName}.";
        } else {
            $reason  = $payload['notes'] ?? 'bukti pembayaran tidak sesuai';
            $message = "Halo {$name}, pembayaran Anda untuk tagihan {$invoice} *ditolak* ({$reason}). "
                . "Silakan hubungi {$tenantName} untuk informasi lebih lanjut.";
        }

        return ['subject' => "Konfirmasi Pembayaran - {$invoice}", 'message' => $message];
    }

    private function invoiceReminder(string $tenantName, ?array $customer, array $payload): array
    {
        $name    = $customer['name'] ?? 'Pelanggan';
        $invoice = $payload['invoice_number'] ?? '-';
        $amount  = isset($payload['amount']) ? number_format((float) $payload['amount'], 0, ',', '.') : '-';
        $dueDate = $payload['due_date'] ?? '-';

        $message = "Halo {$name}, tagihan internet Anda ({$invoice}) sebesar Rp{$amount} akan jatuh tempo "
            . "pada {$dueDate}. Mohon segera lakukan pembayaran agar layanan {$tenantName} tidak terganggu.";

        return ['subject' => "Pengingat Jatuh Tempo - {$invoice}", 'message' => $message];
    }

    private function statusChange(string $tenantName, ?array $customer, string $verb): array
    {
        $name    = $customer['name'] ?? 'Pelanggan';
        $message = "Halo {$name}, layanan internet Anda di {$tenantName} telah {$verb}. "
            . 'Hubungi kami jika ada pertanyaan.';

        return ['subject' => 'Status Layanan Diperbarui', 'message' => $message];
    }

    private function accountCreated(string $tenantName, ?array $customer, array $payload): array
    {
        $name     = $customer['name'] ?? 'Pelanggan';
        $email    = $payload['email'] ?? '-';
        $password = $payload['password'] ?? '-';

        $message = "Halo {$name}, akun Anda untuk memantau & membayar tagihan {$tenantName} sudah aktif.\n\n"
            . "Login: {$email}\nPassword: {$password}\n\n"
            . 'Segera login dan disarankan untuk mengganti password Anda. Jangan bagikan pesan ini ke siapa pun.';

        return ['subject' => "Akun Pelanggan {$tenantName} Aktif", 'message' => $message];
    }

    private function passwordReset(array $payload): array
    {
        helper('url');
        $link = base_url('reset-password?email=' . urlencode($payload['email'] ?? '') . '&token=' . urlencode($payload['token'] ?? ''));

        $message = "Ada permintaan reset password untuk akun NetHub Anda.\n\n"
            . "Klik link berikut untuk atur password baru (berlaku 1 jam):\n{$link}\n\n"
            . 'Kalau bukan Anda yang meminta ini, abaikan pesan ini.';

        return ['subject' => 'Reset Password NetHub', 'message' => $message];
    }

    private function broadcast(string $tenantName, array $payload): array
    {
        return [
            'subject' => "Pengumuman dari {$tenantName}",
            'message' => $payload['message'] ?? '',
        ];
    }
}
