import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatCurrency } from '@/lib/currency';
import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';

function formatDateTime(value) {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function statusBadgeClass(status) {
    const styles = {
        unpaid: 'bg-amber-100 text-amber-800',
        pending: 'bg-amber-100 text-amber-800',
        paid_full: 'bg-emerald-100 text-emerald-800',
        success: 'bg-emerald-100 text-emerald-800',
        installment: 'bg-sky-100 text-sky-800',
        upgraded: 'bg-indigo-100 text-indigo-800',
        failed: 'bg-rose-100 text-rose-800',
        cancelled: 'bg-slate-200 text-slate-700',
    };

    return styles[status] ?? 'bg-slate-100 text-slate-700';
}

function Pagination({ paginator }) {
    if (paginator.last_page <= 1) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-4">
            <p className="text-sm text-slate-500">
                Showing {paginator.from ?? 0} to {paginator.to ?? 0} of {paginator.total} records
            </p>

            <div className="flex flex-wrap items-center gap-2">
                {paginator.links.map((link, index) => (
                    <button
                        key={`${link.label}-${index}`}
                        type="button"
                        disabled={!link.url}
                        onClick={() =>
                            link.url
                                ? router.visit(link.url, {
                                      preserveScroll: true,
                                      preserveState: true,
                                  })
                                : null
                        }
                        className={[
                            'rounded-md border px-3 py-1.5 text-sm transition',
                            link.active
                                ? 'border-slate-900 bg-slate-900 text-white'
                                : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50',
                            !link.url ? 'cursor-not-allowed opacity-50' : '',
                        ].join(' ')}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ))}
            </div>
        </div>
    );
}

function DetailModal({ open, onOpenChange, title, description, fields }) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] max-w-3xl overflow-hidden border border-slate-200 bg-white p-0 text-slate-900 shadow-2xl ring-1 ring-black/5">
                <DialogHeader className="gap-3 border-b border-slate-200 px-6 py-5">
                    <DialogTitle className="text-lg font-semibold text-slate-950">
                        {title}
                    </DialogTitle>
                    <DialogDescription className="text-sm leading-6 text-slate-600">
                        {description}
                    </DialogDescription>
                </DialogHeader>

                <div className="max-h-[60vh] overflow-y-auto px-6 py-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        {fields.map((field) => (
                            <div
                                key={field.label}
                                className="rounded-xl border border-slate-200 bg-slate-50/70 px-4 py-3"
                            >
                                <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                                    {field.label}
                                </p>
                                <p className="mt-2 text-sm font-medium text-slate-900 break-words">
                                    {field.value === null || field.value === undefined || field.value === ''
                                        ? '-'
                                        : field.value}
                                </p>
                            </div>
                        ))}
                    </div>
                </div>

                <DialogFooter className="border-t border-slate-200 bg-slate-50/80 px-6 py-4">
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function CommerceSection({
    indexRoute,
    section,
    tierOptions,
    statusOptions,
    columns,
    detailTitle,
    detailDescription,
    buildDetailFields,
    baseQuery,
}) {
    const [search, setSearch] = useState(section.filters.search ?? '');
    const [status, setStatus] = useState(section.filters.status ?? '');
    const [tier, setTier] = useState(section.filters.tier ? String(section.filters.tier) : '');
    const [perPage, setPerPage] = useState(String(section.filters.per_page ?? 10));
    const [selectedRow, setSelectedRow] = useState(null);

    useEffect(() => {
        setSearch(section.filters.search ?? '');
        setStatus(section.filters.status ?? '');
        setTier(section.filters.tier ? String(section.filters.tier) : '');
        setPerPage(String(section.filters.per_page ?? 10));
    }, [section.filters]);

    const applyFilters = (overrides = {}) => {
        router.get(
            route(indexRoute),
            {
                ...baseQuery,
                [`${section.key}_search`]: search,
                [`${section.key}_status`]: status,
                [`${section.key}_tier`]: tier,
                [`${section.key}_per_page`]: perPage,
                ...overrides,
            },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    };

    const submitSearch = (event) => {
        event.preventDefault();
        applyFilters({ [`${section.key}_page`]: 1 });
    };

    return (
        <>
            <section className="overflow-hidden rounded-[5px] bg-white shadow-sm">
                <div className="border-b border-slate-200 px-5 py-5">
                    <div>
                        <h3 className="text-lg font-semibold text-slate-900">
                            {section.title}
                        </h3>
                        <p className="mt-1 text-sm text-slate-500">
                            {section.description}
                        </p>
                        <p className="mt-3 text-sm font-medium text-slate-700">
                            {section.rows.total} record{section.rows.total === 1 ? '' : 's'} found.
                        </p>
                    </div>

                    <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,2fr)_180px_180px_120px]">
                        <form onSubmit={submitSearch} className="relative sm:col-span-2 lg:col-span-1">
                            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                            <Input
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Search records..."
                                className="h-10 rounded-[5px] pl-9"
                            />
                        </form>

                        <select
                            value={status}
                            onChange={(event) => {
                                const nextValue = event.target.value;
                                setStatus(nextValue);
                                applyFilters({
                                    [`${section.key}_status`]: nextValue,
                                    [`${section.key}_page`]: 1,
                                });
                            }}
                            className="h-10 rounded-[5px] border border-slate-300 bg-white px-3 text-sm text-slate-700"
                        >
                            <option value="">All Status</option>
                            {statusOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>

                        <select
                            value={tier}
                            onChange={(event) => {
                                const nextValue = event.target.value;
                                setTier(nextValue);
                                applyFilters({
                                    [`${section.key}_tier`]: nextValue,
                                    [`${section.key}_page`]: 1,
                                });
                            }}
                            className="h-10 rounded-[5px] border border-slate-300 bg-white px-3 text-sm text-slate-700"
                        >
                            <option value="">All Tiers</option>
                            {tierOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>

                        <select
                            value={perPage}
                            onChange={(event) => {
                                const nextValue = event.target.value;
                                setPerPage(nextValue);
                                applyFilters({
                                    [`${section.key}_per_page`]: nextValue,
                                    [`${section.key}_page`]: 1,
                                });
                            }}
                            className="h-10 rounded-[5px] border border-slate-300 bg-white px-3 text-sm text-slate-700"
                        >
                            <option value="10">10 / page</option>
                            <option value="25">25 / page</option>
                            <option value="50">50 / page</option>
                        </select>
                    </div>
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
                            {section.rows.data.length ? (
                                section.rows.data.map((row) => (
                                    <tr key={row.id} className="align-top">
                                        {columns.map((column) => (
                                            <td key={`${row.id}-${column.key}`} className="px-4 py-3 text-slate-700">
                                                {column.render(row, setSelectedRow)}
                                            </td>
                                        ))}
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td
                                        colSpan={columns.length}
                                        className="px-4 py-10 text-center text-sm text-slate-500"
                                    >
                                        No records found for this section.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={section.rows} />
            </section>

            <DetailModal
                open={selectedRow !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSelectedRow(null);
                    }
                }}
                title={selectedRow ? detailTitle(selectedRow) : ''}
                description={selectedRow ? detailDescription(selectedRow) : ''}
                fields={selectedRow ? buildDetailFields(selectedRow) : []}
            />
        </>
    );
}

export default function CommerceIndexPage({
    title,
    heading,
    description,
    indexRoute,
    sections,
    tierOptions,
    statusOptions,
    columns,
    detailTitle,
    detailDescription,
    buildDetailFields,
}) {
    const baseQuery = Object.values(sections).reduce((carry, section) => {
        carry[`${section.key}_search`] = section.filters.search ?? '';
        carry[`${section.key}_status`] = section.filters.status ?? '';
        carry[`${section.key}_tier`] = section.filters.tier ?? '';
        carry[`${section.key}_per_page`] = section.filters.per_page ?? 10;
        carry[`${section.key}_page`] = section.rows.current_page ?? 1;

        return carry;
    }, {});

    return (
        <AuthenticatedLayout
            header={
                <div className="min-w-0">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        {heading}
                    </h2>
                    <p className="mt-1 text-sm text-gray-500">
                        {description}
                    </p>
                </div>
            }
        >
            <Head title={title} />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {Object.values(sections).map((section) => (
                        <CommerceSection
                            key={section.key}
                            indexRoute={indexRoute}
                            section={section}
                            tierOptions={tierOptions}
                            statusOptions={statusOptions}
                            columns={columns}
                            detailTitle={detailTitle}
                            detailDescription={detailDescription}
                            buildDetailFields={buildDetailFields}
                            baseQuery={baseQuery}
                        />
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

export { formatCurrency, formatDateTime, statusBadgeClass };
