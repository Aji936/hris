import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type PayrollComponentTypeValue, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, Coins, Layers, Pencil, WalletCards } from 'lucide-react';
import { type FormEvent, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Payroll & Compensation',
        href: '/payroll/management',
    },
];

export interface PayrollComponentRecord {
    id: number;
    code: string;
    name: string;
    type: PayrollComponentTypeValue;
    is_taxable: boolean;
    is_fixed: boolean;
    formula: string | null;
    is_active: boolean;
    default_amount?: string;
}

export interface SalaryStructureRecord {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    default_allowance_total?: string;
    default_deduction_total?: string;
    components?: PayrollComponentRecord[];
}

export interface EmployeeSalaryRecord {
    id: number;
    employee_id: number;
    salary_structure_id: number;
    effective_date: string;
    base_salary: string;
    payment_method: 'BANK_TRANSFER' | 'CASH';
    is_active: boolean;
    employee?: {
        id: number;
        employee_code: string;
        full_name: string;
    };
    salary_structure?: SalaryStructureRecord | null;
}

export interface PayrollManagementPageProps {
    components: PayrollComponentRecord[];
    structures: SalaryStructureRecord[];
    employeeSalaries: EmployeeSalaryRecord[];
    canManage: boolean;
}

type ActiveSection = 'salaries' | 'structures' | 'components';

type AdjustSalaryFormData = {
    salary_structure_id: string;
    effective_date: string;
    base_salary: string;
    payment_method: 'BANK_TRANSFER' | 'CASH';
};

type EditStructureFormData = {
    name: string;
    description: string;
};

type EditComponentFormData = {
    code: string;
    name: string;
    type: PayrollComponentTypeValue;
    formula: string;
};

function formatRupiah(amount: string | undefined): string {
    if (!amount) {
        return 'Rp 0';
    }
    const parsed = Number.parseFloat(amount);
    if (Number.isNaN(parsed)) {
        return `Rp ${amount}`;
    }

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(parsed);
}

export default function PayrollManagementIndex({
    components,
    structures,
    employeeSalaries,
    canManage,
}: PayrollManagementPageProps) {
    const page = usePage<SharedData>();
    const flash = page.props.flash;

    const [activeSection, setActiveSection] = useState<ActiveSection>('salaries');
    const [editingSalary, setEditingSalary] = useState<EmployeeSalaryRecord | null>(null);
    const [editingStructure, setEditingStructure] = useState<SalaryStructureRecord | null>(null);
    const [editingComponent, setEditingComponent] = useState<PayrollComponentRecord | null>(null);

    const salaryForm = useForm<AdjustSalaryFormData>({
        salary_structure_id: '',
        effective_date: '',
        base_salary: '',
        payment_method: 'BANK_TRANSFER',
    });

    const structureForm = useForm<EditStructureFormData>({
        name: '',
        description: '',
    });

    const componentForm = useForm<EditComponentFormData>({
        code: '',
        name: '',
        type: 'ALLOWANCE',
        formula: '',
    });

    const openEditSalary = (record: EmployeeSalaryRecord): void => {
        setEditingSalary(record);
        salaryForm.setData({
            salary_structure_id: String(record.salary_structure_id),
            effective_date: record.effective_date,
            base_salary: record.base_salary,
            payment_method: record.payment_method,
        });
        salaryForm.clearErrors();
    };

    const handleSalaryUpdate = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        if (!editingSalary) {
            return;
        }

        salaryForm.transform((data) => ({
            ...data,
            salary_structure_id: Number(data.salary_structure_id),
        }));
        salaryForm.put(`/payroll/employee-salaries/${editingSalary.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditingSalary(null),
        });
    };

    const openEditStructure = (structure: SalaryStructureRecord): void => {
        setEditingStructure(structure);
        structureForm.setData({
            name: structure.name,
            description: structure.description ?? '',
        });
        structureForm.clearErrors();
    };

    const handleStructureUpdate = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        if (!editingStructure) {
            return;
        }

        structureForm.put(`/payroll/structures/${editingStructure.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditingStructure(null),
        });
    };

    const openEditComponent = (comp: PayrollComponentRecord): void => {
        setEditingComponent(comp);
        componentForm.setData({
            code: comp.code,
            name: comp.name,
            type: comp.type,
            formula: comp.formula ?? '',
        });
        componentForm.clearErrors();
    };

    const handleComponentUpdate = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        if (!editingComponent) {
            return;
        }

        componentForm.transform((data) => ({
            ...data,
            formula: data.formula.trim() === '' ? null : data.formula.trim(),
        }));
        componentForm.put(`/payroll/components/${editingComponent.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditingComponent(null),
        });
    };

    const salaryErrors = salaryForm.errors as Record<string, string | undefined>;
    const structureErrors = structureForm.errors as Record<string, string | undefined>;
    const componentErrors = componentForm.errors as Record<string, string | undefined>;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payroll & Compensation" />

            <div className="flex flex-1 flex-col gap-6 p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Payroll &amp; Compensation Management</h1>
                        <p className="text-muted-foreground text-sm">
                            Adjust active employee salaries, compensation structures, and allowance/deduction components.
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant={activeSection === 'salaries' ? 'default' : 'outline'}
                            onClick={() => setActiveSection('salaries')}
                        >
                            <WalletCards className="size-4" />
                            Employee Salaries ({employeeSalaries.length})
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant={activeSection === 'structures' ? 'default' : 'outline'}
                            onClick={() => setActiveSection('structures')}
                        >
                            <Layers className="size-4" />
                            Salary Structures ({structures.length})
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant={activeSection === 'components' ? 'default' : 'outline'}
                            onClick={() => setActiveSection('components')}
                        >
                            <Coins className="size-4" />
                            Payroll Components ({components.length})
                        </Button>
                    </div>
                </div>

                {flash?.success && (
                    <Alert className="border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-300">
                        <CheckCircle2 className="size-4 text-emerald-600 dark:text-emerald-400" />
                        <AlertTitle>Success</AlertTitle>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}

                {activeSection === 'salaries' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Active Employee Salary Assignments</CardTitle>
                            <CardDescription>
                                Adjusting a salary creates a new effective compensation record and archives the previous assignment while keeping historical published payslips immutable.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="overflow-x-auto border-t">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 border-b text-xs uppercase">
                                        <tr>
                                            <th className="px-4 py-3">Employee</th>
                                            <th className="px-4 py-3">Salary Structure</th>
                                            <th className="px-4 py-3">Effective Date</th>
                                            <th className="px-4 py-3">Base Salary</th>
                                            <th className="px-4 py-3">Method</th>
                                            {canManage && <th className="px-4 py-3 text-right">Actions</th>}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {employeeSalaries.map((row) => (
                                            <tr key={row.id} className="hover:bg-muted/30 transition-colors">
                                                <td className="px-4 py-3">
                                                    <p className="font-medium">{row.employee?.full_name ?? `Employee #${row.employee_id}`}</p>
                                                    <p className="text-muted-foreground font-mono text-xs">{row.employee?.employee_code}</p>
                                                </td>
                                                <td className="px-4 py-3">{row.salary_structure?.name ?? `Structure #${row.salary_structure_id}`}</td>
                                                <td className="px-4 py-3 font-mono text-xs">{row.effective_date}</td>
                                                <td className="px-4 py-3 font-semibold tabular-nums">{formatRupiah(row.base_salary)}</td>
                                                <td className="px-4 py-3">
                                                    <Badge variant="outline">{row.payment_method}</Badge>
                                                </td>
                                                {canManage && (
                                                    <td className="px-4 py-3 text-right">
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => openEditSalary(row)}
                                                        >
                                                            <Pencil className="size-3.5" />
                                                            Edit / Adjust Salary
                                                        </Button>
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {activeSection === 'structures' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Salary Structures</CardTitle>
                            <CardDescription>
                                Master compensation packages and bundled allowance/deduction templates.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="overflow-x-auto border-t">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 border-b text-xs uppercase">
                                        <tr>
                                            <th className="px-4 py-3">Structure Name</th>
                                            <th className="px-4 py-3">Description</th>
                                            <th className="px-4 py-3">Default Allowances</th>
                                            {canManage && <th className="px-4 py-3 text-right">Actions</th>}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {structures.map((struct) => (
                                            <tr key={struct.id} className="hover:bg-muted/30 transition-colors">
                                                <td className="px-4 py-3 font-medium">{struct.name}</td>
                                                <td className="text-muted-foreground px-4 py-3">{struct.description ?? '-'}</td>
                                                <td className="px-4 py-3 tabular-nums">{formatRupiah(struct.default_allowance_total)}</td>
                                                {canManage && (
                                                    <td className="px-4 py-3 text-right">
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => openEditStructure(struct)}
                                                        >
                                                            <Pencil className="size-3.5" />
                                                            Edit
                                                        </Button>
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {activeSection === 'components' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Payroll Components</CardTitle>
                            <CardDescription>
                                Granular allowance and deduction definitions used in monthly payroll runs.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="overflow-x-auto border-t">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 border-b text-xs uppercase">
                                        <tr>
                                            <th className="px-4 py-3">Code</th>
                                            <th className="px-4 py-3">Component Name</th>
                                            <th className="px-4 py-3">Type</th>
                                            <th className="px-4 py-3">Formula</th>
                                            {canManage && <th className="px-4 py-3 text-right">Actions</th>}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {components.map((comp) => (
                                            <tr key={comp.id} className="hover:bg-muted/30 transition-colors">
                                                <td className="px-4 py-3 font-mono text-xs font-medium">{comp.code}</td>
                                                <td className="px-4 py-3 font-medium">{comp.name}</td>
                                                <td className="px-4 py-3">
                                                    <Badge variant={comp.type === 'ALLOWANCE' ? 'default' : 'secondary'}>
                                                        {comp.type}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-3 font-mono text-xs">{comp.formula ?? 'Fixed'}</td>
                                                {canManage && (
                                                    <td className="px-4 py-3 text-right">
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => openEditComponent(comp)}
                                                        >
                                                            <Pencil className="size-3.5" />
                                                            Edit
                                                        </Button>
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* Adjust Employee Salary Modal */}
            <Dialog open={editingSalary !== null} onOpenChange={(open) => !open && setEditingSalary(null)}>
                <DialogContent className="sm:max-w-md">
                    <form onSubmit={handleSalaryUpdate} className="flex flex-col gap-4">
                        <DialogHeader>
                            <DialogTitle>Adjust Salary — {editingSalary?.employee?.full_name}</DialogTitle>
                            <DialogDescription>
                                Save a new effective salary assignment. Previous active record will be archived automatically.
                            </DialogDescription>
                        </DialogHeader>

                        {salaryErrors.employee_salary && (
                            <Alert variant="destructive">
                                <AlertCircle className="size-4" />
                                <AlertTitle>Adjustment Error</AlertTitle>
                                <AlertDescription>{salaryErrors.employee_salary}</AlertDescription>
                            </Alert>
                        )}

                        <div className="grid gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="sal_structure">Salary Structure</Label>
                                <Select
                                    value={salaryForm.data.salary_structure_id}
                                    onValueChange={(value) => salaryForm.setData('salary_structure_id', value)}
                                >
                                    <SelectTrigger id="sal_structure">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {structures.map((st) => (
                                            <SelectItem key={st.id} value={String(st.id)}>
                                                {st.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="sal_effective">Effective Date</Label>
                                <Input
                                    id="sal_effective"
                                    type="date"
                                    value={salaryForm.data.effective_date}
                                    onChange={(e) => salaryForm.setData('effective_date', e.target.value)}
                                    required
                                />
                                {salaryErrors.effective_date && (
                                    <p className="text-destructive text-xs">{salaryErrors.effective_date}</p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="sal_base">New Base Salary (IDR)</Label>
                                <Input
                                    id="sal_base"
                                    type="number"
                                    step="0.01"
                                    value={salaryForm.data.base_salary}
                                    onChange={(e) => salaryForm.setData('base_salary', e.target.value)}
                                    required
                                />
                                {salaryErrors.base_salary && (
                                    <p className="text-destructive text-xs">{salaryErrors.base_salary}</p>
                                )}
                            </div>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setEditingSalary(null)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={salaryForm.processing}>
                                {salaryForm.processing ? 'Saving...' : 'Save Salary Adjustment'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Salary Structure Modal */}
            <Dialog open={editingStructure !== null} onOpenChange={(open) => !open && setEditingStructure(null)}>
                <DialogContent className="sm:max-w-md">
                    <form onSubmit={handleStructureUpdate} className="flex flex-col gap-4">
                        <DialogHeader>
                            <DialogTitle>Edit Salary Structure</DialogTitle>
                            <DialogDescription>
                                Update the structure name and description.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="struct_name">Structure Name</Label>
                                <Input
                                    id="struct_name"
                                    value={structureForm.data.name}
                                    onChange={(e) => structureForm.setData('name', e.target.value)}
                                    required
                                />
                                {structureErrors.name && <p className="text-destructive text-xs">{structureErrors.name}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="struct_desc">Description</Label>
                                <Input
                                    id="struct_desc"
                                    value={structureForm.data.description}
                                    onChange={(e) => structureForm.setData('description', e.target.value)}
                                />
                            </div>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setEditingStructure(null)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={structureForm.processing}>
                                {structureForm.processing ? 'Saving...' : 'Update Structure'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Payroll Component Modal */}
            <Dialog open={editingComponent !== null} onOpenChange={(open) => !open && setEditingComponent(null)}>
                <DialogContent className="sm:max-w-md">
                    <form onSubmit={handleComponentUpdate} className="flex flex-col gap-4">
                        <DialogHeader>
                            <DialogTitle>Edit Payroll Component</DialogTitle>
                            <DialogDescription>
                                Update component code, display name, or dynamic formula.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="comp_code">Component Code</Label>
                                <Input
                                    id="comp_code"
                                    value={componentForm.data.code}
                                    onChange={(e) => componentForm.setData('code', e.target.value)}
                                    required
                                />
                                {componentErrors.code && <p className="text-destructive text-xs">{componentErrors.code}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="comp_name">Component Name</Label>
                                <Input
                                    id="comp_name"
                                    value={componentForm.data.name}
                                    onChange={(e) => componentForm.setData('name', e.target.value)}
                                    required
                                />
                                {componentErrors.name && <p className="text-destructive text-xs">{componentErrors.name}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="comp_formula">Dynamic Formula (Optional)</Label>
                                <Input
                                    id="comp_formula"
                                    value={componentForm.data.formula}
                                    onChange={(e) => componentForm.setData('formula', e.target.value)}
                                    placeholder="e.g. basic_salary * 0.02"
                                />
                            </div>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setEditingComponent(null)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={componentForm.processing}>
                                {componentForm.processing ? 'Saving...' : 'Update Component'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
