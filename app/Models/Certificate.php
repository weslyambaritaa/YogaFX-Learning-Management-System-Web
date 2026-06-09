<?php

// app/Models/Certificate.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Certificate extends Model {
    use SoftDeletes;
    
    protected $fillable = [
        'user_id', 'certificate_type', 'file_path', 'file_name', 
        'version', 'generated_by_user_id', 'generated_at'
    ];
}