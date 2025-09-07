<?php

namespace App\Models;

use CodeIgniter\Model;

class NutritionistPatientModel extends Model
{
    protected $table         = 'nutritionist_patient';
    protected $primaryKey    = null; // PK composta
    protected $useAutoIncrement = false;
    protected $allowedFields = ['nutritionist_id','patient_id','created_at'];
    protected $returnType    = 'array';
    public $timestamps       = false;
}
