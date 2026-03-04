<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ApiLog extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'endpoint',
        'method',
        'request_body',
        'response_body',
        'status_code',
        'ip_address',
        'user_agent',
        'duration_ms',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'request_body' => 'array',
            'response_body' => 'array',
            'status_code' => 'integer',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
