<?php

namespace Src\Shared\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLogModel extends Model
{
    protected $table = 'audit_logs';

    protected $fillable = [
        'action',
        'entity_type',
        'entity_id',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
