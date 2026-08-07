<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Android update-checker fields, Super Admin-owned like every other
 * platform_settings column. `mobile_apk_*` are set automatically by
 * apk/register (read straight from the uploaded APK's own manifest, never
 * hand-typed — a typo'd build number would mean "update available" forever
 * for every user). `mobile_min_build`/`mobile_maintenance*` stay manual —
 * they're policy decisions (force-update floor, app-wide kill switch), not
 * facts about a specific APK file.
 */
class AddMobileAppFieldsToPlatformSettings extends Migration
{
    public function up()
    {
        $this->forge->addColumn('platform_settings', [
            'mobile_apk_file'        => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'mobile_apk_version'     => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'mobile_apk_build'       => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'mobile_apk_size'        => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'mobile_apk_notes'       => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'mobile_apk_uploaded_at' => ['type' => 'DATETIME', 'null' => true],
            'mobile_min_build'       => ['type' => 'INT', 'constraint' => 11, 'null' => false, 'default' => 1],
            'mobile_maintenance'     => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 0],
            'mobile_maintenance_msg' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('platform_settings', [
            'mobile_apk_file', 'mobile_apk_version', 'mobile_apk_build', 'mobile_apk_size',
            'mobile_apk_notes', 'mobile_apk_uploaded_at', 'mobile_min_build',
            'mobile_maintenance', 'mobile_maintenance_msg',
        ]);
    }
}
