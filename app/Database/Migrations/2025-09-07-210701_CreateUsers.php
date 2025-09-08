<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUsers extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'             => ['type'=>'INT','constraint'=>11,'unsigned'=>true,'auto_increment'=>true],
            'name'           => ['type'=>'VARCHAR','constraint'=>120],
            'email'          => ['type'=>'VARCHAR','constraint'=>190],
            'password_hash'  => ['type'=>'VARCHAR','constraint'=>255],
            'role'           => ['type'=>'ENUM','constraint'=>['master','nutricionista','paciente']],
            'status'         => ['type'=>'ENUM','constraint'=>['ativo','inativo'],'default'=>'ativo'],
            'photo_url'      => ['type'=>'VARCHAR','constraint'=>255,'null'=>true],
            'created_at'     => ['type'=>'DATETIME','null'=>true],
            'updated_at'     => ['type'=>'DATETIME','null'=>true],
            'deleted_at'     => ['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('email');
        $this->forge->createTable('users');
    }

    public function down()
    {
        $this->forge->dropTable('users');
    }
}
