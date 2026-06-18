import DeleteConfirmationDialog from '@/Components/DeleteConfirmationDialog';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { ClipboardList, CheckCircle2, XCircle, Plus } from 'lucide-react';

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
                <div className="flex items-center gap-3">
                    <div className="flex size-9 items-center justify-center rounded-xl border border-slate-200 bg-white shadow-sm">
                        <ClipboardList className="size-4 text-slate-500" />
                    </div>
                    <div>
                        <h2 className="text-lg font-semibold leading-tight text-slate-800">Assignments</h2>
                        <p className="text-xs text-slate-500">Manage assignment items inside module {module.title}.</p>
                    </div>
                </div>
            }
        >
            <Head title="Assignments" />

            <div className="py-10">
                <div className="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">

                    {/* Flash */}
                    {statusMessages[status] && (
                        <div className="flex items-center gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            <CheckCircle2 className="size-4 shrink-0 text-emerald-500" />
                            {statusMessages[status]}
                        </div>
                    )}
                    {errors.assignment && (
                        <div className="flex items-center gap-2.5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                            <XCircle className="size-4 shrink-0 text-rose-500" />
                            {errors.assignment}
                        </div>
                    )}

                    {/* Card */}
                    <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

                        {/* Card header */}
                        <div className="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-4">
                                <ClipboardList className="size-4 text-slate-400" />
                                <span className="text-sm font-semibold text-slate-700">Assignment List</span>
                                <div className="flex items-center gap-3 text-xs text-slate-500">
                                    <span>{module.lessons_count} lessons</span>
                                    <span className="h-3 w-px bg-slate-200" />
                                    <span>{module.assignments_count} assignments</span>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                {/* Back to Module — outline hitam */}
                                <Link
                                    href={route('admin.modules.edit', module.id)}
                                    className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50 transition whitespace-nowrap"
                                >
                                    Back to Module
                                </Link>
                                {/* Create Assignment — hitam */}
                                <Link
                                    href={route('admin.modules.assignments.create', module.id)}
                                    className="inline-flex h-9 items-center gap-1.5 rounded-lg bg-slate-900 px-4 text-sm font-medium text-white hover:bg-slate-700 transition whitespace-nowrap"
                                >
                                    <Plus className="size-3.5" />
                                    Create Assignment
                                </Link>
                            </div>
                        </div>

                        {/* Content */}
                        {assignments.length === 0 ? (
                            <div className="px-6 py-16 text-center">
                                <div className="rounded-xl border border-dashed border-slate-200 px-6 py-10 text-sm text-slate-400">
                                    No assignments exist in this module yet.
                                </div>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-slate-100 bg-slate-50">
                                            {['Assignment', 'Status', 'Order', 'Required', 'Submissions', 'Action'].map(col => (
                                                <th key={col} className="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.06em] text-slate-500 whitespace-nowrap">
                                                    {col}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 bg-white">
                                        {assignments.map((assignment) => (
                                            <tr key={assignment.id} className="hover:bg-slate-50/70 transition-colors align-top">
                                                <td className="px-4 py-4">
                                                    <div className="font-medium text-gray-900">{assignment.title}</div>
                                                    {assignment.description ? (
                                                        <div className="mt-1 max-w-xl text-xs leading-5 text-gray-500">
                                                            {assignment.description}
                                                        </div>
                                                    ) : null}
                                                </td>
                                                <td className="px-4 py-4 text-gray-700 align-top capitalize whitespace-nowrap">
                                                    {assignment.status.replaceAll('_', ' ')}
                                                </td>
                                                <td className="px-4 py-4 text-gray-700 align-top">{assignment.sort_order}</td>
                                                <td className="px-4 py-4 text-gray-700 align-top whitespace-nowrap">
                                                    {assignment.is_required ? 'Required' : 'Optional'}
                                                </td>
                                                <td className="px-4 py-4 text-gray-700 align-top">{assignment.submissions_count}</td>
                                                <td className="px-4 py-4 align-top">
                                                    <div className="flex flex-col items-start gap-2">
                                                        <Link
                                                            href={route('admin.modules.assignments.edit', {
                                                                module: module.id,
                                                                assignment: assignment.id,
                                                            })}
                                                            className="text-sm font-medium text-indigo-600 hover:text-indigo-800 transition-colors"
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
                                                            triggerClassName="text-sm font-medium text-rose-600 hover:text-rose-800 transition-colors"
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