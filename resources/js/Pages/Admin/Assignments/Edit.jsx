import AssignmentForm from '@/Components/AssignmentForm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const statusMessages = {
    'assignment-created': 'Assignment has been created.',
    'assignment-updated': 'Assignment has been updated.',
};

export default function EditAssignment({
    module,
    assignment,
    assignmentStatuses,
    status,
}) {
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
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Edit Assignment
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Update assignment settings inside module {module.title}.
                        </p>
                    </div>
                    <Link
                        href={route('admin.modules.assignments.index', module.id)}
                        className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                    >
                        Back to Assignments
                    </Link>
                </div>
            }
        >
            <Head title="Edit Assignment" />
            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {statusMessages[status] && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            {statusMessages[status]}
                        </div>
                    )}
                    <div className="rounded-lg bg-white p-6 shadow-sm">
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
