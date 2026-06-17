import AssignmentForm from '@/Components/AssignmentForm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ClipboardList, CheckCircle2 } from 'lucide-react';

const statusMessages = {
    'assignment-created': 'Assignment has been created.',
    'assignment-updated': 'Assignment has been updated.',
};

export default function EditAssignment({ module, assignment, assignmentStatuses, status }) {
    const { data, setData, patch, processing, errors } = useForm({
        title: assignment.title ?? '',
        description: assignment.description ?? '',
        sort_order: String(assignment.sort_order ?? 1),
        status: assignment.status ?? 'live',
        is_required: Boolean(assignment.is_required),
    });

    const submit = (event) => {
        event.preventDefault();
        patch(route('admin.modules.assignments.update', {
            module: module.id,
            assignment: assignment.id,
        }));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <div className="flex size-9 items-center justify-center rounded-xl border border-slate-200 bg-white shadow-sm">
                            <ClipboardList className="size-4 text-slate-500" />
                        </div>
                        <div>
                            <h2 className="text-lg font-semibold leading-tight text-slate-800">Edit Assignment</h2>
                            <p className="text-xs text-slate-500">Update assignment settings inside module {module.title}.</p>
                        </div>
                    </div>
                    {/* Back — outline button hitam */}
                    <Link
                        href={route('admin.modules.assignments.index', module.id)}
                        className="inline-flex h-9 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50 transition whitespace-nowrap"
                    >
                        Back to Assignments
                    </Link>
                </div>
            }
        >
            <Head title="Edit Assignment" />
            <div className="py-10">
                <div className="mx-auto max-w-5xl space-y-5 px-4 sm:px-6 lg:px-8">
                    {statusMessages[status] && (
                        <div className="flex items-center gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            <CheckCircle2 className="size-4 shrink-0 text-emerald-500" />
                            {statusMessages[status]}
                        </div>
                    )}
                    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <AssignmentForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            processing={processing}
                            assignmentStatuses={assignmentStatuses}
                            onSubmit={submit}
                            submitLabel="Save Assignment"
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}