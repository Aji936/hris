<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class SalaryStructureComponent extends Pivot
{
    protected $table = 'salary_structure_components';

    public $incrementing = true;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_amount' => 'decimal:2',
        ];
    }
}
