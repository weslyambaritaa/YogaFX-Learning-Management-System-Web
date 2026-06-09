import DeleteConfirmationDialog from '@/Components/DeleteConfirmationDialog';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function CoursesIndex({ courses, status }) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Courses
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Manage independent course resources outside the main module flow.
                        </p>
                    </div>
                    <Link
                        href={route('admin.courses.create')}
                        className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        Create Course
                    </Link>
                </div>
            }
        >
            <Head title="Courses" />
            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
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
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Tier</th>
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
                                            <td className="px-4 py-3 text-gray-700">{course.access_tier}</td>
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
