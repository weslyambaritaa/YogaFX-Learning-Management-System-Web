import DeleteConfirmationDialog from '@/Components/DeleteConfirmationDialog';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useAdminTableReorder from '@/lib/useAdminTableReorder';
import { Head, Link, usePage } from '@inertiajs/react';
import { BookOpen, Search, Plus, CheckCircle2, XCircle, ChevronLeft, ChevronRight, GripVertical } from 'lucide-react';
import { useState } from 'react';

function FlashMessage({ status, errors }) {
    if (['module-created', 'module-updated', 'module-deleted'].includes(status)) {
        const msg = {
            'module-created': 'Module has been created.',
            'module-updated': 'Module has been updated.',
            'module-deleted': 'Module has been deleted.',
        };
        return (
            <div className="flex items-center gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                <CheckCircle2 className="size-4 shrink-0 text-emerald-500" />
                {msg[status]}
            </div>
        );
    }
    if (errors?.module) {
        return (
            <div className="flex items-center gap-2.5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <XCircle className="size-4 shrink-0 text-rose-500" />
                {errors.module}
            </div>
        );
    }
    return null;
}

const PAGE_SIZE_OPTIONS = [5, 10, 25];

export default function ModulesIndex({ modules, status }) {
    const errors = usePage().props.errors;
    const [search, setSearch] = useState('');
    const [pageSize, setPageSize] = useState(10);
    const [currentPage, setCurrentPage] = useState(1);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    const {
        items: orderedModules,
        draggedItemId,
        dropTarget,
        isSaving,
        getRowProps,
        getHandleProps,
    } = useAdminTableReorder({
        items: modules,
        enabled: true,
        onCommit: async ({ sourceId, targetId, position }) => {
            const response = await fetch(route('admin.modules.reorder'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    source_id: sourceId,
                    target_id: targetId,
                    position,
                }),
            });

            if (!response.ok) {
                throw new Error('Failed to reorder modules.');
            }
        },
    });

    const filtered = orderedModules.filter((module) =>
        module.title.toLowerCase().includes(search.toLowerCase()),
    );

    const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
    const safePage = Math.min(currentPage, totalPages);
    const paginated = filtered.slice((safePage - 1) * pageSize, safePage * pageSize);

    const goTo = (p) => setCurrentPage(Math.max(1, Math.min(p, totalPages)));
    const handleSearch = (v) => { setSearch(v); setCurrentPage(1); };
    const handlePageSize = (v) => { setPageSize(Number(v)); setCurrentPage(1); };

    const pageNumbers = () => {
        const pages = [];
        const left = Math.max(1, safePage - 2);
        const right = Math.min(totalPages, safePage + 2);
        for (let i = left; i <= right; i++) pages.push(i);
        return pages;
    };

    const from = filtered.length === 0 ? 0 : (safePage - 1) * pageSize + 1;
    const to = Math.min(safePage * pageSize, filtered.length);

    return (
        <AuthenticatedLayout>
            <Head title="Modules" />

            <div className="py-10">
                <div className="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">

                    <FlashMessage status={status} errors={errors} />

                    <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-2.5">
                                <BookOpen className="size-4 text-slate-400" />
                                <span className="text-sm font-semibold text-slate-700">Module List</span>
                                <span className="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] font-medium text-slate-500">
                                    {modules.length}
                                </span>
                            </div>
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-slate-400" />
                                    <input
                                        type="text"
                                        placeholder="Search by name..."
                                        value={search}
                                        onChange={(e) => handleSearch(e.target.value)}
                                        className="h-9 w-full rounded-lg border border-slate-200 bg-slate-50 pl-8 pr-3 text-sm text-slate-700 placeholder-slate-400 outline-none focus:border-slate-400 focus:bg-white sm:w-60"
                                    />
                                </div>
                                <Link
                                    href={route('admin.modules.create')}
                                    className="inline-flex h-9 items-center gap-1.5 rounded-lg bg-slate-900 px-4 text-sm font-medium text-white transition hover:bg-slate-700 whitespace-nowrap"
                                >
                                    <Plus className="size-3.5" />
                                    Add Module
                                </Link>
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 bg-slate-50">
                                        {['Move', 'Module', 'Tiers', 'Order', 'Lessons', 'Assignments', 'Certificate', 'Ebook', 'Video Lecturer', 'Action'].map((col) => (
                                            <th
                                                key={col}
                                                className="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.06em] text-slate-500 whitespace-nowrap"
                                            >
                                                {col}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 bg-white">
                                    {paginated.length === 0 ? (
                                        <tr>
                                            <td colSpan={10} className="px-4 py-12 text-center text-sm text-slate-400">
                                                No modules found.
                                            </td>
                                        </tr>
                                    ) : (
                                        paginated.map((module) => (
                                            <tr
                                                key={module.id}
                                                {...getRowProps(module.id)}
                                                className={[
                                                    'align-top transition-colors hover:bg-slate-50/70',
                                                    draggedItemId === module.id ? 'bg-slate-100/80 opacity-70' : '',
                                                    dropTarget?.id === module.id && dropTarget.position === 'before' ? 'border-t-2 border-slate-900' : '',
                                                    dropTarget?.id === module.id && dropTarget.position === 'after' ? 'border-b-2 border-slate-900' : '',
                                                ].join(' ')}
                                            >
                                                <td className="px-4 py-4 align-top">
                                                    <button
                                                        type="button"
                                                        {...getHandleProps(module.id)}
                                                        disabled={isSaving}
                                                        className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:border-slate-300 hover:text-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
                                                        aria-label={`Reorder ${module.title}`}
                                                        title="Drag to reorder"
                                                    >
                                                        <GripVertical className="size-4" />
                                                    </button>
                                                </td>
                                                <td className="px-4 py-4">
                                                    <div className="flex items-start gap-4">
                                                        <img
                                                            src={module.thumbnail_url}
                                                            alt={module.title}
                                                            className="h-14 w-20 shrink-0 rounded-md object-cover"
                                                        />
                                                        <div>
                                                            <div className="font-medium text-gray-900">{module.title}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-4 text-gray-700 align-top">{module.access_tiers.join(', ')}</td>
                                                <td className="px-4 py-4 text-gray-700 align-top">{module.sort_order}</td>
                                                <td className="px-4 py-4 text-gray-700 align-top">{module.lessons_count}</td>
                                                <td className="px-4 py-4 text-gray-700 align-top">{module.assignments_count}</td>
                                                <td className="px-4 py-4 text-gray-700 align-top whitespace-nowrap">{module.certificate_enabled ? 'Checked' : 'Not checked'}</td>
                                                <td className="px-4 py-4 text-gray-700 align-top whitespace-nowrap">{module.ebook_enabled ? 'Checked' : 'Not checked'}</td>
                                                <td className="px-4 py-4 text-gray-700 align-top whitespace-nowrap">{module.video_lecturer_enabled ? 'Checked' : 'Not checked'}</td>
                                                <td className="px-4 py-4 align-top">
                                                    <div className="flex flex-col items-start gap-2">
                                                        <Link
                                                            href={route('admin.modules.assignments.index', module.id)}
                                                            className="text-sm font-medium text-indigo-600 transition-colors hover:text-indigo-800"
                                                        >
                                                            Assignments
                                                        </Link>
                                                        <Link
                                                            href={route('admin.modules.edit', module.id)}
                                                            className="text-sm font-medium text-indigo-600 transition-colors hover:text-indigo-800"
                                                        >
                                                            Edit
                                                        </Link>
                                                        <DeleteConfirmationDialog
                                                            href={route('admin.modules.destroy', module.id)}
                                                            title="Delete module?"
                                                            description={`This will permanently delete "${module.title}". This action cannot be undone.`}
                                                            triggerClassName="text-sm font-medium text-rose-600 transition-colors hover:text-rose-800"
                                                        />
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="flex flex-col gap-3 border-t border-slate-100 bg-slate-50 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-2 text-xs text-slate-500">
                                <span className="rounded-full border border-slate-200 bg-white px-2 py-1 text-[11px] font-medium text-slate-500">
                                    {isSaving ? 'Saving order...' : 'Drag rows by the handle to reorder'}
                                </span>
                                <span>Rows per page</span>
                                <div className="flex items-center gap-1">
                                    {PAGE_SIZE_OPTIONS.map((size) => (
                                        <button
                                            key={size}
                                            onClick={() => handlePageSize(size)}
                                            className={[
                                                'h-7 min-w-[32px] rounded-lg border px-2 text-xs font-medium transition',
                                                pageSize === size
                                                    ? 'border-slate-900 bg-slate-900 text-white'
                                                    : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-100',
                                            ].join(' ')}
                                        >
                                            {size}
                                        </button>
                                    ))}
                                </div>
                                <span className="ml-1 text-slate-400">
                                    {filtered.length === 0 ? '0 results' : `${from}-${to} of ${filtered.length}`}
                                </span>
                            </div>

                            <div className="flex items-center gap-1">
                                <button
                                    onClick={() => goTo(safePage - 1)}
                                    disabled={safePage === 1}
                                    className="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
                                >
                                    <ChevronLeft className="size-3.5" />
                                </button>
                                {pageNumbers().map((p) => (
                                    <button
                                        key={p}
                                        onClick={() => goTo(p)}
                                        className={[
                                            'h-7 min-w-[28px] rounded-lg border px-1.5 text-xs font-medium transition',
                                            p === safePage
                                                ? 'border-slate-900 bg-slate-900 text-white'
                                                : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-100',
                                        ].join(' ')}
                                    >
                                        {p}
                                    </button>
                                ))}
                                <button
                                    onClick={() => goTo(safePage + 1)}
                                    disabled={safePage === totalPages}
                                    className="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
                                >
                                    <ChevronRight className="size-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
