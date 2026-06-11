import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { MoreHorizontal } from 'lucide-react';

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

function AssignmentBadge({ status }) {
    return (
        <Badge variant={status === 'Submitted' ? 'secondary' : 'outline'}>
            {status}
        </Badge>
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
            <DropdownMenuContent align="end" className="w-48">
                <DropdownMenuItem asChild>
                    <Link
                        href={route(
                            'admin.student-progress.students.edit',
                            student.id,
                        )}
                    >
                        Student Detail
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        href={route(
                            'admin.student-progress.completed-lessons.show',
                            student.id,
                        )}
                    >
                        Completed Lesson
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        href={route(
                            'admin.student-progress.assignments.show',
                            student.id,
                        )}
                    >
                        Assignment
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        href={route(
                            'admin.student-progress.certificates.show',
                            student.id,
                        )}
                    >
                        Certificate
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function TierTable({ section }) {
    return (
        <div className="overflow-hidden rounded-lg bg-white shadow-sm">
            <div className="border-b border-slate-200 px-6 py-4">
                <h3 className="text-lg font-semibold text-slate-900">
                    {section.label}
                </h3>
                <p className="mt-1 text-sm text-slate-500">
                    {section.students.length} student
                    {section.students.length === 1 ? '' : 's'}
                </p>
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
                                Access Tier
                            </th>
                            <th className="px-4 py-3 text-left font-medium text-slate-700">
                                Status
                            </th>
                            <th className="px-4 py-3 text-left font-medium text-slate-700">
                                Progress
                            </th>
                            <th className="px-4 py-3 text-left font-medium text-slate-700">
                                Registration Date
                            </th>
                            <th className="px-4 py-3 text-left font-medium text-slate-700">
                                Assignment
                            </th>
                            <th className="px-4 py-3 text-left font-medium text-slate-700">
                                Action
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 bg-white">
                        {section.students.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={9}
                                    className="px-4 py-10 text-center text-sm text-slate-500"
                                >
                                    No students found in this tier yet.
                                </td>
                            </tr>
                        ) : (
                            section.students.map((student) => (
                                <tr key={student.id}>
                                    <td className="px-4 py-4 text-slate-600">
                                        {student.number}
                                    </td>
                                    <td className="px-4 py-4">
                                        <PhotoCell student={student} />
                                    </td>
                                    <td className="px-4 py-4">
                                        <div className="font-medium text-slate-900">
                                            {student.name || 'Unnamed student'}
                                        </div>
                                        <div className="mt-1 text-xs text-slate-500">
                                            {student.email}
                                        </div>
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
                                    <td className="px-4 py-4">
                                        <AssignmentBadge
                                            status={student.assignment_status}
                                        />
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
        </div>
    );
}

export default function StudentProgressDirectory({ tierSections, status }) {
    return (
        <AuthenticatedLayout
            header={
                <div className="min-w-0">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Student Progress
                    </h2>
                    <p className="mt-1 text-sm text-gray-500">
                        Review all students grouped by tier, monitor module
                        completion progress, and jump directly into each student
                        progress area.
                    </p>
                </div>
            }
        >
            <Head title="Student Progress" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {status === 'student-profile-updated' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Student profile has been updated.
                        </div>
                    )}
                    {status === 'student-account-deleted' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Student account has been deleted.
                        </div>
                    )}

                    {tierSections.map((section) => (
                        <TierTable key={section.slug} section={section} />
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
