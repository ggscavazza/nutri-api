<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAuthSupport extends Migration
{
    public function up()
    {
        // refresh_tokens
        $this->forge->addField([
            'id'         => ['type'=>'INT','constraint'=>11,'unsigned'=>true,'auto_increment'=>true],
            'user_id'    => ['type'=>'INT','constraint'=>11,'unsigned'=>true],
            'token'      => ['type'=>'VARCHAR','constraint'=>255], // armazene hash do token
            'expires_at' => ['type'=>'DATETIME'],
            'revoked_at' => ['type'=>'DATETIME','null'=>true],
            'created_at' => ['type'=>'DATETIME','null'=>true],
            'updated_at' => ['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['user_id','token']);
        $this->forge->addForeignKey('user_id','users','id','CASCADE','CASCADE');
        $this->forge->createTable('refresh_tokens');

        // password_resets
        $this->forge->addField([
            'id'         => ['type'=>'INT','constraint'=>11,'unsigned'=>true,'auto_increment'=>true],
            'user_id'    => ['type'=>'INT','constraint'=>11,'unsigned'=>true],
            'token'      => ['type'=>'VARCHAR','constraint'=>255], // armazene hash
            'expires_at' => ['type'=>'DATETIME'],
            'used_at'    => ['type'=>'DATETIME','null'=>true],
            'created_at' => ['type'=>'DATETIME','null'=>true],
            'updated_at' => ['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['user_id','token']);
        $this->forge->addForeignKey('user_id','users','id','CASCADE','CASCADE');
        $this->forge->createTable('password_resets');
    }

    public function down()
    {
        $this->forge->dropTable('refresh_tokens');
        $this->forge->dropTable('password_resets');
    }
}
