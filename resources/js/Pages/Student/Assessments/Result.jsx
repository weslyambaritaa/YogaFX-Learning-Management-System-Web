import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

function completionIntroStorageKey(attemptId) {
    return `assessment-result-intro-seen-${attemptId}`;
}

export default function AssessmentResult({ lesson, assessment, attempt }) {
    const [stage, setStage] = useState('loading');
    const [countdown, setCountdown] = useState(5);

    useEffect(() => {
        const storageKey = completionIntroStorageKey(attempt.id);

        if (window.sessionStorage.getItem(storageKey) === '1') {
            setStage('result');

            return undefined;
        }

        setStage('loading');
        setCountdown(5);

        const interval = window.setInterval(() => {
            setCountdown((currentValue) => {
                if (currentValue <= 0) {
                    window.clearInterval(interval);
                    setStage('success');

                    return 0;
                }

                return currentValue - 1;
            });
        }, 1000);

        const successTimeout = window.setTimeout(() => {
            window.sessionStorage.setItem(storageKey, '1');
            setStage('result');
        }, 7000);

        return () => {
            window.clearInterval(interval);
            window.clearTimeout(successTimeout);
        };
    }, [attempt.id]);

    return (
        <AuthenticatedLayout
            studentVariant="immersive"
            studentContentClassName="bg-[#060606]"
            header={
                <div>
                    <div className="text-sm text-white/55">{lesson.title}</div>
                    <h2 className="text-2xl font-semibold text-white">
                        Assessment Result
                    </h2>
                </div>
            }
        >
            <Head title={`${assessment.title} Result`} />

            <div className="bg-[radial-gradient(circle_at_top,_rgba(226,72,72,0.18),_transparent_28%),linear-gradient(180deg,#111111_0%,#080808_38%,#030303_100%)] py-12">
                <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                    {stage === 'loading' && (
                        <div className="rounded-[36px] border border-white/10 bg-white/6 p-8 text-center shadow-[0_24px_80px_rgba(0,0,0,0.35)] backdrop-blur md:p-12">
                            <div className="mx-auto flex size-24 items-center justify-center rounded-full border border-white/12 bg-white/8 text-4xl font-semibold text-white">
                                {countdown}
                            </div>
                            <div className="mx-auto mt-8 max-w-2xl">
                                <div className="text-xs font-semibold uppercase tracking-[0.18em] text-red-200/90">
                                    Finalizing Assessment
                                </div>
                                <h3 className="mt-4 text-4xl font-semibold tracking-tight text-white">
                                    Preparing your final result
                                </h3>
                                <p className="mt-3 text-base leading-7 text-white/72">
                                    Your answers are being wrapped into the final YogaFX assessment experience.
                                </p>
                            </div>
                        </div>
                    )}

                    {stage === 'success' && (
                        <div className="rounded-[36px] border border-white/10 bg-white/6 p-8 text-center shadow-[0_24px_80px_rgba(0,0,0,0.35)] backdrop-blur md:p-12">
                            <div className="mx-auto flex size-24 items-center justify-center rounded-full border border-emerald-300/25 bg-emerald-500/15 text-emerald-100">
                                <svg
                                    viewBox="0 0 24 24"
                                    className="size-12"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="2.4"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    aria-hidden="true"
                                >
                                    <path d="M5 12.5l4.2 4.2L19 7.4" />
                                </svg>
                            </div>
                            <div className="mx-auto mt-8 max-w-2xl">
                                <div className="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-200/90">
                                    Assessment Complete
                                </div>
                                <h3 className="mt-4 text-4xl font-semibold tracking-tight text-white">
                                    Your result is ready
                                </h3>
                                <p className="mt-3 text-base leading-7 text-white/72">
                                    The final summary is ready to review.
                                </p>
                            </div>
                        </div>
                    )}

                    {stage === 'result' && (
                        <div className="rounded-[36px] border border-white/10 bg-white/6 p-8 text-center shadow-[0_24px_80px_rgba(0,0,0,0.35)] backdrop-blur md:p-12">
                            <div className="mx-auto max-w-2xl">
                                <div className="text-xs font-semibold uppercase tracking-[0.18em] text-red-200/90">
                                    Assessment Complete
                                </div>
                                <h3 className="mt-4 text-4xl font-semibold tracking-tight text-white">
                                    {attempt.total_score}
                                </h3>
                                <p className="mt-3 text-base leading-7 text-white/72">
                                    {attempt.result_label
                                        ? `${attempt.result_label}${attempt.result_description ? ` - ${attempt.result_description}` : ''}`
                                        : 'This scoreboard does not use result ranges yet, so your raw score is shown directly.'}
                                </p>
                                <p className="mt-4 text-sm text-white/46">
                                    This assessment has already been completed. Retake is currently disabled.
                                </p>

                                <div className="mt-8 grid gap-4 md:grid-cols-3">
                                    <div className="rounded-2xl border border-white/10 bg-white/6 px-4 py-4">
                                        <div className="text-xs uppercase tracking-[0.16em] text-white/45">
                                            Status
                                        </div>
                                        <div className="mt-2 text-lg font-semibold text-white">
                                            {attempt.status}
                                        </div>
                                    </div>
                                    <div className="rounded-2xl border border-white/10 bg-white/6 px-4 py-4">
                                        <div className="text-xs uppercase tracking-[0.16em] text-white/45">
                                            Finished By
                                        </div>
                                        <div className="mt-2 text-lg font-semibold text-white">
                                            {attempt.finished_reason}
                                        </div>
                                    </div>
                                    <div className="rounded-2xl border border-white/10 bg-white/6 px-4 py-4">
                                        <div className="text-xs uppercase tracking-[0.16em] text-white/45">
                                            Completed At
                                        </div>
                                        <div className="mt-2 text-sm font-semibold text-white">
                                            {attempt.completed_at ?? 'Just now'}
                                        </div>
                                    </div>
                                </div>

                                <div className="mt-8 flex flex-wrap justify-center gap-3">
                                    <Button
                                        asChild
                                        size="lg"
                                        className="bg-[#e24848] text-white hover:bg-[#f05a5a]"
                                    >
                                        <Link href={route('lessons.show', lesson.id)}>
                                            Return to Lesson
                                        </Link>
                                    </Button>
                                    {lesson.module && (
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="lg"
                                            className="border-white/20 bg-white/5 text-white hover:bg-white/10 hover:text-white"
                                        >
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
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
