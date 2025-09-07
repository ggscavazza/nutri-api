<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'users'; // prefixo api_ aplicado automaticamente
    protected $primaryKey       = 'id';
    protected $useSoftDeletes   = true;
    protected $useTimestamps    = true;
    protected $allowedFields    = ['name','email','password_hash','role','status','photo_url','deleted_at'];

    protected $returnType       = 'array';
}
