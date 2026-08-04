<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Platform-level SMTP config, Super Admin-editable (Stage 13 follow-up) —
 * used for system emails like password-reset links. Separate from each
 * tenant's own settings; this is the platform operator's own mail account,
 * same "operator owns this, not any tenant" pattern as platform billing.
 */
class AddSmtpToPlatformSettings extends Migration
{
    public function up()
    {
        $this->forge->addColumn('platform_settings', [
            'smtp_host' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'smtp_port' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
                'default'  => 587,
            ],
            'smtp_user' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'smtp_pass' => [
                'type' => 'TEXT',
                'null' => true,
                'comment' => 'Encrypted at rest, same as tenant whatsapp_token/payment_gateway_config',
            ],
            'smtp_crypto' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'null'       => true,
                'default'    => 'tls',
                'comment'    => 'tls|ssl|(empty for none)',
            ],
            'smtp_from_email' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'smtp_from_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('platform_settings', [
            'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_crypto', 'smtp_from_email', 'smtp_from_name',
        ]);
    }
}
