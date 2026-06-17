import QuestionReviewList from '@/Components/admin/assessments/QuestionReviewList';
import DeleteConfirmationDialog from '@/Components/DeleteConfirmationDialog';
import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function AssessmentResultDetail({ assessment, attempt }) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <div className="text-sm text-slate-500">
                            Assessment Result Detail
                        </div>
                        <h2 className="text-2xl font-semibold text-slate-900">
                            {assessment.title}
                        </h2>
                        <p className="mt-1 text-sm text-slate-500">
                            Review the completed attempt for this user, including every traversed question and answer.
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-3">
                        <Button asChild variant="outline">
                            <Link
                                href={route(
                                    'admin.assessments.results.index',
                                    assessment.id,
                                )}
                            >
                                Back to Results
                            </Link>
                        </Button>
                        <DeleteConfirmationDialog
                            title="Delete this result?"
                            description="This deletes the selected assessment attempt and all of its stored answers."
                            trigger={
                                <Button type="button" variant="destructive">
                                    Delete Result
                                </Button>
                            }
                            href={route('admin.assessments.results.destroy', {
                                assessment: assessment.id,
                                attempt: attempt.id,
                            })}
                            confirmLabel="Delete Result"
                        />
                    </div>
                </div>
            }
        >
            <Head title={`${assessment.title} Result Detail`} />

            <div className="py-12">
                <div className="mx-auto max-w-6xl space-y-8 px-4 sm:px-6 lg:px-8">
                    <div className="grid gap-6 lg:grid-cols-[0.95fr_1.05fr]">
                        <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                                User Detail
                            </div>
                            <div className="mt-5 space-y-5">
                                <div>
                                    <div className="text-xs uppercase tracking-[0.16em] text-slate-500">
                                        Name
                                    </div>
                                    <div className="mt-1 text-lg font-semibold text-slate-900">
                                        {attempt.user.name || 'Unknown User'}
                                    </div>
                                </div>
                                <div>
                                    <div className="text-xs uppercase tracking-[0.16em] text-slate-500">
                                        Email
                                    </div>
                                    <div className="mt-1 text-sm text-slate-700">
                                        {attempt.user.email || '-'}
                                    </div>
                                </div>
                                <div>
                                    <div className="text-xs uppercase tracking-[0.16em] text-slate-500">
                                        Phone
                                    </div>
                                    <div className="mt-1 text-sm text-slate-700">
                                        {attempt.user.phone || '-'}
                                    </div>
                                </div>
                                <div>
                                    <div className="text-xs uppercase tracking-[0.16em] text-slate-500">
                                        Attempt
                                    </div>
                                    <div className="mt-1 text-sm text-slate-700">
                                        #{attempt.attempt_number}
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="rounded-2xl bg-[#f4f1e9] px-4 py-4">
                                    <div className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                        Scorecard
                                    </div>
                                    <div className="mt-4 space-y-3 text-sm text-slate-700">
                                        <div className="flex items-center justify-between gap-3">
                                            <span>Correct Answers</span>
                                            <span className="font-semibold text-slate-900">
                                                {attempt.summary.correct_answers_label}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between gap-3">
                                            <span>Points</span>
                                            <span className="font-semibold text-slate-900">
                                                {attempt.summary.points}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between gap-3">
                                            <span>Percentage</span>
                                            <span className="font-semibold text-slate-900">
                                                {attempt.summary.percentage}%
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div className="rounded-2xl bg-[#eef3ee] px-4 py-4">
                                    <div className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                        Time Taken
                                    </div>
                                    {attempt.summary.time_taken.available ? (
                                        <div className="mt-4 grid grid-cols-3 gap-3">
                                            <div className="rounded-xl bg-white/60 px-3 py-3 text-center">
                                                <div className="text-xl font-semibold text-slate-900">
                                                    {attempt.summary.time_taken.parts.hours}
                                                </div>
                                                <div className="mt-1 text-[11px] uppercase tracking-[0.16em] text-slate-500">
                                                    hours
                                                </div>
                                            </div>
                                            <div className="rounded-xl bg-white/60 px-3 py-3 text-center">
                                                <div className="text-xl font-semibold text-slate-900">
                                                    {attempt.summary.time_taken.parts.minutes}
                                                </div>
                                                <div className="mt-1 text-[11px] uppercase tracking-[0.16em] text-slate-500">
                                                    minutes
                                                </div>
                                            </div>
                                            <div className="rounded-xl bg-white/60 px-3 py-3 text-center">
                                                <div className="text-xl font-semibold text-slate-900">
                                                    {attempt.summary.time_taken.parts.seconds}
                                                </div>
                                                <div className="mt-1 text-[11px] uppercase tracking-[0.16em] text-slate-500">
                                                    seconds
                                                </div>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="mt-4 text-xl font-semibold text-slate-900">
                                            Unavailable
                                        </div>
                                    )}
                                    <div className="mt-4 text-sm text-slate-600">
                                        Completed {attempt.completed_at || '-'}
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>

                    <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                            User Comments
                        </div>
                        {attempt.summary.user_comments?.length ? (
                            <div className="mt-5 space-y-4">
                                {attempt.summary.user_comments.map((comment, index) => (
                                    <div
                                        key={`${comment.question_title}-${index}`}
                                        className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4"
                                    >
                                        <div className="text-sm font-medium text-slate-900">
                                            {comment.question_title}
                                        </div>
                                        <div className="mt-2 text-sm leading-7 text-slate-700">
                                            {comment.answer}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="mt-5 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-sm text-slate-500">
                                No open-text comments were submitted in this attempt.
                            </div>
                        )}
                    </section>

                    <section className="space-y-4">
                        <div>
                            <h3 className="text-lg font-semibold text-slate-900">
                                Responses
                            </h3>
                            <p className="mt-1 text-sm text-slate-500">
                                Only the questions and screens that were actually traversed are shown here.
                            </p>
                        </div>

                        <QuestionReviewList questions={attempt.questions} />
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
