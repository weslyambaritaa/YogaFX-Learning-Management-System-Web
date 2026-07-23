import DeleteConfirmationDialog from '@/Components/DeleteConfirmationDialog';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function CoursesIndex({ courses, status }) {
    return (
        <AuthenticatedLayout>
            <Head title="Courses" />
            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <div className="flex justify-end">
                        <Link
                            href={route('admin.courses.create')}
                            className="rounded-md bg-indigo-600 px-4 py-2 text-center text-sm font-medium text-white hover:bg-indigo-700"
                        >
                            Create Course
                        </Link>
                    </div>

                    {status === 'course-created' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Course has been created.
                        </div>
                    )}
                    {status === 'course-updated' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Course has been updated.
                        </div>
                    )}
                    {status === 'course-deleted' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Course has been deleted.
                        </div>
                    )}
                    <div className="overflow-hidden rounded-lg bg-white shadow-sm">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 text-sm">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Course</th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Access Tiers</th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {courses.map((course) => (
                                        <tr key={course.id}>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-4">
                                                    <img
                                                        src={course.thumbnail_url}
                                                        alt={course.title}
                                                        className="h-14 w-20 rounded-md object-cover"
                                                    />
                                                    <div>
                                                        <div className="font-medium text-gray-900">
                                                            {course.title}
                                                        </div>
                                                        <div className="text-xs text-gray-500">
                                                            {course.url_slug}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-gray-700">
                                                {(course.access_tiers ?? []).length
                                                    ? course.access_tiers.join(', ')
                                                    : '-'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-4">
                                                    <Link
                                                        href={route('admin.courses.edit', course.id)}
                                                        className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                                    >
                                                        Edit
                                                    </Link>
                                                    <DeleteConfirmationDialog
                                                        href={route('admin.courses.destroy', course.id)}
                                                        title="Delete video lecture?"
                                                        description={`This will permanently delete "${course.title}". This action cannot be undone.`}
                                                    />
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
        </AuthenticatedLayout>
    );
}
