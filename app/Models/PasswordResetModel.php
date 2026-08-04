<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Works for every role (owner/admin/teknisi/kasir/pelanggan/super_admin) —
 * keyed by email, not tied to tenant_id, since super_admin has none.
 */
class PasswordResetModel extends Model
{
    protected $table            = 'password_resets';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    protected $allowedFields = ['email', 'token_hash', 'expires_at', 'used_at'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    /**
     * The one still-valid (unused, unexpired) reset row for this email+token
     * pair, or null. Tokens are stored hashed, so this hashes the incoming
     * token the same way before comparing.
     */
    public function findValid(string $email, string $token): ?array
    {
        $hash = hash('sha256', $token);

        return $this->where('email', $email)
            ->where('token_hash', $hash)
            ->where('used_at', null)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->orderBy('id', 'DESC')
            ->first();
    }

    public function markUsed(int $id): void
    {
        $this->update($id, ['used_at' => date('Y-m-d H:i:s')]);
    }
}
