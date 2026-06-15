import DeleteConfirmationDialog from '@/Components/DeleteConfirmationDialog';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { BookMarked, Search, Plus } from 'lucide-react';
import { useState } from 'react';

const PAGE_SIZE_OPTIONS = [5, 10, 25];

export default function EbooksIndex({ ebooks = [], status }) {
    const [search, setSearch]           = useState('');
    const [pageSize, setPageSize]       = useState(5);
    const [currentPage, setCurrentPage] = useState(1);

    const filtered = ebooks.filter((e) =>
        (e.title ?? '').toLowerCase().includes(search.toLowerCase())
    );

    const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
    const safePage   = Math.min(currentPage, totalPages);
    const paginated  = filtered.slice((safePage - 1) * pageSize, safePage * pageSize);

    const handleSearch   = (e) => { setSearch(e.target.value); setCurrentPage(1); };
    const handlePageSize = (e) => { setPageSize(Number(e.target.value)); setCurrentPage(1); };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-1">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">E-Books</h2>
                    <p className="text-sm text-gray-500">Manage downloadable ebook resources per access tier.</p>
                </div>
            }
        >
            <Head title="Ebooks" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">

                    {/* Status banners */}
                    {status === 'ebook-created' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">Ebook has been created.</div>
                    )}
                    {status === 'ebook-updated' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">Ebook has been updated.</div>
                    )}
                    {status === 'ebook-deleted' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">Ebook has been deleted.</div>
                    )}

                    {/* Card */}
                    <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">

                        {/* Card header */}
                        <div className="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-2">
                                <BookMarked className="h-5 w-5 text-gray-500 shrink-0" />
                                <span className="text-base font-semibold text-gray-800">E-Book List</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <div className="relative">
                                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                    <input
                                        type="text"
                                        placeholder="Search by title"
                                        value={search}
                                        onChange={handleSearch}
                                        className="h-10 w-52 rounded-lg border border-gray-200 bg-gray-50 pl-9 pr-3 text-sm text-gray-700 placeholder:text-gray-400 focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                    />
                                </div>
                                <Link
                                    href={route('admin.ebooks.create')}
                                    className="flex h-10 items-center gap-1.5 rounded-lg bg-gray-900 px-4 text-sm font-medium text-white hover:bg-gray-700 whitespace-nowrap"
                                >
                                    <Plus className="h-4 w-4" />
                                    Add E-Book
                                </Link>
                            </div>
                        </div>

                        {/* Table */}
                        <div className="overflow-x-auto">
                            <table className="w-full table-fixed text-sm">
                                <colgroup>
                                    <col style={{ width: '40px' }} />
                                    <col style={{ width: '48px' }} />
                                    <col style={{ width: '30%' }} />
                                    <col style={{ width: '20%' }} />
                                    <col style={{ width: '80px' }} />
                                    <col style={{ width: '100px' }} />
                                    <col style={{ width: '120px' }} />
                                </colgroup>
                                <thead>
                                    <tr className="bg-gray-900 text-white">
                                        <th className="px-3 py-3 text-left">
                                            <input type="checkbox" className="rounded border-gray-600 bg-gray-800 accent-indigo-500" onChange={() => {}} />
                                        </th>
                                        <th className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide">No</th>
                                        <th className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide">Title</th>
                                        <th className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide">Access Tier</th>
                                        <th className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide">Order</th>
                                        <th className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide">File</th>
                                        <th className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {paginated.length === 0 ? (
                                        <tr>
                                            <td colSpan={7} className="px-4 py-10 text-center text-sm text-gray-400">
                                                {search ? 'No ebooks match your search.' : 'No ebooks yet.'}
                                            </td>
                                        </tr>
                                    ) : (
                                        paginated.map((ebook, idx) => (
                                            <tr key={ebook.id} className="align-middle hover:bg-gray-50" style={{ height: '60px' }}>
                                                {/* Checkbox */}
                                                <td className="px-3 py-3">
                                                    <input type="checkbox" className="rounded border-gray-300 accent-indigo-500" />
                                                </td>
                                                {/* No */}
                                                <td className="px-3 py-3 text-gray-500">
                                                    {(safePage - 1) * pageSize + idx + 1}
                                                </td>
                                                {/* Title */}
                                                <td className="px-3 py-3">
                                                    <span className="font-medium text-gray-900 line-clamp-2">{ebook.title}</span>
                                                </td>
                                                {/* Access Tier */}
                                                <td className="px-3 py-3 text-gray-600">
                                                    {ebook.access_tiers?.join(', ') ?? '—'}
                                                </td>
                                                {/* Order */}
                                                <td className="px-3 py-3 text-gray-500">
                                                    {ebook.sort_order ?? '—'}
                                                </td>
                                                {/* File */}
                                                <td className="px-3 py-3">
                                                    {ebook.preview_url ? (
                                                        <a
                                                            href={ebook.preview_url}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            className="font-medium text-indigo-600 hover:text-indigo-800"
                                                        >
                                                            Preview
                                                        </a>
                                                    ) : (
                                                        <span className="text-gray-400">—</span>
                                                    )}
                                                </td>
                                                {/* Action — inline Edit + Delete */}
                                                <td className="px-3 py-3">
                                                    <div className="flex items-center gap-3">
                                                        <Link
                                                            href={route('admin.ebooks.edit', ebook.id)}
                                                            className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                                        >
                                                            Edit
                                                        </Link>
                                                        <DeleteConfirmationDialog
                                                            href={route('admin.ebooks.destroy', ebook.id)}
                                                            title="Delete ebook?"
                                                            description={`This will permanently delete "${ebook.title}". This action cannot be undone.`}
                                                        />
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination footer */}
                        <div className="flex items-center justify-between border-t border-gray-100 px-5 py-3">
                            <div className="flex items-center gap-2 text-sm text-gray-500">
                                <span>Data per page</span>
                                <select
                                    value={pageSize}
                                    onChange={handlePageSize}
                                    className="rounded border border-gray-200 bg-white px-2 py-1 pr-7 text-xs text-gray-700 focus:outline-none cursor-pointer"
                                >
                                    {PAGE_SIZE_OPTIONS.map((n) => (
                                        <option key={n} value={n}>{n}</option>
                                    ))}
                                </select>
                            </div>
                            <div className="flex items-center gap-1 text-sm">
                                <button
                                    onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                                    disabled={safePage === 1}
                                    className="rounded px-3 py-1.5 text-gray-500 hover:bg-gray-100 disabled:opacity-40"
                                >
                                    ‹ Previous
                                </button>
                                {Array.from({ length: totalPages }, (_, i) => i + 1).map((p) => (
                                    <button
                                        key={p}
                                        onClick={() => setCurrentPage(p)}
                                        className={`min-w-[32px] rounded px-2 py-1.5 text-sm font-medium ${
                                            p === safePage ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100'
                                        }`}
                                    >
                                        {p}
                                    </button>
                                ))}
                                <button
                                    onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                                    disabled={safePage === totalPages}
                                    className="rounded px-3 py-1.5 text-gray-500 hover:bg-gray-100 disabled:opacity-40"
                                >
                                    Next ›
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}