import DeleteConfirmationDialog from "@/Components/DeleteConfirmationDialog";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, usePage } from "@inertiajs/react";
import { ChevronLeft, ChevronRight, Plus, Search } from "lucide-react";
import { useState } from "react";

const PAGE_SIZE_OPTIONS = [5, 10, 25];

function buildAssetLabels(lesson) {
    return [
        lesson.has_workbook ? "Workbook" : null,
        lesson.has_lesson_video ? "Lesson Video" : null,
        lesson.has_audio ? "Audio" : null,
    ].filter(Boolean);
}

export default function LessonsIndex({ lessons, status }) {
    const errors = usePage().props.errors;
    const [search, setSearch] = useState("");
    const [pageSize, setPageSize] = useState(10);
    const [currentPage, setCurrentPage] = useState(1);

    const normalizedSearch = search.trim().toLowerCase();
    const filteredLessons = lessons.filter((lesson) => {
        if (!normalizedSearch) {
            return true;
        }

        return [
            lesson.title,
            lesson.module,
            lesson.scoreboard ?? "",
            lesson.access_tiers.join(" "),
            buildAssetLabels(lesson).join(" "),
        ]
            .join(" ")
            .toLowerCase()
            .includes(normalizedSearch);
    });

    const totalPages = Math.max(
        1,
        Math.ceil(filteredLessons.length / pageSize),
    );
    const safePage = Math.min(currentPage, totalPages);
    const paginatedLessons = filteredLessons.slice(
        (safePage - 1) * pageSize,
        safePage * pageSize,
    );
    const paginationStart = filteredLessons.length
        ? (safePage - 1) * pageSize + 1
        : 0;
    const paginationEnd = filteredLessons.length
        ? paginationStart + paginatedLessons.length - 1
        : 0;
    const visiblePages = Array.from({ length: totalPages }, (_, index) => {
        return index + 1;
    }).filter((page) => Math.abs(page - safePage) <= 1);

    const handleSearchChange = (event) => {
        setSearch(event.target.value);
        setCurrentPage(1);
    };

    const handlePageSizeChange = (event) => {
        setPageSize(Number(event.target.value));
        setCurrentPage(1);
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="min-w-0">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Lessons
                    </h2>
                    <p className="mt-1 text-sm text-gray-500">
                        Manage lesson records independently from module
                        navigation.
                    </p>
                </div>
            }
        >
            <Head title="Lessons" />

            <div className="py-8 sm:py-10">
                <div className="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                    {status === "lesson-created" && (
                        <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Lesson has been created.
                        </div>
                    )}
                    {status === "lesson-updated" && (
                        <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Lesson has been updated.
                        </div>
                    )}
                    {status === "lesson-deleted" && (
                        <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Lesson has been deleted.
                        </div>
                    )}
                    {errors.lesson && (
                        <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                            {errors.lesson}
                        </div>
                    )}

                    <section className="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-[0_18px_48px_rgba(15,23,42,0.08)]">
                        <div className="flex flex-col gap-4 border-b border-slate-200 px-5 py-5 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
                            <div className="space-y-2">
                                <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-400">
                                    Content Management
                                </p>
                                <div className="flex flex-wrap items-center gap-3">
                                    <h3 className="text-lg font-semibold text-slate-900">
                                        Lesson List
                                    </h3>
                                    <span className="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
                                        {filteredLessons.length} lesson
                                        {filteredLessons.length === 1
                                            ? ""
                                            : "s"}
                                    </span>
                                </div>
                            </div>

                            <div className="flex w-full flex-col gap-3 sm:flex-row sm:items-center lg:w-auto">
                                <div className="relative w-full sm:max-w-[320px]">
                                    <Search className="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                                    <Input
                                        type="search"
                                        value={search}
                                        onChange={handleSearchChange}
                                        placeholder="Search lessons"
                                        className="h-11 rounded-xl border-slate-200 bg-slate-50 pl-11 pr-4 text-sm text-slate-700 placeholder:text-slate-400 focus-visible:border-slate-300 focus-visible:ring-slate-200"
                                    />
                                </div>

                                <Button
                                    asChild
                                    size="lg"
                                    className="h-11 rounded-xl bg-black px-5 text-sm font-semibold text-white hover:bg-slate-800"
                                >
                                    <Link href={route("admin.lessons.create")}>
                                        <Plus className="size-4" />
                                        Create Lesson
                                    </Link>
                                </Button>
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="min-w-[980px] w-full table-fixed text-sm">
                                <thead className="bg-black">
                                    <tr>
                                        <th className="w-[28%] px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-white">
                                            Lesson
                                        </th>
                                        <th className="w-[16%] px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-white">
                                            Module
                                        </th>
                                        <th className="w-[18%] px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-white">
                                            Tiers
                                        </th>
                                        <th className="w-[8%] px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-white">
                                            Order
                                        </th>
                                        <th className="w-[14%] px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-white">
                                            Scoreboard
                                        </th>
                                        <th className="w-[16%] px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-white">
                                            Assets
                                        </th>
                                        <th className="w-[14%] px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-white">
                                            Action
                                        </th>
                                    </tr>
                                </thead>

                                <tbody className="divide-y divide-slate-100 bg-white">
                                    {paginatedLessons.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={7}
                                                className="px-6 py-16 text-center"
                                            >
                                                <div className="mx-auto max-w-sm space-y-2">
                                                    <p className="text-sm font-medium text-slate-900">
                                                        {search
                                                            ? "No lessons match your search."
                                                            : "No lessons available yet."}
                                                    </p>
                                                    <p className="text-sm text-slate-500">
                                                        {search
                                                            ? "Try a different keyword to find the lesson you need."
                                                            : "Create a lesson to start building the learning library."}
                                                    </p>
                                                </div>
                                            </td>
                                        </tr>
                                    ) : (
                                        paginatedLessons.map((lesson) => {
                                            const assetLabels =
                                                buildAssetLabels(lesson);

                                            return (
                                                <tr
                                                    key={lesson.id}
                                                    className="align-top transition-colors hover:bg-slate-50/80"
                                                >
                                                    <td className="px-6 py-5">
                                                        <div className="flex items-center gap-4">
                                                            {lesson.thumbnail_url ? (
                                                                <img
                                                                    src={
                                                                        lesson.thumbnail_url
                                                                    }
                                                                    alt={
                                                                        lesson.title
                                                                    }
                                                                    className="h-14 w-20 shrink-0 rounded-xl object-cover ring-1 ring-black/5"
                                                                />
                                                            ) : (
                                                                <div className="flex h-14 w-20 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-[10px] font-medium uppercase tracking-[0.18em] text-slate-500">
                                                                    No image
                                                                </div>
                                                            )}

                                                            <div className="min-w-0 space-y-1">
                                                                <p className="truncate text-sm font-semibold text-slate-900">
                                                                    {
                                                                        lesson.title
                                                                    }
                                                                </p>
                                                                <p className="text-xs text-slate-500">
                                                                    Learning
                                                                    content item
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </td>

                                                    <td className="px-6 py-5">
                                                        <div className="space-y-1">
                                                            <p className="line-clamp-2 text-sm font-medium text-slate-700">
                                                                {lesson.module}
                                                            </p>
                                                            <p className="text-xs text-slate-400">
                                                                Linked module
                                                            </p>
                                                        </div>
                                                    </td>

                                                    <td className="px-6 py-5">
                                                        <div className="flex flex-wrap gap-2">
                                                            {lesson.access_tiers
                                                                .length > 0 ? (
                                                                lesson.access_tiers.map(
                                                                    (tier) => (
                                                                        <span
                                                                            key={
                                                                                tier
                                                                            }
                                                                            className="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600"
                                                                        >
                                                                            {
                                                                                tier
                                                                            }
                                                                        </span>
                                                                    ),
                                                                )
                                                            ) : (
                                                                <span className="text-sm text-slate-400">
                                                                    None
                                                                </span>
                                                            )}
                                                        </div>
                                                    </td>

                                                    <td className="px-6 py-5 text-sm font-medium text-slate-700">
                                                        {lesson.sort_order}
                                                    </td>

                                                    <td className="px-6 py-5">
                                                        <span className="inline-flex max-w-full items-center rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-600">
                                                            <span className="truncate">
                                                                {lesson.scoreboard ??
                                                                    "None"}
                                                            </span>
                                                        </span>
                                                    </td>

                                                    <td className="px-6 py-5">
                                                        <div className="flex flex-wrap gap-2">
                                                            {assetLabels.length >
                                                            0 ? (
                                                                assetLabels.map(
                                                                    (asset) => (
                                                                        <span
                                                                            key={
                                                                                asset
                                                                            }
                                                                            className="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600"
                                                                        >
                                                                            {
                                                                                asset
                                                                            }
                                                                        </span>
                                                                    ),
                                                                )
                                                            ) : (
                                                                <span className="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-500">
                                                                    Basic
                                                                </span>
                                                            )}
                                                        </div>
                                                    </td>

                                                    <td className="px-6 py-5">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <Link
                                                                href={route(
                                                                    "admin.lessons.edit",
                                                                    lesson.id,
                                                                )}
                                                                className="inline-flex h-9 items-center justify-center rounded-lg border border-slate-200 px-3 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
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
                                                                triggerClassName="inline-flex h-9 items-center justify-center rounded-lg border border-rose-200 px-3 text-sm font-medium text-rose-600 transition hover:bg-rose-50 hover:text-rose-700"
                                                            />
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="flex flex-col gap-4 border-t border-slate-200 px-5 py-4 sm:px-6 xl:flex-row xl:items-center xl:justify-between">
                            <div className="flex flex-col gap-3 text-sm text-slate-500 sm:flex-row sm:items-center sm:gap-5">
                                <div>
                                    Showing {paginationStart}-{paginationEnd} of{" "}
                                    {filteredLessons.length} lessons
                                </div>
                                <label className="flex items-center gap-3 text-sm text-slate-600">
                                    <span>Data per page</span>
                                    <select
                                        value={pageSize}
                                        onChange={handlePageSizeChange}
                                        className="h-10 rounded-xl border border-slate-200 bg-white px-3 pr-9 text-sm text-slate-700 outline-none transition focus:border-slate-300"
                                    >
                                        {PAGE_SIZE_OPTIONS.map((option) => (
                                            <option key={option} value={option}>
                                                {option}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            </div>

                            <div className="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    onClick={() =>
                                        setCurrentPage((page) =>
                                            Math.max(1, page - 1),
                                        )
                                    }
                                    disabled={safePage === 1}
                                    className="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 px-4 text-sm font-medium text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                                >
                                    <ChevronLeft className="size-4" />
                                    Previous
                                </button>

                                {safePage > 2 && (
                                    <button
                                        type="button"
                                        onClick={() => setCurrentPage(1)}
                                        className="inline-flex h-10 min-w-10 items-center justify-center rounded-xl border border-slate-200 px-3 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
                                    >
                                        1
                                    </button>
                                )}

                                {safePage > 3 && (
                                    <span className="px-1 text-sm text-slate-400">
                                        ...
                                    </span>
                                )}

                                {visiblePages.map((page) => (
                                    <button
                                        key={page}
                                        type="button"
                                        onClick={() => setCurrentPage(page)}
                                        className={[
                                            "inline-flex h-10 min-w-10 items-center justify-center rounded-xl border px-3 text-sm font-medium transition",
                                            page === safePage
                                                ? "border-black bg-black text-white"
                                                : "border-slate-200 text-slate-600 hover:bg-slate-50",
                                        ].join(" ")}
                                    >
                                        {page}
                                    </button>
                                ))}

                                {safePage < totalPages - 2 && (
                                    <span className="px-1 text-sm text-slate-400">
                                        ...
                                    </span>
                                )}

                                {safePage < totalPages - 1 && (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setCurrentPage(totalPages)
                                        }
                                        className="inline-flex h-10 min-w-10 items-center justify-center rounded-xl border border-slate-200 px-3 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
                                    >
                                        {totalPages}
                                    </button>
                                )}

                                <button
                                    type="button"
                                    onClick={() =>
                                        setCurrentPage((page) =>
                                            Math.min(totalPages, page + 1),
                                        )
                                    }
                                    disabled={safePage === totalPages}
                                    className="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 px-4 text-sm font-medium text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                                >
                                    Next
                                    <ChevronRight className="size-4" />
                                </button>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
