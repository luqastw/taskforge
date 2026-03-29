<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\HasFactory;

class Workspace extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'is_active', 'tenant_id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }
}
