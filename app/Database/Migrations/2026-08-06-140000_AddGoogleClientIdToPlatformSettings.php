<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Platform-level Google OAuth Web Client ID, Super Admin-editable — same
 * "operator owns this, not any tenant" pattern as the SMTP settings. Not a
 * secret (Google Client IDs are meant to be public, embedded in client
 * apps/web pages), so unlike smtp_pass this is never encrypted at rest.
 */
class AddGoogleClientIdToPlatformSettings extends Migration
{
    public function up()
    {
        $this->forge->addColumn('platform_settings', [
            'google_client_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('platform_settings', 'google_client_id');
    }
}
