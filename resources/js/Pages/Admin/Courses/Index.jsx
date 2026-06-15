import DeleteConfirmationDialog from '@/Components/DeleteConfirmationDialog';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { Video, Search, Plus } from 'lucide-react';
import { useState } from 'react';

const PAGE_SIZE_OPTIONS = [5, 10, 25];

export default function CoursesIndex({ courses = [], status }) {
    const [search, setSearch]           = useState('');
    const [pageSize, setPageSize]       = useState(5);
    const [currentPage, setCurrentPage] = useState(1);

    const filtered = courses.filter((c) =>
        (c.title ?? '').toLowerCase().includes(search.toLowerCase()) ||
        (c.url_slug ?? '').toLowerCase().includes(search.toLowerCase())
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
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Courses</h2>
                    <p className="text-sm text-gray-500">Manage independent course resources outside the main module flow.</p>
                </div>
            }
        >
            <Head title="Video Lecture" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">

                    {status === 'course-created' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">Course has been created.</div>
                    )}
                    {status === 'course-updated' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">Course has been updated.</div>
                    )}
                    {status === 'course-deleted' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">Course has been deleted.</div>
                    )}
<<<<<<< HEAD
                    <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                        <div className="overflow-hidden rounded-xl border border-gray-200">
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="bg-gray-900">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-medium text-white">Course</th>
                                        <th className="px-4 py-3 text-left font-medium text-white">Tier</th>
                                        <th className="px-4 py-3 text-left font-medium text-white">Action</th>
=======

                    {/* Card */}
                    <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">

                        {/* Card header */}
                        <div className="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-2">
                                <Video className="h-5 w-5 text-gray-500 shrink-0" />
                                <span className="text-base font-semibold text-gray-800">Video List</span>
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
                                    href={route('admin.courses.create')}
                                    className="flex h-10 items-center gap-1.5 rounded-lg bg-gray-900 px-4 text-sm font-medium text-white hover:bg-gray-700 whitespace-nowrap"
                                >
                                    <Plus className="h-4 w-4" />
                                    Add Video
                                </Link>
                            </div>
                        </div>

                        {/* Table */}
                        <div className="overflow-x-auto">
                            <table className="w-full table-fixed text-sm">
                                <colgroup>
                                    <col style={{ width: '40px' }} />
                                    <col style={{ width: '48px' }} />
                                    <col style={{ width: '40%' }} />
                                    <col style={{ width: '25%' }} />
                                    <col style={{ width: '130px' }} />
                                </colgroup>
                                <thead>
                                    <tr className="bg-gray-900 text-white">
                                        <th className="px-3 py-3 text-left">
                                            <input type="checkbox" className="rounded border-gray-600 bg-gray-800 accent-indigo-500" onChange={() => {}} />
                                        </th>
                                        <th className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide">No</th>
                                        <th className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide">Course</th>
                                        <th className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide">Tier</th>
                                        <th className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide">Action</th>
>>>>>>> d210866db4dae9ce4bf7858119ec249467c747ad
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {paginated.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} className="px-4 py-10 text-center text-sm text-gray-400">
                                                {search ? 'No courses match your search.' : 'No courses yet.'}
                                            </td>
                                        </tr>
<<<<<<< HEAD
                                    ))}
                                    {courses.length === 0 && (
                                        <tr>
                                            <td colSpan={3} className="px-4 py-6 text-center text-gray-500">
                                                No courses yet.
                                            </td>
                                        </tr>
=======
                                    ) : (
                                        paginated.map((course, idx) => (
                                            <tr key={course.id} className="align-middle hover:bg-gray-50" style={{ height: '60px' }}>
                                                {/* Checkbox */}
                                                <td className="px-3 py-3">
                                                    <input type="checkbox" className="rounded border-gray-300 accent-indigo-500" />
                                                </td>
                                                {/* No */}
                                                <td className="px-3 py-3 text-gray-500">
                                                    {(safePage - 1) * pageSize + idx + 1}
                                                </td>
                                                {/* Course: thumbnail + title + slug */}
                                                <td className="px-3 py-3">
                                                    <div className="flex items-center gap-3">
                                                        {course.thumbnail_url ? (
                                                            <img
                                                                src={course.thumbnail_url}
                                                                alt={course.title}
                                                                className="h-10 w-14 rounded object-cover shrink-0"
                                                            />
                                                        ) : (
                                                            <div className="h-10 w-14 rounded bg-gray-100 shrink-0" />
                                                        )}
                                                        <div>
                                                            <div className="font-medium text-gray-900">{course.title}</div>
                                                            {course.url_slug && (
                                                                <div className="text-xs text-gray-400 mt-0.5">{course.url_slug}</div>
                                                            )}
                                                        </div>
                                                    </div>
                                                </td>
                                                {/* Tier */}
                                                <td className="px-3 py-3 text-gray-600">
                                                    {course.access_tier ?? '—'}
                                                </td>
                                                {/* Action */}
                                                <td className="px-3 py-3">
                                                    <div className="flex items-center gap-3">
                                                        <Link
                                                            href={route('admin.courses.edit', course.id)}
                                                            className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                                        >
                                                            Edit
                                                        </Link>
                                                        <DeleteConfirmationDialog
                                                            href={route('admin.courses.destroy', course.id)}
                                                            title="Delete course?"
                                                            description={`This will permanently delete "${course.title}". This action cannot be undone.`}
                                                        />
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
>>>>>>> d210866db4dae9ce4bf7858119ec249467c747ad
                                    )}
                                </tbody>
                            </table>
                        </div>
<<<<<<< HEAD
                        </div>
=======

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

>>>>>>> d210866db4dae9ce4bf7858119ec249467c747ad
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}