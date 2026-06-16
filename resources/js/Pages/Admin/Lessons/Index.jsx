import DeleteConfirmationDialog from "@/Components/DeleteConfirmationDialog";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, usePage } from "@inertiajs/react";
import { Search, Plus, BookOpen } from "lucide-react";
import { useState } from "react";

const PAGE_SIZE_OPTIONS = [5, 10, 25];

export default function LessonsIndex({ lessons = [], status }) {
    const errors = usePage().props.errors;

    const [search, setSearch] = useState("");
    const [pageSize, setPageSize] = useState(5);
    const [currentPage, setCurrentPage] = useState(1);

    // client-side filter
    const filtered = lessons.filter(
        (l) =>
            (l.title ?? "").toLowerCase().includes(search.toLowerCase()) ||
            (l.module ?? "").toLowerCase().includes(search.toLowerCase()),
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
                <div className="flex flex-col gap-1">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Lessons
                    </h2>
                    <p className="text-sm text-gray-500">
                        Manage lesson records independently from module
                        navigation.
                    </p>
                </div>
            }
        >
            <Head title="Lessons" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                    {/* Status banners */}
                    {status === "lesson-created" && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Lesson has been created.
                        </div>
                    )}
                    {status === "lesson-updated" && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Lesson has been updated.
                        </div>
                    )}
                    {status === "lesson-deleted" && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Lesson has been deleted.
                        </div>
                    )}
                    {errors.lesson && (
                        <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                            {errors.lesson}
                        </div>
                    )}

                    {/* Card */}
                    <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                        {/* Card header */}
                        <div className="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            {/* Left: icon + title */}
                            <div className="flex items-center gap-2">
                                <BookOpen className="h-5 w-5 text-gray-500 shrink-0" />
                                <span className="text-base font-semibold text-gray-800">
                                    Lesson List
                                </span>
                            </div>

                            {/* Right: search + add */}
                            <div className="flex items-center gap-2">
                                <div className="relative">
                                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                    <input
                                        type="text"
                                        placeholder="Search by title or module"
                                        value={search}
                                        onChange={handleSearch}
                                        className="h-10 w-56 rounded-lg border border-gray-200 bg-gray-50 pl-9 pr-3 text-sm text-gray-700 placeholder:text-gray-400 focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                    />
                                </div>
                                <Link
                                    href={route("admin.lessons.create")}
                                    className="flex h-10 items-center gap-1.5 rounded-lg bg-gray-900 px-4 text-sm font-medium text-white hover:bg-gray-700 whitespace-nowrap"
                                >
                                    <Plus className="h-4 w-4" />
                                    Add Lesson
                                </Link>
                            </div>
                        </div>

                        {/* Table */}
                        <div className="overflow-x-auto">
                            <table className="w-full table-fixed text-sm">
                                <colgroup>
                                    <col className="w-8" />
                                    <col className="w-10" />
                                    <col className="w-[18%]" />
                                    <col className="w-[14%]" />
                                    <col className="w-[16%]" />
                                    <col className="w-[18%]" />
                                    <col className="w-[14%]" />
                                    <col className="w-20" />
                                </colgroup>
                                <thead>
                                    <tr className="bg-gray-900 text-white">
                                        <th className="px-3 py-3 text-left">
                                            <input
                                                type="checkbox"
                                                className="rounded border-gray-600 bg-gray-800 accent-indigo-500"
                                                onChange={() => {}}
                                            />
                                        </th>
                                        <th className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide">
                                            No
                                        </th>
                                        <th className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide">
                                            Title
                                        </th>
                                        <th className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide">
                                            Thumbnail
                                        </th>
                                        <th className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide">
                                            Video
                                        </th>
                                        <th className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide">
                                            Access Tier
                                        </th>
                                        <th className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide">
                                            Assets
                                        </th>
                                        <th className="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide">
                                            Action
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {paginated.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={8}
                                                className="px-4 py-10 text-center text-sm text-gray-400"
                                            >
                                                {search
                                                    ? "No lessons match your search."
                                                    : "No lessons yet."}
                                            </td>
                                        </tr>
                                    ) : (
                                        paginated.map((lesson, idx) => (
                                            <tr
                                                key={lesson.id}
                                                className="align-middle hover:bg-gray-50"
                                                style={{ height: "60px" }}
                                            >
                                                {/* Checkbox */}
                                                <td className="px-3 py-3">
                                                    <input
                                                        type="checkbox"
                                                        className="rounded border-gray-300 accent-indigo-500"
                                                    />
                                                </td>
                                                {/* No */}
                                                <td className="px-3 py-3 text-gray-500">
                                                    {(safePage - 1) * pageSize +
                                                        idx +
                                                        1}
                                                </td>
                                                {/* Title */}
                                                <td className="px-3 py-3">
                                                    <span className="font-medium text-gray-900 line-clamp-2">
                                                        {lesson.title}
                                                    </span>
                                                </td>
                                                {/* Thumbnail */}
                                                <td className="px-3 py-3">
                                                    {lesson.thumbnail_url ? (
                                                        <div className="flex items-center gap-2">
                                                            <img
                                                                src={
                                                                    lesson.thumbnail_url
                                                                }
                                                                alt={
                                                                    lesson.title
                                                                }
                                                                className="h-8 w-12 rounded object-cover shrink-0"
                                                            />
                                                            <span className="truncate text-xs text-gray-400">
                                                                {
                                                                    lesson.thumbnail_url
                                                                        .split(
                                                                            "/",
                                                                        )
                                                                        .pop()
                                                                        .split(
                                                                            "?",
                                                                        )[0]
                                                                }
                                                            </span>
                                                        </div>
                                                    ) : (
                                                        <span className="text-xs text-gray-400">
                                                            —
                                                        </span>
                                                    )}
                                                </td>
                                                {/* Video */}
                                                <td className="px-3 py-3">
                                                    {lesson.has_lesson_video ? (
                                                        <span className="truncate text-xs text-gray-600">
                                                            {lesson.video_filename ??
                                                                "Video attached"}
                                                        </span>
                                                    ) : (
                                                        <span className="text-xs text-gray-400">
                                                            —
                                                        </span>
                                                    )}
                                                </td>
                                                {/* Access Tier */}
                                                <td className="px-3 py-3 text-gray-700">
                                                    <span className="line-clamp-2 text-xs">
                                                        {lesson.access_tiers?.join(
                                                            ", ",
                                                        ) ?? "—"}
                                                    </span>
                                                </td>
                                                {/* Assets */}
                                                <td className="px-3 py-3 text-gray-500 text-xs">
                                                    {[
                                                        lesson.has_workbook
                                                            ? "Workbook"
                                                            : null,
                                                        lesson.has_lesson_video
                                                            ? "Video"
                                                            : null,
                                                        lesson.has_audio
                                                            ? "Audio"
                                                            : null,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(", ") || "Basic"}
                                                </td>
                                                {/* Action */}
                                                <td className="px-3 py-3 text-right">
                                                    <div className="flex items-center justify-end gap-3">
                                                        <Link
                                                            href={route(
                                                                "admin.lessons.edit",
                                                                lesson.id,
                                                            )}
                                                            className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                                        >
                                                            Edit
                                                        </Link>
                                                        <DeleteConfirmationDialog
                                                            href={route(
                                                                "admin.lessons.destroy",
                                                                lesson.id,
                                                            )}
                                                            title="Delete lesson?"
                                                            description={`This will permanently delete "${lesson.title}". This action cannot be undone.`}
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
                            {/* Data per page */}
                            <div className="flex items-center gap-2 text-sm text-gray-500">
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

                            {/* Page buttons */}
                            <div className="flex items-center gap-1 text-sm">
                                <button
                                    onClick={() =>
                                        setCurrentPage((p) =>
                                            Math.max(1, p - 1),
                                        )
                                    }
                                    disabled={safePage === 1}
                                    className="rounded px-3 py-1.5 text-gray-500 hover:bg-gray-100 disabled:opacity-40"
                                >
                                    ‹ Previous
                                </button>
                                {Array.from(
                                    { length: totalPages },
                                    (_, i) => i + 1,
                                ).map((p) => (
                                    <button
                                        key={p}
                                        onClick={() => setCurrentPage(p)}
                                        className={`min-w-[32px] rounded px-2 py-1.5 text-sm font-medium ${
                                            p === safePage
                                                ? "bg-gray-900 text-white"
                                                : "text-gray-600 hover:bg-gray-100"
                                        }`}
                                    >
                                        {p}
                                    </button>
                                ))}
                                <button
                                    onClick={() =>
                                        setCurrentPage((p) =>
                                            Math.min(totalPages, p + 1),
                                        )
                                    }
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
