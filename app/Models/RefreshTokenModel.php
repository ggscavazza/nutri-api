<?php

namespace App\Models;

use CodeIgniter\Model;

class RefreshTokenModel extends Model
{
    protected $table         = 'refresh_tokens';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = ['user_id','token','expires_at','revoked_at'];
    protected $returnType    = 'array';
}
