<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Auto-generate slug from name and ensure lowercase
        static::creating(function (Tenant $tenant) {
            if (! $tenant->slug) {
                $tenant->slug = Str::slug($tenant->name);
            } else {
                $tenant->slug = Str::lower($tenant->slug);
            }
        });
    }

    /**
     * Get all users for this tenant.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the owner of this tenant.
     */
    public function owner()
    {
        return $this->users()->whereHas('roles', function ($query) {
            $query->where('name', 'owner');
        })->first();
    }
}
