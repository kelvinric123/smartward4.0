<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EcgUpload extends Model
{
    use HasFactory;

    protected $fillable = [
        'gateway_event_id',
        'gateway_id',
        'patient_code',
        'xml_filename',
        'pdf_filename',
        'file_size',
        'captured_at',
        'source_ip',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
        'file_size' => 'integer',
    ];
}
