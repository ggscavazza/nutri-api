<?php

namespace App\Models;

use CodeIgniter\Model;

class DocumentPatientModel extends Model
{
    protected $table         = 'document_patient';
    protected $primaryKey    = null; // PK composta
    protected $useAutoIncrement = false;
    protected $allowedFields = ['document_id','patient_id'];
    protected $returnType    = 'array';
    public $timestamps       = false;
}
