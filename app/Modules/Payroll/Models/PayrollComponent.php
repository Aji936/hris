<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use App\Modules\Payroll\Enums\PayrollComponentType;
use App\Shared\Traits\HasAuditColumns;
use Database\Factories\PayrollComponentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollComponent extends Model
{
    /** @use HasFactory<PayrollComponentFactory> */
    use HasAuditColumns, HasFactory, SoftDeletes;

    protected $table = 'payroll_components';

    protected $fillable = [
        'code',
        'name',
        'type',
        'is_taxable',
        'is_fixed',
        'formula',
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
            'type' => PayrollComponentType::class,
            'is_taxable' => 'boolean',
            'is_fixed' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): PayrollComponentFactory
    {
        return PayrollComponentFactory::new();
    }

    public function salaryStructures(): BelongsToMany
    {
        return $this->belongsToMany(
            SalaryStructure::class,
            'salary_structure_components',
            'payroll_component_id',
            'salary_structure_id',
        )
            ->using(SalaryStructureComponent::class)
            ->withPivot('default_amount')
            ->withTimestamps();
    }
}
