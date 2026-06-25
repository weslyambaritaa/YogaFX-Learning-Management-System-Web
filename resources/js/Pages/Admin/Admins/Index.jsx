import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Search, ShieldPlus } from 'lucide-react';
import { useEffect, useState } from 'react';

function StatusMessage({ status }) {
    const messages = {
        'admin-account-created': 'Admin account has been created.',
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
                admin accounts
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

export default function AdminsIndex({ admins, filters, status }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [scope, setScope] = useState(filters.scope ?? 'all');
    const [perPage, setPerPage] = useState(String(filters.per_page ?? 10));

    useEffect(() => {
        setSearch(filters.search ?? '');
        setScope(filters.scope ?? 'all');
        setPerPage(String(filters.per_page ?? 10));
    }, [filters]);

    const applyFilters = (overrides = {}) => {
        const query = {
            search,
            scope,
            per_page: perPage,
            ...overrides,
        };

        router.get(route('admin.admins.index'), query, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    const submitSearch = (event) => {
        event.preventDefault();
        applyFilters();
    };

    const deleteSelf = (admin) => {
        if (!window.confirm(`Delete your admin account "${admin.email}" permanently?`)) {
            return;
        }

        router.delete(route('admin.admins.destroy', admin.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="min-w-0">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Admin
                    </h2>
                    <p className="mt-1 text-sm text-gray-500">
                        Create admin accounts, review who has access to the admin side,
                        and manage your own account lifecycle safely.
                    </p>
                </div>
            }
        >
            <Head title="Admin" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <StatusMessage status={status} />

                    <div className="overflow-hidden rounded-lg bg-white shadow-sm">
                        <div className="border-b border-slate-200 px-4 py-4">
                            <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                                <div>
                                    <h3 className="text-lg font-semibold text-slate-900">
                                        Admin Directory
                                    </h3>
                                    <p className="mt-1 text-sm text-slate-500">
                                        {admins.total} admin account
                                        {admins.total === 1 ? '' : 's'} found.
                                    </p>
                                </div>

                                <Button asChild>
                                    <Link href={route('admin.admins.create')}>
                                        <ShieldPlus className="mr-2 size-4" />
                                        Add Admin
                                    </Link>
                                </Button>
                            </div>

                            <div className="mt-4 grid gap-3 lg:grid-cols-[minmax(0,2fr)_180px_120px]">
                                <form onSubmit={submitSearch} className="relative">
                                    <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                                    <Input
                                        value={search}
                                        onChange={(event) => setSearch(event.target.value)}
                                        placeholder="Search by name or email..."
                                        className="h-10 pl-9"
                                    />
                                </form>

                                <select
                                    value={scope}
                                    onChange={(event) => {
                                        const value = event.target.value;
                                        setScope(value);
                                        applyFilters({ scope: value, page: 1 });
                                    }}
                                    className="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700"
                                >
                                    <option value="all">All Admins</option>
                                    <option value="mine">My Account</option>
                                    <option value="others">Other Admins</option>
                                </select>

                                <select
                                    value={perPage}
                                    onChange={(event) => {
                                        const value = event.target.value;
                                        setPerPage(value);
                                        applyFilters({ per_page: value, page: 1 });
                                    }}
                                    className="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700"
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
                                            Name
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium text-slate-700">
                                            Email
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium text-slate-700">
                                            Scope
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium text-slate-700">
                                            Created Date
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium text-slate-700">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 bg-white">
                                    {admins.data.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="px-4 py-10 text-center text-sm text-slate-500"
                                            >
                                                No admin accounts matched the current filters.
                                            </td>
                                        </tr>
                                    ) : (
                                        admins.data.map((admin) => (
                                            <tr key={admin.id}>
                                                <td className="px-4 py-4 text-slate-600">
                                                    {admin.number}
                                                </td>
                                                <td className="px-4 py-4 font-medium text-slate-900">
                                                    {admin.name}
                                                </td>
                                                <td className="px-4 py-4 text-slate-700">
                                                    {admin.email}
                                                </td>
                                                <td className="px-4 py-4">
                                                    <Badge
                                                        variant={
                                                            admin.is_self
                                                                ? 'secondary'
                                                                : 'outline'
                                                        }
                                                    >
                                                        {admin.is_self ? 'You' : 'Admin'}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-4 text-slate-700">
                                                    {admin.created_at}
                                                </td>
                                                <td className="px-4 py-4">
                                                    {admin.is_self ? (
                                                        <Button
                                                            type="button"
                                                            variant="destructive"
                                                            size="sm"
                                                            onClick={() => deleteSelf(admin)}
                                                        >
                                                            Delete My Account
                                                        </Button>
                                                    ) : (
                                                        <span className="text-sm text-slate-400">
                                                            No actions
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <Pagination paginator={admins} />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
