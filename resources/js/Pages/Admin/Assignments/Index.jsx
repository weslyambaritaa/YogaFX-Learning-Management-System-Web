import DeleteConfirmationDialog from '@/Components/DeleteConfirmationDialog';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';

const statusMessages = {
    'assignment-created': 'Assignment has been created.',
    'assignment-updated': 'Assignment has been updated.',
    'assignment-deleted': 'Assignment has been deleted.',
};

export default function AssignmentIndex({ module, assignments, status }) {
    const errors = usePage().props.errors;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="min-w-0">
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Assignments
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Manage assignment items inside module {module.title}.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-3">
                        <Link
                            href={route('admin.modules.edit', module.id)}
                            className="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Back to Module
                        </Link>
                        <Link
                            href={route('admin.modules.assignments.create', module.id)}
                            className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                        >
                            Create Assignment
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="Assignments" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {statusMessages[status] && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            {statusMessages[status]}
                        </div>
                    )}
                    {errors.assignment && (
                        <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                            {errors.assignment}
                        </div>
                    )}

                    <div className="rounded-lg bg-white p-6 shadow-sm">
                        <div className="mb-4 flex flex-wrap gap-6 text-sm text-gray-600">
                            <span>{module.lessons_count} lessons</span>
                            <span>{module.assignments_count} assignments</span>
                        </div>

                        {assignments.length === 0 ? (
                            <div className="rounded-lg border border-dashed border-gray-300 px-6 py-10 text-center text-sm text-gray-500">
                                No assignments exist in this module yet.
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-4 py-3 text-left font-medium text-gray-700">
                                                Assignment
                                            </th>
                                            <th className="px-4 py-3 text-left font-medium text-gray-700">
                                                Status
                                            </th>
                                            <th className="px-4 py-3 text-left font-medium text-gray-700">
                                                Order
                                            </th>
                                            <th className="px-4 py-3 text-left font-medium text-gray-700">
                                                Required
                                            </th>
                                            <th className="px-4 py-3 text-left font-medium text-gray-700">
                                                Submissions
                                            </th>
                                            <th className="px-4 py-3 text-left font-medium text-gray-700">
                                                Action
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100 bg-white">
                                        {assignments.map((assignment) => (
                                            <tr key={assignment.id}>
                                                <td className="px-4 py-3">
                                                    <div className="font-medium text-gray-900">
                                                        {assignment.title}
                                                    </div>
                                                    {assignment.description ? (
                                                        <div className="mt-1 max-w-xl text-xs leading-5 text-gray-500">
                                                            {assignment.description}
                                                        </div>
                                                    ) : null}
                                                </td>
                                                <td className="px-4 py-3 text-gray-700">
                                                    {assignment.status.replaceAll('_', ' ')}
                                                </td>
                                                <td className="px-4 py-3 text-gray-700">
                                                    {assignment.sort_order}
                                                </td>
                                                <td className="px-4 py-3 text-gray-700">
                                                    {assignment.is_required ? 'Required' : 'Optional'}
                                                </td>
                                                <td className="px-4 py-3 text-gray-700">
                                                    {assignment.submissions_count}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex flex-wrap items-center gap-4">
                                                        <Link
                                                            href={route('admin.modules.assignments.show', {
                                                                module: module.id,
                                                                assignment: assignment.id,
                                                            })}
                                                            className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                                        >
                                                            Review
                                                        </Link>
                                                        <Link
                                                            href={route('admin.modules.assignments.edit', {
                                                                module: module.id,
                                                                assignment: assignment.id,
                                                            })}
                                                            className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                                        >
                                                            Edit
                                                        </Link>
                                                        <DeleteConfirmationDialog
                                                            href={route('admin.modules.assignments.destroy', {
                                                                module: module.id,
                                                                assignment: assignment.id,
                                                            })}
                                                            title="Delete assignment?"
                                                            description={`This will permanently delete "${assignment.title}" and any linked submission references.`}
                                                        />
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
