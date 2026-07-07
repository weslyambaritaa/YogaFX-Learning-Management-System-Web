import { Badge } from "@/Components/ui/badge";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { formatCurrency } from "@/lib/currency";
import { Head, router } from "@inertiajs/react";
import { useState } from "react";

function MetricCard({ label, value, helper }) {
    return (
        <div className="rounded-3xl border border-slate-200 bg-white px-6 py-5 shadow-sm">
            <div className="flex h-full flex-col justify-between gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-slate-500">{label}</p>
                    {helper ? (
                        <p className="mt-2 max-w-sm text-sm leading-6 text-slate-500">
                            {helper}
                        </p>
                    ) : null}
                </div>

                <div className="shrink-0 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 sm:min-w-[180px] sm:text-right">
                    <p className="text-3xl font-semibold tracking-tight text-slate-900">
                        {value}
                    </p>
                </div>
            </div>
        </div>
    );
}

function DashboardTable({ title, description, columns, rows, emptyMessage }) {
    return (
        <div className="rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-200 px-6 py-5">
                <h3 className="text-lg font-semibold text-slate-900">
                    {title}
                </h3>
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
    const [activeIndex, setActiveIndex] = useState(null);
    const width = 860;
    const height = 300;
    const padding = {
        top: 18,
        right: 10,
        bottom: 36,
        left: 28,
    };
    const innerWidth = width - padding.left - padding.right;
    const innerHeight = height - padding.top - padding.bottom;
    const safePeak = Math.max(peak || 0, 1);
    const latestPoint = series[series.length - 1] ?? null;
    const highestPoint =
        series.reduce(
            (best, point) => (point.count > best.count ? point : best),
            series[0] ?? { label: "-", count: 0 },
        ) ?? null;
    const averageCount = series.length
        ? Math.round(
              series.reduce((total, point) => total + point.count, 0) /
                  series.length,
          )
        : 0;
    const activePoint =
        activeIndex !== null ? (series[activeIndex] ?? null) : null;
    const highlightedPoint = activePoint ??
        latestPoint ?? {
            label: "-",
            count: 0,
        };
    const xStep = series.length > 1 ? innerWidth / (series.length - 1) : 0;
    const yTicks = Array.from({ length: 5 }, (_, index) =>
        Math.round((safePeak / 4) * (4 - index)),
    );

    const points = series.map((point, index) => {
        const x =
            series.length === 1
                ? padding.left + innerWidth / 2
                : padding.left + index * xStep;
        const y =
            padding.top + innerHeight - (point.count / safePeak) * innerHeight;

        return {
            ...point,
            x,
            y: Number.isFinite(y) ? y : padding.top + innerHeight,
        };
    });

    const linePoints = points.map((point) => `${point.x},${point.y}`).join(" ");
    const areaPoints = points.length
        ? `${padding.left},${padding.top + innerHeight} ${linePoints} ${
              padding.left + innerWidth
          },${padding.top + innerHeight}`
        : "";

    const visibleXAxisLabels = points.map((point, index) => {
        if (points.length <= 8) {
            return true;
        }

        if (points.length <= 12) {
            return index % 2 === 0 || index === points.length - 1;
        }

        if (points.length <= 31) {
            return index % 5 === 0 || index === points.length - 1;
        }

        return index % 2 === 0 || index === points.length - 1;
    });

    const tooltipAlignmentClass =
        activeIndex === null
            ? ""
            : activeIndex >= Math.max(points.length - 2, 1)
              ? "-translate-x-full"
              : activeIndex <= 1
                ? "translate-x-0"
                : "-translate-x-1/2";

    const tooltipStyle =
        activeIndex !== null && points[activeIndex]
            ? {
                  left: `${(points[activeIndex].x / width) * 100}%`,
              }
            : undefined;

    return (
        <div className="rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-200 px-6 py-4">
                <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div className="min-w-0">
                        <h3 className="text-lg font-semibold text-slate-900">
                            Daily Active Students
                        </h3>
                    </div>

                    <div className="grid gap-2 sm:grid-cols-3 xl:min-w-[420px]">
                        <div className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2.5">
                            <p className="text-[10px] font-medium uppercase tracking-[0.18em] text-slate-500">
                                Latest
                            </p>
                            <div className="mt-1.5 flex items-end justify-between gap-3">
                                <p className="text-2xl font-semibold text-slate-900">
                                    {latestPoint?.count ?? 0}
                                </p>
                                <p className="text-xs text-slate-500">
                                    {latestPoint?.label ?? "-"}
                                </p>
                            </div>
                        </div>

                        <div className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2.5">
                            <p className="text-[10px] font-medium uppercase tracking-[0.18em] text-slate-500">
                                Peak
                            </p>
                            <div className="mt-1.5 flex items-end justify-between gap-3">
                                <p className="text-2xl font-semibold text-slate-900">
                                    {highestPoint?.count ?? 0}
                                </p>
                                <p className="text-xs text-slate-500">
                                    {highestPoint?.label ?? "-"}
                                </p>
                            </div>
                        </div>

                        <div className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2.5">
                            <p className="text-[10px] font-medium uppercase tracking-[0.18em] text-slate-500">
                                Average
                            </p>
                            <div className="mt-1.5 flex items-end justify-between gap-3">
                                <p className="text-2xl font-semibold text-slate-900">
                                    {averageCount}
                                </p>
                                <p className="text-xs text-slate-500">
                                    Active students
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div className="px-4 py-4 sm:px-5 sm:py-5">
                <div className="rounded-[28px] border border-slate-200 bg-[linear-gradient(180deg,#ffffff_0%,#f8fafc_100%)] p-3 sm:p-4">
                    <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <p className="text-sm font-medium text-slate-500">
                                Highlighted period
                            </p>
                            <div className="mt-1.5 flex items-end gap-3">
                                <span className="text-4xl font-semibold tracking-tight text-slate-950">
                                    {highlightedPoint.count}
                                </span>
                                <div className="pb-1">
                                    <p className="text-sm font-medium text-slate-700">
                                        active students
                                    </p>
                                    <p className="text-sm text-slate-500">
                                        {highlightedPoint.label}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        className="relative w-full"
                        onMouseLeave={() => setActiveIndex(null)}
                    >
                        {activePoint ? (
                            <div
                                className={[
                                    "pointer-events-none absolute top-0 z-10 hidden rounded-2xl border border-slate-200 bg-white/98 px-4 py-3 shadow-xl ring-1 ring-slate-100 backdrop-blur md:block",
                                    tooltipAlignmentClass,
                                ].join(" ")}
                                style={tooltipStyle}
                            >
                                <p className="text-xs font-medium uppercase tracking-[0.18em] text-slate-500">
                                    {activePoint.label}
                                </p>
                                <p className="mt-2 text-2xl font-semibold text-slate-950">
                                    {activePoint.count}
                                </p>
                                <p className="text-xs text-slate-500">
                                    Daily active students
                                </p>
                            </div>
                        ) : null}

                        <svg
                            viewBox={`0 0 ${width} ${height}`}
                            className="h-[300px] w-full"
                            role="img"
                            aria-label="Daily active students chart"
                        >
                            <defs>
                                <linearGradient
                                    id="activity-area-fill"
                                    x1="0"
                                    y1="0"
                                    x2="0"
                                    y2="1"
                                >
                                    <stop
                                        offset="0%"
                                        stopColor="#0f172a"
                                        stopOpacity="0.16"
                                    />
                                    <stop
                                        offset="100%"
                                        stopColor="#0f172a"
                                        stopOpacity="0.02"
                                    />
                                </linearGradient>
                            </defs>

                            {yTicks.map((tick) => {
                                const y =
                                    padding.top +
                                    innerHeight -
                                    (tick / safePeak) * innerHeight;
                                return (
                                    <g key={tick}>
                                        <line
                                            x1={padding.left}
                                            x2={padding.left + innerWidth}
                                            y1={y}
                                            y2={y}
                                            stroke="#e2e8f0"
                                            strokeDasharray="4 8"
                                        />
                                        <text
                                            x={padding.left - 8}
                                            y={y + 4}
                                            textAnchor="end"
                                            className="fill-slate-400 text-[11px]"
                                        >
                                            {tick}
                                        </text>
                                    </g>
                                );
                            })}

                            <line
                                x1={padding.left}
                                x2={padding.left + innerWidth}
                                y1={padding.top + innerHeight}
                                y2={padding.top + innerHeight}
                                stroke="#cbd5e1"
                            />

                            {points.length ? (
                                <>
                                    <polygon
                                        points={areaPoints}
                                        fill="url(#activity-area-fill)"
                                    />
                                    <polyline
                                        points={linePoints}
                                        fill="none"
                                        stroke="#0f172a"
                                        strokeWidth="3"
                                        strokeLinejoin="round"
                                        strokeLinecap="round"
                                    />

                                    {activeIndex !== null &&
                                    points[activeIndex] ? (
                                        <line
                                            x1={points[activeIndex].x}
                                            x2={points[activeIndex].x}
                                            y1={padding.top}
                                            y2={padding.top + innerHeight}
                                            stroke="#94a3b8"
                                            strokeDasharray="5 7"
                                        />
                                    ) : null}

                                    {points.map((point, index) => {
                                        const isActive = activeIndex === index;

                                        return (
                                            <g key={point.label}>
                                                <circle
                                                    cx={point.x}
                                                    cy={point.y}
                                                    r={isActive ? "7" : "4.5"}
                                                    fill="#ffffff"
                                                    stroke="#0f172a"
                                                    strokeWidth={
                                                        isActive ? "3" : "2.25"
                                                    }
                                                    className="cursor-pointer transition-all duration-150"
                                                    onMouseEnter={() =>
                                                        setActiveIndex(index)
                                                    }
                                                    onFocus={() =>
                                                        setActiveIndex(index)
                                                    }
                                                />

                                                <circle
                                                    cx={point.x}
                                                    cy={point.y}
                                                    r={isActive ? "16" : "0"}
                                                    fill="#0f172a"
                                                    opacity="0.10"
                                                    className="transition-all"
                                                />

                                                <circle
                                                    cx={point.x}
                                                    cy={point.y}
                                                    r="18"
                                                    fill="transparent"
                                                    className="cursor-pointer"
                                                    onMouseEnter={() =>
                                                        setActiveIndex(index)
                                                    }
                                                    onFocus={() =>
                                                        setActiveIndex(index)
                                                    }
                                                />

                                                {visibleXAxisLabels[index] ? (
                                                    <text
                                                        x={point.x}
                                                        y={height - 10}
                                                        textAnchor="middle"
                                                        className={
                                                            isActive
                                                                ? "fill-slate-900 text-[11px] font-medium"
                                                                : "fill-slate-500 text-[11px]"
                                                        }
                                                    >
                                                        {point.label}
                                                    </text>
                                                ) : null}
                                            </g>
                                        );
                                    })}
                                </>
                            ) : null}
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function AdminDashboard({ filters, dashboard }) {
    const applyFilters = (overrides) => {
        router.get(
            route("admin.dashboard"),
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
        revenue_usd: formatCurrency(tier.revenue_usd, "USD"),
        paid_payments_count: tier.paid_payments_count,
        unpaid_balance_usd: formatCurrency(tier.unpaid_balance_usd, "USD"),
    }));

    return (
        <AuthenticatedLayout>
            <Head title="Admin Dashboard" />

            <div className="px-4 py-8 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-7xl space-y-6">
                    <div className="rounded-3xl border border-slate-200 bg-white px-6 py-6 shadow-sm">
                        <div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                            <div className="space-y-2">
                                <Badge
                                    variant="outline"
                                    className="rounded-full"
                                >
                                    Admin Dashboard
                                </Badge>
                                <div>
                                    <h2 className="text-3xl font-semibold tracking-tight text-slate-900">
                                        Student and revenue snapshot
                                    </h2>
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
                                                student_scope:
                                                    event.target.value,
                                            })
                                        }
                                        className="h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700"
                                    >
                                        {dashboard.student_scope_options.map(
                                            (option) => (
                                                <option
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </option>
                                            ),
                                        )}
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
                                                activity_range:
                                                    event.target.value,
                                            })
                                        }
                                        className="h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700"
                                    >
                                        {dashboard.activity_range_options.map(
                                            (option) => (
                                                <option
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </option>
                                            ),
                                        )}
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
                                filters.student_scope === "active"
                                    ? "Filtered to students with an active login session."
                                    : "Counts all student accounts."
                            }
                        />
                        <MetricCard
                            label="Total Revenue"
                            value={formatCurrency(
                                dashboard.revenue_metrics.total_revenue_usd,
                                "USD",
                            )}
                        />
                    </div>

                    <div className="grid gap-6 xl:grid-cols-2">
                        <DashboardTable
                            title="Student Progress by Tier"
                            columns={[
                                { key: "tier_name", label: "Tier" },
                                {
                                    key: "total_students",
                                    label: "Total Students",
                                },
                                {
                                    key: "in_progress_students",
                                    label: "In Progress",
                                },
                                {
                                    key: "completed_students",
                                    label: "Completed",
                                },
                            ]}
                            rows={studentTableRows}
                            emptyMessage="No tier-based student data is available yet."
                        />

                        <DashboardTable
                            title="Revenue by Tier"
                            columns={[
                                { key: "tier_name", label: "Tier" },
                                { key: "revenue_usd", label: "Revenue" },
                                {
                                    key: "paid_payments_count",
                                    label: "Paid Payments",
                                },
                                {
                                    key: "unpaid_balance_usd",
                                    label: "Unpaid Balance",
                                },
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
