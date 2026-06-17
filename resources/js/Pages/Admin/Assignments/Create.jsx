import AssignmentForm from '@/Components/AssignmentForm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';

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
                    <div className="flex items-center gap-3">
                        <div className="flex size-9 items-center justify-center rounded-xl border border-slate-200 bg-white shadow-sm">
                            <ClipboardList className="size-4 text-slate-500" />
                        </div>
                        <div>
                            <h2 className="text-lg font-semibold leading-tight text-slate-800">Create Assignment</h2>
                            <p className="text-xs text-slate-500">Add a new assignment inside module {module.title}.</p>
                        </div>
                    </div>
                    {/* Back — outline button hitam, bukan link biru */}
                    <Link
                        href={route('admin.modules.assignments.index', module.id)}
                        className="inline-flex h-9 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50 transition whitespace-nowrap"
                    >
                        Back to Assignments
                    </Link>
                </div>
            }
        >
            <Head title="Create Assignment" />
            <div className="py-10">
                <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
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