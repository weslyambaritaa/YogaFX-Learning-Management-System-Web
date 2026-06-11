import DeleteConfirmationDialog from "@/Components/DeleteConfirmationDialog";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Input } from "@/Components/ui/input";
import { Button } from "@/Components/ui/button";
import { Head, Link, usePage } from "@inertiajs/react";
import { Search, Plus } from "lucide-react";
import { useState } from "react";

const PAGE_SIZE_OPTIONS = [5, 10, 25];

export default function ModulesIndex({ modules, status }) {
    const errors = usePage().props.errors;

    // — search (client-side filter) —
    const [search, setSearch] = useState("");
    // — pagination —
    const [pageSize, setPageSize] = useState(5);
    const [currentPage, setCurrentPage] = useState(1);

    // filter by title or thumbnail name
    const filtered = modules.filter(
        (m) =>
            m.title.toLowerCase().includes(search.toLowerCase()) ||
            (m.url_slug ?? "").toLowerCase().includes(search.toLowerCase()),
    );

    const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
    const safePage = Math.min(currentPage, totalPages);
    const paginated = filtered.slice(
        (safePage - 1) * pageSize,
        safePage * pageSize,
    );

    const handleSearch = (e) => {
        setSearch(e.target.value);
        setCurrentPage(1);
    };

    const handlePageSize = (e) => {
        setPageSize(Number(e.target.value));
        setCurrentPage(1);
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="min-w-0">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Modules
                    </h2>
                    <p className="mt-1 text-sm text-gray-500">
                        Manage the primary learning containers for YogaFX
                        students.
                    </p>
                </div>
            }
        >
            <Head title="Modules" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                    {/* Status messages */}
                    {status === "module-created" && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Module has been created.
                        </div>
                    )}
                    {status === "module-updated" && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Module has been updated.
                        </div>
                    )}
                    {status === "module-deleted" && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Module has been deleted.
                        </div>
                    )}
                    {errors.module && (
                        <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                            {errors.module}
                        </div>
                    )}

                    {/* Card */}
                    <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                        {/* Table header: title + search + add button */}
                        <div className="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-2 text-sm font-semibold text-gray-800">
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    className="size-4 text-gray-500"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    strokeWidth={2}
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        d="M4 6h16M4 10h16M4 14h10"
                                    />
                                </svg>
                                Module List
                            </div>
                            <div className="flex items-center gap-2">
                                <div className="relative">
                                    <Search className="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-gray-400" />
                                    <input
                                        type="text"
                                        placeholder="Search by name or email"
                                        value={search}
                                        onChange={handleSearch}
                                        className="h-8 rounded-md border border-gray-200 bg-gray-50 pl-8 pr-3 text-xs text-gray-700 placeholder-gray-400 focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300 w-56"
                                    />
                                </div>
                                <Link
                                    href={route("admin.modules.create")}
                                    className="inline-flex h-8 items-center gap-1.5 rounded-md bg-gray-900 px-3 text-xs font-medium text-white hover:bg-gray-700"
                                >
                                    <Plus className="size-3.5" />
                                    Add modules
                                </Link>
                            </div>
                        </div>

                        {/* Table */}
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                {/* Dark header */}
                                <thead>
                                    <tr className="bg-gray-900 text-white">
                                        <th className="w-10 px-4 py-3 text-left">
                                            <input
                                                type="checkbox"
                                                className="rounded border-gray-600 bg-gray-800 accent-indigo-500"
                                                onChange={() => {}}
                                            />
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide">
                                            Module
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide">
                                            Tiers
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide">
                                            Order
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide">
                                            Lessons
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide">
                                            Action
                                        </th>
                                    </tr>
                                </thead>

                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {paginated.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="px-4 py-8 text-center text-gray-400"
                                            >
                                                {search
                                                    ? "No modules match your search."
                                                    : "No modules yet."}
                                            </td>
                                        </tr>
                                    ) : (
                                        paginated.map((module, idx) => (
                                            <tr
                                                key={module.id}
                                                className="hover:bg-gray-50"
                                            >
                                                <td className="px-4 py-3">
                                                    <input
                                                        type="checkbox"
                                                        className="rounded border-gray-300 accent-indigo-500"
                                                    />
                                                </td>
                                                {/* Module: thumbnail + title + slug */}
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-3">
                                                        {module.thumbnail_url ? (
                                                            <img
                                                                src={
                                                                    module.thumbnail_url
                                                                }
                                                                alt={
                                                                    module.title
                                                                }
                                                                className="h-10 w-14 rounded object-cover shrink-0"
                                                            />
                                                        ) : (
                                                            <div className="h-10 w-14 rounded bg-gray-100 shrink-0" />
                                                        )}
                                                        <div>
                                                            <div className="font-medium text-gray-900">
                                                                {module.title}
                                                            </div>
                                                            {module.url_slug && (
                                                                <div className="text-xs text-gray-400">
                                                                    {
                                                                        module.url_slug
                                                                    }
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                </td>
                                                {/* Tiers */}
                                                <td className="px-4 py-3 text-gray-700">
                                                    {module.access_tiers.join(
                                                        ", ",
                                                    )}
                                                </td>
                                                {/* Order */}
                                                <td className="px-4 py-3 text-gray-500">
                                                    {module.order ??
                                                        (safePage - 1) *
                                                            pageSize +
                                                            idx +
                                                            1}
                                                </td>
                                                {/* Lessons count */}
                                                <td className="px-4 py-3 text-gray-500">
                                                    {module.lessons_count ??
                                                        module.lessons
                                                            ?.length ??
                                                        "—"}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-3">
                                                        <Link
                                                            href={route(
                                                                "admin.modules.edit",
                                                                module.id,
                                                            )}
                                                            className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                                        >
                                                            Edit
                                                        </Link>
                                                        <DeleteConfirmationDialog
                                                            href={route(
                                                                "admin.modules.destroy",
                                                                module.id,
                                                            )}
                                                            title="Delete module?"
                                                            description={`This will permanently delete "${module.title}". This action cannot be undone.`}
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
                        <div className="flex flex-col gap-3 border-t border-gray-200 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-2 text-xs text-gray-500">
                                <span>Data per page</span>
                                <select
                                    value={pageSize}
                                    onChange={handlePageSize}
                                    className="rounded border border-gray-200 bg-white px-2 py-1 pr-7 text-xs text-gray-700 focus:outline-none cursor-pointer"
                                >
                                    {PAGE_SIZE_OPTIONS.map((n) => (
                                        <option key={n} value={n}>
                                            {n}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="flex items-center gap-1 text-xs">
                                <button
                                    onClick={() =>
                                        setCurrentPage((p) =>
                                            Math.max(1, p - 1),
                                        )
                                    }
                                    disabled={safePage === 1}
                                    className="flex items-center gap-1 rounded border border-gray-200 px-3 py-1.5 text-gray-600 hover:bg-gray-50 disabled:opacity-40"
                                >
                                    ‹ Previous
                                </button>

                                {Array.from(
                                    { length: totalPages },
                                    (_, i) => i + 1,
                                ).map((page) => (
                                    <button
                                        key={page}
                                        onClick={() => setCurrentPage(page)}
                                        className={[
                                            "min-w-[28px] rounded border px-2 py-1.5",
                                            page === safePage
                                                ? "border-gray-900 bg-gray-900 font-semibold text-white"
                                                : "border-gray-200 text-gray-600 hover:bg-gray-50",
                                        ].join(" ")}
                                    >
                                        {page}
                                    </button>
                                ))}

                                <button
                                    onClick={() =>
                                        setCurrentPage((p) =>
                                            Math.min(totalPages, p + 1),
                                        )
                                    }
                                    disabled={safePage === totalPages}
                                    className="flex items-center gap-1 rounded border border-gray-200 px-3 py-1.5 text-gray-600 hover:bg-gray-50 disabled:opacity-40"
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
