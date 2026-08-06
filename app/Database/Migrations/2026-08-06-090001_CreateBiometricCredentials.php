<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * One row per (user, device) — a device registers its biometric-gated
 * public key here after a normal password login, then proves possession of
 * the matching private key (signed via the device's Keystore/Secure Enclave,
 * unlockable only by the user's fingerprint/face) to log in again without a
 * password. `challenge`/`challenge_expires_at` hold the current pending
 * login challenge inline rather than a separate table — this table is
 * already 1 row per device, so there's never more than one challenge in
 * flight per row.
 */
class CreateBiometricCredentials extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'device_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'device_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'public_key' => [
                'type' => 'TEXT',
            ],
            'challenge' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'challenge_expires_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'last_used_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'revoked_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('user_id');
        // Global uniqueness (not per-user) — a given app install only ever
        // signs in as one account at a time in this app, so `device_id`
        // doubles as the lookup key for the pre-login challenge/login
        // endpoints, which don't know who the user is yet.
        $this->forge->addUniqueKey('device_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('biometric_credentials');
    }

    public function down()
    {
        $this->forge->dropTable('biometric_credentials');
    }
}
