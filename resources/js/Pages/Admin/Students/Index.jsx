import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function StudentsIndex({ students, status }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Students
                </h2>
            }
        >
            <Head title="Students" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    {status === 'student-profile-updated' && (
                        <div className="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Student profile has been updated.
                        </div>
                    )}
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <div className="mb-4">
                                <h3 className="text-lg font-medium text-gray-900">
                                    Student Directory
                                </h3>
                                <p className="mt-1 text-sm text-gray-600">
                                    Review student identity data and open the
                                    detail page to complete or correct profile
                                    information.
                                </p>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-4 py-3 text-left font-medium text-gray-700">
                                                Student
                                            </th>
                                            <th className="px-4 py-3 text-left font-medium text-gray-700">
                                                Email
                                            </th>
                                            <th className="px-4 py-3 text-left font-medium text-gray-700">
                                                Country
                                            </th>
                                            <th className="px-4 py-3 text-left font-medium text-gray-700">
                                                Access Tier
                                            </th>
                                            <th className="px-4 py-3 text-left font-medium text-gray-700">
                                                Profile Status
                                            </th>
                                            <th className="px-4 py-3 text-left font-medium text-gray-700">
                                                Action
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100 bg-white">
                                        {students.map((student) => (
                                            <tr key={student.id}>
                                                <td className="px-4 py-3">
                                                    <div className="font-medium text-gray-900">
                                                        {student.name || 'Unnamed student'}
                                                    </div>
                                                    <div className="text-xs text-gray-500">
                                                        Joined {student.created_at}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 text-gray-700">
                                                    {student.email}
                                                </td>
                                                <td className="px-4 py-3 text-gray-700">
                                                    {student.country || '-'}
                                                </td>
                                                <td className="px-4 py-3 text-gray-700">
                                                    {student.access_tier || '-'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span
                                                        className={
                                                            student.profile_is_complete
                                                                ? 'rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700'
                                                                : 'rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700'
                                                        }
                                                    >
                                                        {student.profile_is_complete
                                                            ? 'Complete'
                                                            : 'Incomplete'}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-4">
                                                        <Link
                                                            href={route(
                                                                'admin.students.edit',
                                                                student.id,
                                                            )}
                                                            className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                                        >
                                                            Open Detail
                                                        </Link>
                                                        <Link
                                                            href={route(
                                                                'admin.students.progress.show',
                                                                student.id,
                                                            )}
                                                            className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                                        >
                                                            Student Progress
                                                        </Link>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
