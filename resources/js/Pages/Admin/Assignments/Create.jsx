import AssignmentForm from '@/Components/AssignmentForm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function CreateAssignment({ module, assignmentStatuses, nextSortOrder }) {
    const { data, setData, post, processing, errors } = useForm({
        title: '',
        description: '',
        sort_order: String(nextSortOrder),
        status: 'live',
        is_required: true,
    });

    const submit = (event) => {
        event.preventDefault();
        post(route('admin.modules.assignments.store', module.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Create Assignment
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Add a new assignment inside module {module.title}.
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
            <Head title="Create Assignment" />
            <div className="py-12">
                <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                    <div className="rounded-lg bg-white p-6 shadow-sm">
                        <AssignmentForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            processing={processing}
                            assignmentStatuses={assignmentStatuses}
                            onSubmit={submit}
                            submitLabel="Create Assignment"
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
