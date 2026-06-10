import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function AssessmentResult({ lesson, assessment, attempt }) {
    return (
        <AuthenticatedLayout
            header={
                <div>
                    <div className="text-sm text-slate-500">{lesson.title}</div>
                    <h2 className="text-2xl font-semibold text-slate-900">
                        Assessment Result
                    </h2>
                </div>
            }
        >
            <Head title={`${assessment.title} Result`} />

            <div className="bg-[linear-gradient(180deg,#eef3ee_0%,#f8f5ef_45%,#ffffff_100%)] py-12">
                <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                    <div className="rounded-[36px] border border-white/70 bg-white/90 p-8 text-center shadow-[0_24px_60px_rgba(15,23,42,0.08)] backdrop-blur md:p-12">
                        <div className="mx-auto max-w-2xl">
                            <div className="text-xs font-semibold uppercase tracking-[0.18em] text-[#5f7462]">
                                Assessment Complete
                            </div>
                            <h3 className="mt-4 text-4xl font-semibold tracking-tight text-slate-900">
                                {attempt.total_score}
                            </h3>
                            <p className="mt-3 text-base leading-7 text-slate-600">
                                {attempt.result_label
                                    ? `${attempt.result_label}${attempt.result_description ? ` — ${attempt.result_description}` : ''}`
                                    : 'This scoreboard does not use result ranges yet, so your raw score is shown directly.'}
                            </p>

                            <div className="mt-8 grid gap-4 md:grid-cols-3">
                                <div className="rounded-2xl bg-[#f4f1e9] px-4 py-4">
                                    <div className="text-xs uppercase tracking-[0.16em] text-slate-500">
                                        Status
                                    </div>
                                    <div className="mt-2 text-lg font-semibold text-slate-900">
                                        {attempt.status}
                                    </div>
                                </div>
                                <div className="rounded-2xl bg-[#eef3ee] px-4 py-4">
                                    <div className="text-xs uppercase tracking-[0.16em] text-slate-500">
                                        Finished By
                                    </div>
                                    <div className="mt-2 text-lg font-semibold text-slate-900">
                                        {attempt.finished_reason}
                                    </div>
                                </div>
                                <div className="rounded-2xl bg-[#f5f6fa] px-4 py-4">
                                    <div className="text-xs uppercase tracking-[0.16em] text-slate-500">
                                        Completed At
                                    </div>
                                    <div className="mt-2 text-sm font-semibold text-slate-900">
                                        {attempt.completed_at ?? 'Just now'}
                                    </div>
                                </div>
                            </div>

                            <div className="mt-8 flex flex-wrap justify-center gap-3">
                                <Button asChild size="lg">
                                    <Link href={route('lessons.show', lesson.id)}>
                                        Return to Lesson
                                    </Link>
                                </Button>
                                {lesson.module && (
                                    <Button asChild variant="outline" size="lg">
                                        <Link
                                            href={route(
                                                'modules.show',
                                                lesson.module.url_slug,
                                            )}
                                        >
                                            Back to Module
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
