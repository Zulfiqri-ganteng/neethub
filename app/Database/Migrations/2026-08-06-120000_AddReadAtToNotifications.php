<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddReadAtToNotifications extends Migration
{
    public function up()
    {
        $this->forge->addColumn('notifications', [
            'read_at' => [
                'type'       => 'DATETIME',
                'null'       => true,
                'after'      => 'sent_at',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('notifications', 'read_at');
    }
}
