<?php

namespace App\Models;

use CodeIgniter\Model;

class DocumentModel extends Model
{
    protected $table            = 'documents';
    protected $primaryKey       = 'id';
    protected $useSoftDeletes   = true;
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'title','description','file_type','storage_path','download_url',
        'size_bytes','scope','status','uploaded_by','deleted_at'
    ];
    protected $returnType       = 'array';
}
