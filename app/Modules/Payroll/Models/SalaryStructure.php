<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use App\Modules\Payroll\Enums\PayrollComponentType;
use App\Shared\Support\MoneyHelper;
use App\Shared\Traits\HasAuditColumns;
use Database\Factories\SalaryStructureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalaryStructure extends Model
{
    /** @use HasFactory<SalaryStructureFactory> */
    use HasAuditColumns, HasFactory, SoftDeletes;

    protected $table = 'salary_structures';

    protected $fillable = [
        'name',
        'description',
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
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): SalaryStructureFactory
    {
        return SalaryStructureFactory::new();
    }

    public function components(): BelongsToMany
    {
        return $this->belongsToMany(
            PayrollComponent::class,
            'salary_structure_components',
            'salary_structure_id',
            'payroll_component_id',
        )
            ->using(SalaryStructureComponent::class)
            ->withPivot('default_amount')
            ->withTimestamps();
    }

    public function employeeSalaries(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class, 'salary_structure_id');
    }

    public function calculateDefaultComponentTotal(PayrollComponentType $type): string
    {
        $components = $this->relationLoaded('components')
            ? $this->components
            : $this->components()->get();

        $total = '0.00';

        foreach ($components as $component) {
            if ($component->type === $type) {
                /** @var SalaryStructureComponent|null $pivot */
                $pivot = $component->pivot;
                $amount = (string) ($pivot?->default_amount ?? '0.00');
                $total = MoneyHelper::add($total, $amount);
            }
        }

        return $total;
    }
}
