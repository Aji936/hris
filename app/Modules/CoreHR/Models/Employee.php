<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Models;

use App\Models\User;
use App\Modules\CoreHR\Enums\EmploymentStatus;
use App\Shared\Traits\HasAuditColumns;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasAuditColumns, HasFactory, SoftDeletes;

    protected $table = 'employees';

    protected $fillable = [
        'user_id',
        'employee_code',
        'full_name',
        'department_id',
        'position_id',
        'manager_id',
        'join_date',
        'status',
        'salary_grade',
        'basic_salary',
        'national_id_number',
        'tax_id_number',
        'bank_account_number',
        'bank_name',
        'created_by',
        'updated_by',
    ];

    /**
     * Prevent accidental exposure of sensitive PII and financial fields in default serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'national_id_number',
        'tax_id_number',
        'bank_account_number',
        'basic_salary',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'join_date' => 'date',
            'status' => EmploymentStatus::class,
            'basic_salary' => 'decimal:2',
            'national_id_number' => 'encrypted',
            'tax_id_number' => 'encrypted',
            'bank_account_number' => 'encrypted',
        ];
    }

    protected static function newFactory(): EmployeeFactory
    {
        return EmployeeFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }
}
