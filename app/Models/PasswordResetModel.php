<?php

namespace App\Models;

use CodeIgniter\Model;

class PasswordResetModel extends Model
{
    protected $table         = 'password_resets';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = ['user_id','token','expires_at','used_at'];
    protected $returnType    = 'array';
}
