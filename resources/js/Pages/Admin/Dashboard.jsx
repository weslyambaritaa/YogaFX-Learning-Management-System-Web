import { Badge } from '@/Components/ui/badge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatCurrency } from '@/lib/currency';
import { Head, router } from '@inertiajs/react';

function MetricCard({ label, value, helper }) {
    return (
        <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <p className="text-sm font-medium text-slate-500">{label}</p>
            <p className="mt-3 text-3xl font-semibold tracking-tight text-slate-900">
                {value}
            </p>
            {helper ? <p className="mt-2 text-sm text-slate-500">{helper}</p> : null}
        </div>
    );
}

function DashboardTable({ title, description, columns, rows, emptyMessage }) {
    return (
        <div className="rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-200 px-6 py-5">
                <h3 className="text-lg font-semibold text-slate-900">{title}</h3>
                <p className="mt-1 text-sm text-slate-500">{description}</p>
            </div>

            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-slate-200 text-sm">
                    <thead className="bg-slate-50">
                        <tr>
                            {columns.map((column) => (
                                <th
                                    key={column.key}
                                    className="px-4 py-3 text-left font-medium text-slate-700"
                                >
                                    {column.label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 bg-white">
                        {rows.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={columns.length}
                                    className="px-4 py-10 text-center text-sm text-slate-500"
                                >
                                    {emptyMessage}
                                </td>
                            </tr>
                        ) : (
                            rows.map((row) => (
                                <tr key={row.key}>
                                    {columns.map((column) => (
                                        <td
                                            key={`${row.key}-${column.key}`}
                                            className="px-4 py-4 text-slate-700"
                                        >
                                            {row[column.key]}
                                        </td>
                                    ))}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function ActivityChart({ series, peak }) {
    const width = 720;
    const height = 260;
    const safePeak = Math.max(peak || 0, 1);
    const pointGap = series.length > 1 ? width / (series.length - 1) : width;
    const points = series
        .map((point, index) => {
            const x = series.length === 1 ? width / 2 : index * pointGap;
            const y = height - (point.count / safePeak) * (height - 32) - 16;

            return `${x},${Number.isFinite(y) ? y : height - 16}`;
        })
        .join(' ');

    const areaPoints = series.length
        ? `0,${height} ${points} ${width},${height}`
        : '';

    return (
        <div className="rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-200 px-6 py-5">
                <h3 className="text-lg font-semibold text-slate-900">
                    Daily Active Students
                </h3>
                <p className="mt-1 text-sm text-slate-500">
                    Unique students with login session activity in each selected period.
                </p>
            </div>

            <div className="space-y-6 px-6 py-6">
                <div className="overflow-x-auto">
                    <div className="min-w-[720px]">
                        <svg
                            viewBox={`0 0 ${width} ${height}`}
                            className="h-[260px] w-full"
                            role="img"
                            aria-label="Daily active students chart"
                        >
                            {[0, 0.25, 0.5, 0.75, 1].map((ratio) => {
                                const y = height - ratio * (height - 32) - 16;

                                return (
                                    <line
                                        key={ratio}
                                        x1="0"
                                        x2={width}
                                        y1={y}
                                        y2={y}
                                        stroke="#e2e8f0"
                                        strokeDasharray="4 8"
                                    />
                                );
                            })}

                            {series.length ? (
                                <>
                                    <polygon
                                        points={areaPoints}
                                        fill="rgba(15, 23, 42, 0.08)"
                                    />
                                    <polyline
                                        points={points}
                                        fill="none"
                                        stroke="#0f172a"
                                        strokeWidth="3"
                                        strokeLinejoin="round"
                                        strokeLinecap="round"
                                    />
                                    {series.map((point, index) => {
                                        const x = series.length === 1 ? width / 2 : index * pointGap;
                                        const y =
                                            height -
                                            (point.count / safePeak) * (height - 32) -
                                            16;

                                        return (
                                            <g key={point.label}>
                                                <circle
                                                    cx={x}
                                                    cy={y}
                                                    r="5"
                                                    fill="#0f172a"
                                                />
                                                <text
                                                    x={x}
                                                    y={Math.max(y - 12, 12)}
                                                    textAnchor="middle"
                                                    className="fill-slate-500 text-[11px]"
                                                >
                                                    {point.count}
                                                </text>
                                            </g>
                                        );
                                    })}
                                </>
                            ) : null}
                        </svg>
                    </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                    {series.map((point) => (
                        <div
                            key={point.label}
                            className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"
                        >
                            <p className="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">
                                {point.label}
                            </p>
                            <p className="mt-2 text-xl font-semibold text-slate-900">
                                {point.count}
                            </p>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

export default function AdminDashboard({ filters, dashboard }) {
    const applyFilters = (overrides) => {
        router.get(
            route('admin.dashboard'),
            {
                student_scope: filters.student_scope,
                activity_range: filters.activity_range,
                ...overrides,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const studentTableRows = dashboard.student_metrics.tiers.map((tier) => ({
        key: tier.tier_slug,
        tier_name: tier.tier_name,
        total_students: tier.total_students,
        in_progress_students: tier.in_progress_students,
        completed_students: tier.completed_students,
    }));

    const revenueTableRows = dashboard.revenue_metrics.tiers.map((tier) => ({
        key: tier.tier_slug,
        tier_name: tier.tier_name,
        revenue_usd: formatCurrency(tier.revenue_usd, 'USD'),
        paid_payments_count: tier.paid_payments_count,
        unpaid_balance_usd: formatCurrency(tier.unpaid_balance_usd, 'USD'),
    }));

    return (
        <AuthenticatedLayout>
            <Head title="Admin Dashboard" />

            <div className="px-4 py-8 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-7xl space-y-6">
                    <div className="rounded-3xl border border-slate-200 bg-white px-6 py-6 shadow-sm">
                        <div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                            <div className="space-y-2">
                                <Badge variant="outline" className="rounded-full">
                                    Admin Dashboard
                                </Badge>
                                <div>
                                    <h2 className="text-3xl font-semibold tracking-tight text-slate-900">
                                        Student and revenue snapshot
                                    </h2>
                                    <p className="mt-2 max-w-3xl text-sm text-slate-600">
                                        Monitor active student distribution, learning-path
                                        completion, realized revenue, and daily active student
                                        trends from login sessions.
                                    </p>
                                </div>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <label className="space-y-2 text-sm text-slate-600">
                                    <span className="font-medium text-slate-700">
                                        Student Scope
                                    </span>
                                    <select
                                        value={filters.student_scope}
                                        onChange={(event) =>
                                            applyFilters({
                                                student_scope: event.target.value,
                                            })
                                        }
                                        className="h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700"
                                    >
                                        {dashboard.student_scope_options.map((option) => (
                                            <option key={option.value} value={option.value}>
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                </label>

                                <label className="space-y-2 text-sm text-slate-600">
                                    <span className="font-medium text-slate-700">
                                        Activity Range
                                    </span>
                                    <select
                                        value={filters.activity_range}
                                        onChange={(event) =>
                                            applyFilters({
                                                activity_range: event.target.value,
                                            })
                                        }
                                        className="h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700"
                                    >
                                        {dashboard.activity_range_options.map((option) => (
                                            <option key={option.value} value={option.value}>
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            </div>
                        </div>

                        {dashboard.warnings.length ? (
                            <div className="mt-5 space-y-2">
                                {dashboard.warnings.map((warning) => (
                                    <div
                                        key={warning.message}
                                        className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                                    >
                                        {warning.message}
                                    </div>
                                ))}
                            </div>
                        ) : null}
                    </div>

                    <div className="grid gap-4 lg:grid-cols-2">
                        <MetricCard
                            label="Total Students"
                            value={dashboard.student_metrics.total_students}
                            helper={
                                filters.student_scope === 'active'
                                    ? 'Filtered to students with an active login session.'
                                    : 'Counts all student accounts.'
                            }
                        />
                        <MetricCard
                            label="Total Revenue"
                            value={formatCurrency(
                                dashboard.revenue_metrics.total_revenue_usd,
                                'USD',
                            )}
                            helper="Summed from successful payment amounts after USD conversion."
                        />
                    </div>

                    <div className="grid gap-6 xl:grid-cols-2">
                        <DashboardTable
                            title="Student Progress by Tier"
                            description="Each row summarizes student distribution within the active learning path for that tier."
                            columns={[
                                { key: 'tier_name', label: 'Tier' },
                                { key: 'total_students', label: 'Total Students' },
                                { key: 'in_progress_students', label: 'In Progress' },
                                { key: 'completed_students', label: 'Completed' },
                            ]}
                            rows={studentTableRows}
                            emptyMessage="No tier-based student data is available yet."
                        />

                        <DashboardTable
                            title="Revenue by Tier"
                            description="Revenue is converted to USD, paid payments are counted from successful payment records, and unpaid balance uses remaining invoice balance."
                            columns={[
                                { key: 'tier_name', label: 'Tier' },
                                { key: 'revenue_usd', label: 'Revenue' },
                                { key: 'paid_payments_count', label: 'Paid Payments' },
                                { key: 'unpaid_balance_usd', label: 'Unpaid Balance' },
                            ]}
                            rows={revenueTableRows}
                            emptyMessage="No revenue data is available yet."
                        />
                    </div>

                    <ActivityChart
                        series={dashboard.activity_chart.series}
                        peak={dashboard.activity_chart.peak}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
