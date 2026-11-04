<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Models;

use App\Shared\Traits\HasAuditColumns;
use Database\Factories\PositionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Position extends Model
{
    /** @use HasFactory<PositionFactory> */
    use HasAuditColumns, HasFactory, SoftDeletes;

    protected $table = 'positions';

    protected $fillable = [
        'department_id',
        'code',
        'title',
        'level',
        'description',
        'base_salary_min',
        'base_salary_max',
        'is_active',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_salary_min' => 'decimal:2',
            'base_salary_max' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): PositionFactory
    {
        return PositionFactory::new();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'position_id');
    }
}
