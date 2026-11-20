import LeaveIndex from '@/Modules/Attendance/Pages/Leave/Index';
import { type LeaveBalanceProps } from '@/types';

export default function LeaveBalancesIndex(props: LeaveBalanceProps) {
    return <LeaveIndex {...props} initialTab="balances" />;
}

