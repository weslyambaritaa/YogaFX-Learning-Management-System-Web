import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Search, UserPlus } from 'lucide-react';
import { useEffect, useState } from 'react';

function PhotoCell({ student }) {
    if (student.profile_photo) {
        return (
            <img
                src={student.profile_photo}
                alt={student.name}
                className="size-12 rounded-full object-cover ring-1 ring-slate-200"
            />
        );
    }

    return (
        <div className="flex size-12 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-600 ring-1 ring-slate-200">
            {student.profile_initials}
        </div>
    );
}

function StatusMessage({ status }) {
    const messages = {
        'student-account-created': 'Student account has been created.',
        'student-account-deleted': 'Student account has been deleted.',
    };

    if (!messages[status]) {
        return null;
    }

    return (
        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            {messages[status]}
        </div>
    );
}

function Pagination({ paginator }) {
    if (paginator.last_page <= 1) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-4">
            <p className="text-sm text-slate-500">
                Showing {paginator.from ?? 0} to {paginator.to ?? 0} of {paginator.total}{' '}
                students
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

export default function StudentsIndex({ students, accessTiers, filters, status }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [statusFilter, setStatusFilter] = useState(filters.status_filter ?? 'all');
    const [tierFilter, setTierFilter] = useState(filters.access_tier_id ?? '');
    const [perPage, setPerPage] = useState(String(filters.per_page ?? 10));

    useEffect(() => {
        setSearch(filters.search ?? '');
        setStatusFilter(filters.status_filter ?? 'all');
        setTierFilter(filters.access_tier_id ?? '');
        setPerPage(String(filters.per_page ?? 10));
    }, [filters]);

    const applyFilters = (overrides = {}) => {
        const query = {
            search,
            status_filter: statusFilter,
            access_tier_id: tierFilter,
            per_page: perPage,
            ...overrides,
        };

        router.get(route('admin.students.index'), query, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    const submitSearch = (event) => {
        event.preventDefault();
        applyFilters();
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="min-w-0">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Students
                    </h2>
                    <p className="mt-1 text-sm text-gray-500">
                        Manage student accounts, assign access tiers, and keep student
                        access under clear operational control.
                    </p>
                </div>
            }
        >
            <Head title="Students" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <StatusMessage status={status} />

                    <div className="overflow-hidden rounded-[5px] bg-white shadow-sm">
                        <div className="border-b border-slate-200 px-5 py-5">
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 className="text-lg font-semibold text-slate-900">
                                        Student Directory
                                    </h3>
                                    <p className="mt-1 text-sm text-slate-500">
                                        {students.total} student
                                        {students.total === 1 ? '' : 's'} found.
                                    </p>
                                </div>

                                <Button
                                    asChild
                                    className="w-full shrink-0 rounded-[5px] bg-gray-900 px-4 py-2 hover:bg-gray-800 sm:w-auto"
                                >
                                    <Link href={route('admin.students.create')}>
                                        <UserPlus className="mr-2 size-4" />
                                        Add Student
                                    </Link>
                                </Button>
                            </div>

                            <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,2fr)_160px_200px_120px]">
                                <form onSubmit={submitSearch} className="relative sm:col-span-2 lg:col-span-1">
                                    <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                                    <Input
                                        value={search}
                                        onChange={(event) => setSearch(event.target.value)}
                                        placeholder="Search by name or email..."
                                        className="h-10 rounded-[5px] pl-9"
                                    />
                                </form>

                                <select
                                    value={statusFilter}
                                    onChange={(event) => {
                                        const value = event.target.value;
                                        setStatusFilter(value);
                                        applyFilters({ status_filter: value, page: 1 });
                                    }}
                                    className="h-10 rounded-[5px] border border-slate-300 bg-white px-3 text-sm text-slate-700"
                                >
                                    <option value="all">All Status</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>

                                <select
                                    value={tierFilter}
                                    onChange={(event) => {
                                        const value = event.target.value;
                                        setTierFilter(value);
                                        applyFilters({ access_tier_id: value, page: 1 });
                                    }}
                                    className="h-10 rounded-[5px] border border-slate-300 bg-white px-3 text-sm text-slate-700"
                                >
                                    <option value="">All Access Tiers</option>
                                    {accessTiers.map((accessTier) => (
                                        <option key={accessTier.id} value={accessTier.id}>
                                            {accessTier.name}
                                            {!accessTier.is_active ? ' (Inactive)' : ''}
                                        </option>
                                    ))}
                                </select>

                                <select
                                    value={perPage}
                                    onChange={(event) => {
                                        const value = event.target.value;
                                        setPerPage(value);
                                        applyFilters({ per_page: value, page: 1 });
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
                                        <th className="px-4 py-3 text-left font-medium text-slate-700">
                                            No
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium text-slate-700">
                                            Photo
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium text-slate-700">
                                            Name
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium text-slate-700">
                                            Email
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium text-slate-700">
                                            Access Tier
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium text-slate-700">
                                            Status
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium text-slate-700">
                                            Registration Date
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium text-slate-700">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 bg-white">
                                    {students.data.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={8}
                                                className="px-4 py-10 text-center text-sm text-slate-500"
                                            >
                                                No students matched the current filters.
                                            </td>
                                        </tr>
                                    ) : (
                                        students.data.map((student) => (
                                            <tr key={student.id}>
                                                <td className="px-4 py-4 text-slate-600">
                                                    {student.number}
                                                </td>
                                                <td className="px-4 py-4">
                                                    <PhotoCell student={student} />
                                                </td>
                                                <td className="px-4 py-4 font-medium text-slate-900">
                                                    {student.name || 'Unnamed student'}
                                                </td>
                                                <td className="px-4 py-4 text-slate-700">
                                                    {student.email}
                                                </td>
                                                <td className="px-4 py-4 text-slate-700">
                                                    {student.access_tier_name}
                                                </td>
                                                <td className="px-4 py-4">
                                                    <Badge
                                                        variant={
                                                            student.is_active
                                                                ? 'secondary'
                                                                : 'outline'
                                                        }
                                                    >
                                                        {student.is_active
                                                            ? 'Active'
                                                            : 'Inactive'}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-4 text-slate-700">
                                                    {student.registration_date}
                                                </td>
                                                <td className="px-4 py-4">
                                                    <Button
                                                        asChild
                                                        variant="outline"
                                                        size="sm"
                                                        className="rounded-[5px]"
                                                    >
                                                        <Link
                                                            href={route(
                                                                'admin.students.edit',
                                                                student.id,
                                                            )}
                                                        >
                                                            Student Detail
                                                        </Link>
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <Pagination paginator={students} />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}