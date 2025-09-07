<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDocuments extends Migration
{
    public function up()
    {
        // documents
        $this->forge->addField([
            'id'           => ['type'=>'INT','constraint'=>11,'unsigned'=>true,'auto_increment'=>true],
            'title'        => ['type'=>'VARCHAR','constraint'=>180],
            'description'  => ['type'=>'TEXT','null'=>true],
            'file_type'    => ['type'=>'ENUM','constraint'=>['pdf','epub','docx']],
            'storage_path' => ['type'=>'VARCHAR','constraint'=>255],
            'download_url' => ['type'=>'VARCHAR','constraint'=>255],
            'size_bytes'   => ['type'=>'BIGINT','unsigned'=>true],
            'scope'        => ['type'=>'ENUM','constraint'=>['geral','paciente']],
            'status'       => ['type'=>'ENUM','constraint'=>['ativo','inativo'],'default'=>'ativo'],
            'uploaded_by'  => ['type'=>'INT','constraint'=>11,'unsigned'=>true],
            'created_at'   => ['type'=>'DATETIME','null'=>true],
            'updated_at'   => ['type'=>'DATETIME','null'=>true],
            'deleted_at'   => ['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['scope','status']);
        $this->forge->addForeignKey('uploaded_by','users','id','CASCADE','SET NULL');
        $this->forge->createTable('documents');

        // document_patient
        $this->forge->addField([
            'document_id' => ['type'=>'INT','constraint'=>11,'unsigned'=>true],
            'patient_id'  => ['type'=>'INT','constraint'=>11,'unsigned'=>true],
        ]);
        $this->forge->addKey(['document_id','patient_id'], true);
        $this->forge->addForeignKey('document_id','documents','id','CASCADE','CASCADE');
        $this->forge->addForeignKey('patient_id','users','id','CASCADE','CASCADE');
        $this->forge->createTable('document_patient');
    }

    public function down()
    {
        $this->forge->dropTable('document_patient');
        $this->forge->dropTable('documents');
    }
}
