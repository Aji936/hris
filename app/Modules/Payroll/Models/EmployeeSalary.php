<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use App\Modules\CoreHR\Models\Employee;
use App\Modules\Payroll\Enums\PaymentMethod;
use App\Shared\Traits\HasAuditColumns;
use Database\Factories\EmployeeSalaryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeSalary extends Model
{
    /** @use HasFactory<EmployeeSalaryFactory> */
    use HasAuditColumns, HasFactory, SoftDeletes;

    protected $table = 'employee_salaries';

    protected $fillable = [
        'employee_id',
        'salary_structure_id',
        'effective_date',
        'base_salary',
        'payment_method',
        'is_active',
        'created_by',
        'updated_by',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'base_salary',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'base_salary' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): EmployeeSalaryFactory
    {
        return EmployeeSalaryFactory::new();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function salaryStructure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class, 'salary_structure_id');
    }
}
