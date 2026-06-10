import ScoreboardMetaForm from '@/Components/ScoreboardMetaForm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const statusMessages = {
    'scoreboard-updated': 'Scoreboard meta has been updated.',
};

export default function EditScoreboard({
    scoreboard,
    statusOptions,
    scoringModeOptions,
    resultModeOptions,
    status,
}) {
    const { data, setData, patch, processing, errors } = useForm({
        title: scoreboard.title ?? '',
        slug: scoreboard.slug ?? '',
        description: scoreboard.description ?? '',
        thumbnail: null,
        status: scoreboard.status ?? 'draft',
        duration_minutes: scoreboard.duration_minutes ?? '',
        scoring_mode: scoreboard.scoring_mode ?? 'points',
        result_mode: scoreboard.result_mode ?? 'score_or_range',
        is_active: Boolean(scoreboard.is_active),
        show_progress_bar: Boolean(scoreboard.show_progress_bar),
        allow_back_navigation: Boolean(scoreboard.allow_back_navigation),
    });

    const submit = (event) => {
        event.preventDefault();
        patch(route('admin.scoreboards.update', scoreboard.id), { forceFormData: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h2 className="text-2xl font-semibold text-slate-900">
                            Edit Scoreboard
                        </h2>
                        <p className="mt-1 text-sm text-slate-500">
                            Update the scoreboard shell without leaving the assessment domain.
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <Link
                            href={route('admin.scoreboards.builder', scoreboard.id)}
                            className="text-sm font-medium text-slate-600 hover:text-slate-900"
                        >
                            Open Builder
                        </Link>
                        <Link
                            href={route('admin.scoreboards.index')}
                            className="text-sm font-medium text-slate-600 hover:text-slate-900"
                        >
                            Back to Scoreboards
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="Edit Scoreboard" />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {statusMessages[status] && (
                        <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            {statusMessages[status]}
                        </div>
                    )}

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
                            submitLabel="Save Meta"
                            currentThumbnailUrl={scoreboard.thumbnail_url}
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
