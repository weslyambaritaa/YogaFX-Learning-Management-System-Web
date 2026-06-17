import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

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

export default function StudentsIndex({ students, status }) {
    return (
        <AuthenticatedLayout
            header={
                <div className="min-w-0">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Students
                    </h2>
                    <p className="mt-1 text-sm text-gray-500">
                        Manage student accounts, review assigned access tiers, control
                        active or inactive status, and open each student detail page.
                    </p>
                </div>
            }
        >
            <Head title="Students" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {status === 'student-account-deleted' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Student account has been deleted.
                        </div>
                    )}

                    <div className="overflow-hidden rounded-lg bg-white shadow-sm">
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
                                    {students.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={8}
                                                className="px-4 py-10 text-center text-sm text-slate-500"
                                            >
                                                No students found yet.
                                            </td>
                                        </tr>
                                    ) : (
                                        students.map((student) => (
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
                                                    <Button asChild variant="outline" size="sm">
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
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
