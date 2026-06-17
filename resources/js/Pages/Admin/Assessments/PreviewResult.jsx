import QuestionReviewList from '@/Components/admin/assessments/QuestionReviewList';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function AssessmentPreviewResult({
    assessment,
    preview_result: previewResult,
}) {
    return (
        <AuthenticatedLayout
            header={
                <div>
                    <div className="text-sm text-slate-500">
                        Admin Assessment Preview
                    </div>
                    <h2 className="text-2xl font-semibold text-slate-900">
                        Preview Result
                    </h2>
                </div>
            }
        >
            <Head title={`${assessment.title} Preview Result`} />

            <div className="bg-[linear-gradient(180deg,#eef3ee_0%,#f8f5ef_45%,#ffffff_100%)] py-12">
                <div className="mx-auto max-w-6xl space-y-8 px-4 sm:px-6 lg:px-8">
                    <div className="rounded-[36px] border border-white/70 bg-white/90 p-8 shadow-[0_24px_60px_rgba(15,23,42,0.08)] backdrop-blur md:p-12">
                        <div className="mx-auto max-w-3xl text-center">
                            <Badge variant="outline">Simulation Only</Badge>
                            <div className="mt-4 text-xs font-semibold uppercase tracking-[0.18em] text-[#5f7462]">
                                Assessment Preview Complete
                            </div>
                            <h3 className="mt-4 text-4xl font-semibold tracking-tight text-slate-900">
                                {previewResult.summary.points}
                            </h3>
                            <p className="mt-3 text-base leading-7 text-slate-600">
                                {previewResult.summary.result_label
                                    ? `${previewResult.summary.result_label}${previewResult.summary.result_description ? ` - ${previewResult.summary.result_description}` : ''}`
                                    : 'This preview does not map into a result range yet, so the simulated score is shown directly.'}
                            </p>

                            <div className="mt-8 grid gap-4 md:grid-cols-4">
                                <div className="rounded-2xl bg-[#f4f1e9] px-4 py-4">
                                    <div className="text-xs uppercase tracking-[0.16em] text-slate-500">
                                        Correct Answers
                                    </div>
                                    <div className="mt-2 text-lg font-semibold text-slate-900">
                                        {previewResult.summary.correct_answers_label}
                                    </div>
                                </div>
                                <div className="rounded-2xl bg-[#eef3ee] px-4 py-4">
                                    <div className="text-xs uppercase tracking-[0.16em] text-slate-500">
                                        Percentage
                                    </div>
                                    <div className="mt-2 text-lg font-semibold text-slate-900">
                                        {previewResult.summary.percentage}%
                                    </div>
                                </div>
                                <div className="rounded-2xl bg-[#f5f6fa] px-4 py-4">
                                    <div className="text-xs uppercase tracking-[0.16em] text-slate-500">
                                        Duration
                                    </div>
                                    <div className="mt-2 text-sm font-semibold text-slate-900">
                                        {previewResult.summary.time_taken.available
                                            ? previewResult.summary.time_taken.label
                                            : 'Unavailable'}
                                    </div>
                                </div>
                                <div className="rounded-2xl bg-[#f3f5fb] px-4 py-4">
                                    <div className="text-xs uppercase tracking-[0.16em] text-slate-500">
                                        Points
                                    </div>
                                    <div className="mt-2 text-lg font-semibold text-slate-900">
                                        {previewResult.summary.points}
                                    </div>
                                </div>
                            </div>

                            <div className="mt-8 flex flex-wrap justify-center gap-3">
                                <Button asChild size="lg">
                                    <Link
                                        href={route(
                                            'admin.assessments.preview',
                                            assessment.id,
                                        )}
                                        data={{ restart: 1 }}
                                    >
                                        Restart Preview
                                    </Link>
                                </Button>
                                <Button asChild variant="outline" size="lg">
                                    <Link href={route('admin.scoreboards.index')}>
                                        Back to Assessment List
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </div>

                    <div className="space-y-4">
                        <div>
                            <h3 className="text-lg font-semibold text-slate-900">
                                Traversed Preview Review
                            </h3>
                            <p className="mt-1 text-sm text-slate-500">
                                This review shows only the screens that were actually traversed during the simulation.
                            </p>
                        </div>

                        <QuestionReviewList questions={previewResult.questions} />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
