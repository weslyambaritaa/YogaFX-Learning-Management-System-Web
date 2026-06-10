import ScoreboardMetaForm from '@/Components/ScoreboardMetaForm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function CreateScoreboard({
    statusOptions,
    scoringModeOptions,
    resultModeOptions,
}) {
    const { data, setData, post, processing, errors } = useForm({
        title: '',
        slug: '',
        description: '',
        thumbnail: null,
        status: 'draft',
        duration_minutes: '',
        scoring_mode: 'points',
        result_mode: 'score_or_range',
        is_active: false,
        show_progress_bar: true,
        allow_back_navigation: true,
    });

    const submit = (event) => {
        event.preventDefault();
        post(route('admin.scoreboards.store'), { forceFormData: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h2 className="text-2xl font-semibold text-slate-900">
                            Create Scoreboard
                        </h2>
                        <p className="mt-1 text-sm text-slate-500">
                            Define the editorial and operational shell first, then continue inside the builder.
                        </p>
                    </div>
                    <Link
                        href={route('admin.scoreboards.index')}
                        className="text-sm font-medium text-slate-600 hover:text-slate-900"
                    >
                        Back to Scoreboards
                    </Link>
                </div>
            }
        >
            <Head title="Create Scoreboard" />

            <div className="py-12">
                <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                    <div className="rounded-[32px] border border-slate-200 bg-white p-8 shadow-sm">
                        <ScoreboardMetaForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            processing={processing}
                            statusOptions={statusOptions}
                            scoringModeOptions={scoringModeOptions}
                            resultModeOptions={resultModeOptions}
                            onSubmit={submit}
                            submitLabel="Create and Open Builder"
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
