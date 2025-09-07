<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateLinks extends Migration
{
    public function up()
    {
        // nutritionist_patient
        $this->forge->addField([
            'nutritionist_id' => ['type'=>'INT','constraint'=>11,'unsigned'=>true],
            'patient_id'      => ['type'=>'INT','constraint'=>11,'unsigned'=>true],
            'created_at'      => ['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey(['nutritionist_id','patient_id'], true);
        $this->forge->addForeignKey('nutritionist_id','users','id','CASCADE','CASCADE');
        $this->forge->addForeignKey('patient_id','users','id','CASCADE','CASCADE');
        $this->forge->createTable('nutritionist_patient');
    }

    public function down()
    {
        $this->forge->dropTable('nutritionist_patient');
    }
}
