import DeleteConfirmationDialog from '@/Components/DeleteConfirmationDialog';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';

export default function LessonsIndex({ lessons, status }) {
    const errors = usePage().props.errors;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Lessons
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Manage lesson records independently from module navigation.
                        </p>
                    </div>
                    <Link
                        href={route('admin.lessons.create')}
                        className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        Create Lesson
                    </Link>
                </div>
            }
        >
            <Head title="Lessons" />
            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {status === 'lesson-created' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Lesson has been created.
                        </div>
                    )}
                    {status === 'lesson-updated' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Lesson has been updated.
                        </div>
                    )}
                    {status === 'lesson-deleted' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Lesson has been deleted.
                        </div>
                    )}
                    {errors.lesson && (
                        <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                            {errors.lesson}
                        </div>
                    )}
                    <div className="overflow-hidden rounded-lg bg-white shadow-sm">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 text-sm">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Lesson</th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Module</th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Tiers</th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Order</th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Assets</th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {lessons.map((lesson) => (
                                        <tr key={lesson.id}>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-4">
                                                    {lesson.thumbnail_url ? (
                                                        <img
                                                            src={lesson.thumbnail_url}
                                                            alt={lesson.title}
                                                            className="h-14 w-20 rounded-md object-cover"
                                                        />
                                                    ) : (
                                                        <div className="flex h-14 w-20 items-center justify-center rounded-md bg-gray-100 text-[11px] font-medium uppercase tracking-wide text-gray-500">
                                                            No image
                                                        </div>
                                                    )}
                                                    <div className="font-medium text-gray-900">
                                                        {lesson.title}
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-gray-700">{lesson.module}</td>
                                            <td className="px-4 py-3 text-gray-700">
                                                {lesson.access_tiers.join(', ')}
                                            </td>
                                            <td className="px-4 py-3 text-gray-700">{lesson.sort_order}</td>
                                            <td className="px-4 py-3 text-gray-700">
                                                {[
                                                    lesson.has_workbook ? 'Workbook' : null,
                                                    lesson.has_video ? 'Video' : null,
                                                    lesson.has_audio ? 'Audio' : null,
                                                ]
                                                    .filter(Boolean)
                                                    .join(', ') || 'Basic'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-4">
                                                    <Link
                                                        href={route('admin.lessons.edit', lesson.id)}
                                                        className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                                    >
                                                        Edit
                                                    </Link>
                                                    <DeleteConfirmationDialog
                                                        href={route('admin.lessons.destroy', lesson.id)}
                                                        title="Delete lesson?"
                                                        description={`This will permanently delete "${lesson.title}". This action cannot be undone.`}
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
