import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { usePersistedPage } from '@/lib/useIndexPageMemory';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    MoreHorizontal,
    Search,
    UserPlus,
} from 'lucide-react';
import { useMemo, useState } from 'react';

const PAGE_SIZE_OPTIONS = [5, 10, 25];

function PhotoCell({ student }) {
    if (student.profile_photo) {
        return (
            <Link href={route('admin.student-progress.students.show', student.id)}>
                <img
                    src={student.profile_photo}
                    alt={student.name}
                    className="size-12 rounded-full object-cover ring-1 ring-slate-200 transition hover:ring-slate-300"
                />
            </Link>
        );
    }

    return (
        <Link href={route('admin.student-progress.students.show', student.id)}>
            <div className="flex size-12 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-600 ring-1 ring-slate-200 transition hover:ring-slate-300">
                {student.profile_initials}
            </div>
        </Link>
    );
}

function StudentActionMenu({ student }) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" size="icon" aria-label="Open actions">
                    <MoreHorizontal className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="w-48 border border-slate-200 bg-white text-slate-900 shadow-lg"
            >
                <DropdownMenuItem asChild>
                    <Link
                        href={route('admin.student-progress.completed-lessons.show', student.id)}
                    >
                        Completed Lesson
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        href={route('admin.student-progress.assignments.show', student.id)}
                    >
                        Assignment
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        href={route('admin.student-progress.certificates.show', student.id)}
                    >
                        Certificate
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function SortButton({ label, column, sortKey, sortDirection, onSort }) {
    const Icon =
        sortKey !== column
            ? ArrowUpDown
            : sortDirection === 'asc'
              ? ArrowUp
              : ArrowDown;

    return (
        <button
            type="button"
            onClick={() => onSort(column)}
            className="inline-flex items-center gap-1 font-medium text-slate-700 transition hover:text-slate-900"
        >
            <span>{label}</span>
            <Icon className="size-3.5" />
        </button>
    );
}

function PaginationControls({
    currentPage,
    totalPages,
    onPageChange,
}) {
    if (totalPages <= 1) {
        return null;
    }

    return (
        <div className="flex items-center gap-2">
            <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={currentPage === 1}
                onClick={() => onPageChange(currentPage - 1)}
            >
                Prev
            </Button>
            <span className="text-sm text-slate-600">
                Page {currentPage} of {totalPages}
            </span>
            <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={currentPage === totalPages}
                onClick={() => onPageChange(currentPage + 1)}
            >
                Next
            </Button>
        </div>
    );
}

function compareValues(left, right, direction) {
    if (left === right) {
        return 0;
    }

    if (left === null || left === undefined || left === '') {
        return direction === 'asc' ? 1 : -1;
    }

    if (right === null || right === undefined || right === '') {
        return direction === 'asc' ? -1 : 1;
    }

    if (typeof left === 'number' && typeof right === 'number') {
        return direction === 'asc' ? left - right : right - left;
    }

    return direction === 'asc'
        ? String(left).localeCompare(String(right))
        : String(right).localeCompare(String(left));
}

function TierTable({ section }) {
    const [search, setSearch] = useState('');
    const [sortKey, setSortKey] = useState('number');
    const [sortDirection, setSortDirection] = useState('asc');
    const { getInitialPage, persistPage } = usePersistedPage(`admin-student-progress-${section.slug}-page`);
    const [currentPage, setCurrentPageState] = useState(getInitialPage);
    const setCurrentPage = (page) => {
        setCurrentPageState(page);
        persistPage(page);
    };
    const [pageSize, setPageSize] = useState(10);

    const filteredStudents = useMemo(() => {
        const normalizedSearch = search.trim().toLowerCase();

        if (normalizedSearch === '') {
            return section.students;
        }

        return section.students.filter((student) =>
            [student.name, student.email, student.registration_date, student.last_visit_at]
                .filter(Boolean)
                .some((value) =>
                    String(value).toLowerCase().includes(normalizedSearch),
                ),
        );
    }, [search, section.students]);

    const sortedStudents = useMemo(() => {
        const sorted = [...filteredStudents];

        sorted.sort((left, right) => {
            const valueMap = {
                number: [left.number, right.number],
                name: [left.name ?? '', right.name ?? ''],
                progress_percentage: [left.progress_percentage, right.progress_percentage],
                registration_date: [
                    left.registration_date_sort ?? left.registration_date,
                    right.registration_date_sort ?? right.registration_date,
                ],
                last_visit_at: [
                    left.last_visit_sort ?? '',
                    right.last_visit_sort ?? '',
                ],
            };

            const [leftValue, rightValue] = valueMap[sortKey] ?? valueMap.number;

            return compareValues(leftValue, rightValue, sortDirection);
        });

        return sorted;
    }, [filteredStudents, sortDirection, sortKey]);

    const totalPages = Math.max(1, Math.ceil(sortedStudents.length / pageSize));
    const safePage = Math.min(currentPage, totalPages);
    const pageStart = (safePage - 1) * pageSize;
    const paginatedStudents = sortedStudents.slice(pageStart, pageStart + pageSize);

    const handleSort = (column) => {
        setCurrentPage(1);
        if (sortKey !== column) {
            setSortKey(column);
            setSortDirection('asc');
            return;
        }

        setSortDirection((currentDirection) =>
            currentDirection === 'asc' ? 'desc' : 'asc',
        );
    };

    return (
        <div className="overflow-hidden rounded-lg bg-white shadow-sm">
            <div className="border-b border-slate-200 px-6 py-4">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h3 className="text-lg font-semibold text-slate-900">
                            {section.label}
                        </h3>
                        <p className="mt-1 text-sm text-slate-500">
                            {filteredStudents.length} student
                            {filteredStudents.length === 1 ? '' : 's'}
                        </p>
                    </div>

                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <div className="relative">
                            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                value={search}
                                onChange={(event) => {
                                    setSearch(event.target.value);
                                    setCurrentPage(1);
                                }}
                                placeholder={`Search ${section.label.toLowerCase()}...`}
                                className="h-10 rounded-lg border border-slate-200 bg-white pl-9 pr-3 text-sm text-slate-700 outline-none focus:border-slate-400 sm:w-64"
                            />
                        </div>

                        <select
                            value={pageSize}
                            onChange={(event) => {
                                setPageSize(Number(event.target.value));
                                setCurrentPage(1);
                            }}
                            className="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-slate-400"
                        >
                            {PAGE_SIZE_OPTIONS.map((option) => (
                                <option key={option} value={option}>
                                    {option} / page
                                </option>
                            ))}
                        </select>
                    </div>
                </div>
            </div>

            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-slate-200 text-sm">
                    <thead className="bg-slate-50">
                        <tr>
                            <th className="px-4 py-3 text-left text-[11px] uppercase tracking-[0.06em]">
                                <SortButton
                                    label="No"
                                    column="number"
                                    sortKey={sortKey}
                                    sortDirection={sortDirection}
                                    onSort={handleSort}
                                />
                            </th>
                            <th className="px-4 py-3 text-left text-[11px] font-medium uppercase tracking-[0.06em] text-slate-700">
                                Photo
                            </th>
                            <th className="px-4 py-3 text-left text-[11px] uppercase tracking-[0.06em]">
                                <SortButton
                                    label="Name"
                                    column="name"
                                    sortKey={sortKey}
                                    sortDirection={sortDirection}
                                    onSort={handleSort}
                                />
                            </th>
                            <th className="px-4 py-3 text-left text-[11px] uppercase tracking-[0.06em]">
                                <SortButton
                                    label="Progress"
                                    column="progress_percentage"
                                    sortKey={sortKey}
                                    sortDirection={sortDirection}
                                    onSort={handleSort}
                                />
                            </th>
                            <th className="px-4 py-3 text-left text-[11px] uppercase tracking-[0.06em]">
                                <SortButton
                                    label="Registration Date"
                                    column="registration_date"
                                    sortKey={sortKey}
                                    sortDirection={sortDirection}
                                    onSort={handleSort}
                                />
                            </th>
                            <th className="px-4 py-3 text-left text-[11px] uppercase tracking-[0.06em]">
                                <SortButton
                                    label="Last Visit"
                                    column="last_visit_at"
                                    sortKey={sortKey}
                                    sortDirection={sortDirection}
                                    onSort={handleSort}
                                />
                            </th>
                            <th className="px-4 py-3 text-left text-[11px] font-medium uppercase tracking-[0.06em] text-slate-700">
                                Action
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 bg-white">
                        {paginatedStudents.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={7}
                                    className="px-4 py-10 text-center text-sm text-slate-500"
                                >
                                    No students found in this tier.
                                </td>
                            </tr>
                        ) : (
                            paginatedStudents.map((student) => (
                                <tr key={student.id}>
                                    <td className="px-4 py-4 text-slate-600">
                                        {student.number}
                                    </td>
                                    <td className="px-4 py-4">
                                        <PhotoCell student={student} />
                                    </td>
                                    <td className="px-4 py-4">
                                        <Link
                                            href={route('admin.student-progress.students.show', student.id)}
                                            className="font-medium text-slate-900 transition hover:text-indigo-700"
                                        >
                                            {student.name || 'Unnamed student'}
                                        </Link>
                                        <div className="mt-1 text-xs text-slate-500">
                                            {student.email}
                                        </div>
                                    </td>
                                    <td className="px-4 py-4">
                                        <div className="flex min-w-32 items-center gap-3">
                                            <div className="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                                <div
                                                    className="h-full rounded-full bg-emerald-500"
                                                    style={{
                                                        width: `${student.progress_percentage}%`,
                                                    }}
                                                />
                                            </div>
                                            <span className="text-sm font-medium text-slate-700">
                                                {student.progress_percentage}%
                                            </span>
                                        </div>
                                    </td>
                                    <td className="px-4 py-4 text-slate-700">
                                        {student.registration_date}
                                    </td>
                                    <td className="px-4 py-4 text-slate-700">
                                        {student.last_visit_at}
                                    </td>
                                    <td className="px-4 py-4">
                                        <StudentActionMenu student={student} />
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            <div className="flex flex-col gap-3 border-t border-slate-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-sm text-slate-500">
                    Showing {paginatedStudents.length === 0 ? 0 : pageStart + 1} to{' '}
                    {Math.min(pageStart + pageSize, sortedStudents.length)} of{' '}
                    {sortedStudents.length} students
                </p>

                <PaginationControls
                    currentPage={safePage}
                    totalPages={totalPages}
                    onPageChange={setCurrentPage}
                />
            </div>
        </div>
    );
}

export default function StudentProgressDirectory({ tierSections, status }) {
    return (
        <AuthenticatedLayout>
            <Head title="Student" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {status === 'student-profile-updated' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Student profile has been updated.
                        </div>
                    )}

                    {status === 'student-account-created' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Student account has been created.
                        </div>
                    )}

                    {status === 'student-account-deleted' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Student account has been deleted.
                        </div>
                    )}

                    <div className="flex justify-end">
                        <Button asChild>
                            <Link href={route('admin.students.create', { context: 'student_progress' })}>
                                <UserPlus className="mr-2 size-4" />
                                Add Student
                            </Link>
                        </Button>
                    </div>

                    {tierSections.map((section) => (
                        <TierTable key={section.slug} section={section} />
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}