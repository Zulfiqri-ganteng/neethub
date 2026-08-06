<?php

namespace App\Models;

use CodeIgniter\Model;

class BiometricCredentialModel extends Model
{
    protected $table            = 'biometric_credentials';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    protected $allowedFields = [
        'user_id', 'device_id', 'device_name', 'public_key',
        'challenge', 'challenge_expires_at', 'last_used_at', 'revoked_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function findActiveByDeviceId(string $deviceId): ?array
    {
        return $this->where('device_id', $deviceId)
            ->where('revoked_at', null)
            ->first();
    }

    /**
     * One credential per (user, device) — re-registering the same device
     * (e.g. the user re-enabled the toggle after disabling it) replaces the
     * existing row's key/challenge state instead of erroring on the unique
     * `device_id` constraint or leaving a stale duplicate.
     */
    public function upsertForUser(int $userId, string $deviceId, ?string $deviceName, string $publicKey): void
    {
        $existing = $this->where('device_id', $deviceId)->first();

        if ($existing) {
            $this->update($existing['id'], [
                'user_id'     => $userId,
                'device_name' => $deviceName,
                'public_key'  => $publicKey,
                'revoked_at'  => null,
            ]);

            return;
        }

        $this->insert([
            'user_id'     => $userId,
            'device_id'   => $deviceId,
            'device_name' => $deviceName,
            'public_key'  => $publicKey,
        ]);
    }

    public function setChallenge(int $id, string $challenge, int $ttlSeconds): void
    {
        $this->update($id, [
            'challenge'            => $challenge,
            'challenge_expires_at' => date('Y-m-d H:i:s', time() + $ttlSeconds),
        ]);
    }

    public function clearChallenge(int $id): void
    {
        $this->update($id, ['challenge' => null, 'challenge_expires_at' => null]);
    }

    public function markUsed(int $id): void
    {
        $this->update($id, ['last_used_at' => date('Y-m-d H:i:s')]);
    }

    public function revokeForUser(int $userId, string $deviceId): bool
    {
        $existing = $this->where('user_id', $userId)
            ->where('device_id', $deviceId)
            ->where('revoked_at', null)
            ->first();

        if (! $existing) {
            return false;
        }

        $this->update($existing['id'], ['revoked_at' => date('Y-m-d H:i:s')]);

        return true;
    }
}
