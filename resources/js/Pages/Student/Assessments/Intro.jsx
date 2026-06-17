import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

export default function AssessmentIntro({
    lesson,
    assessment,
    eligibility,
    attempt,
    completedAttempt,
}) {
    return (
        <AuthenticatedLayout
            studentVariant="immersive"
            studentContentClassName="bg-[#070707]"
            header={
                <div>
                    <div className="text-sm text-white/60">
                        {lesson.module?.title ?? 'Assessment'}
                    </div>
                    <h2 className="text-2xl font-semibold text-white">
                        {assessment.title}
                    </h2>
                </div>
            }
        >
            <Head title={assessment.title} />

            <div className="bg-[radial-gradient(circle_at_top,_rgba(170,42,42,0.22),_transparent_28%),linear-gradient(180deg,#0d0d0d_0%,#080808_38%,#040404_100%)] py-12">
                <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                    <div className="overflow-hidden rounded-[36px] border border-white/10 bg-white/5 shadow-[0_24px_80px_rgba(0,0,0,0.38)] backdrop-blur">
                        <div className="grid gap-0 lg:grid-cols-[1.1fr_0.9fr]">
                            <div className="p-8 lg:p-10">
                                <div className="text-xs font-semibold uppercase tracking-[0.18em] text-[#f16d6d]">
                                    Assessment
                                </div>
                                <h3 className="mt-4 text-3xl font-semibold tracking-tight text-white">
                                    {assessment.title}
                                </h3>
                                <p className="mt-4 max-w-2xl text-base leading-7 text-white/72">
                                    {assessment.description ||
                                        'Move through one screen at a time and let the scoreboard guide the next step.'}
                                </p>

                                <div className="mt-8 grid gap-4 sm:grid-cols-3">
                                    <div className="rounded-2xl border border-white/10 bg-white/6 px-4 py-4">
                                        <div className="text-xs uppercase tracking-[0.16em] text-white/45">
                                            Duration
                                        </div>
                                        <div className="mt-2 text-lg font-semibold text-white">
                                            {assessment.duration_minutes
                                                ? `${assessment.duration_minutes} min`
                                                : 'Untimed'}
                                        </div>
                                    </div>
                                    <div className="rounded-2xl border border-white/10 bg-white/6 px-4 py-4">
                                        <div className="text-xs uppercase tracking-[0.16em] text-white/45">
                                            Progress Bar
                                        </div>
                                        <div className="mt-2 text-lg font-semibold text-white">
                                            {assessment.show_progress_bar ? 'Shown' : 'Hidden'}
                                        </div>
                                    </div>
                                    <div className="rounded-2xl border border-white/10 bg-white/6 px-4 py-4">
                                        <div className="text-xs uppercase tracking-[0.16em] text-white/45">
                                            Navigation
                                        </div>
                                        <div className="mt-2 text-lg font-semibold text-white">
                                            {assessment.allow_back_navigation
                                                ? 'Back allowed'
                                                : 'Forward only'}
                                        </div>
                                    </div>
                                </div>

                                {completedAttempt ? (
                                    <div className="mt-8 rounded-3xl border border-emerald-400/30 bg-emerald-500/10 px-5 py-5 text-white">
                                        <div className="text-sm font-semibold uppercase tracking-[0.16em] text-emerald-200">
                                            Completed
                                        </div>
                                        <p className="mt-3 text-sm leading-7 text-white/78">
                                            You have already completed this assessment. Retake is disabled, so we will keep you on the final result state instead of starting a new attempt.
                                        </p>
                                        <div className="mt-4 flex flex-wrap gap-3">
                                            <Button asChild size="lg" className="bg-[#e24848] text-white hover:bg-[#f05a5a]">
                                                <Link
                                                    href={route('assessments.result', {
                                                        lesson: lesson.id,
                                                        attempt: completedAttempt.id,
                                                    })}
                                                >
                                                    View Result
                                                </Link>
                                            </Button>
                                            <Button asChild variant="outline" size="lg" className="border-white/20 bg-white/5 text-white hover:bg-white/10 hover:text-white">
                                                <Link href={route('lessons.show', lesson.id)}>
                                                    Back to Lesson
                                                </Link>
                                            </Button>
                                        </div>
                                    </div>
                                ) : eligibility.is_unlocked ? (
                                    <div className="mt-8 flex flex-wrap gap-3">
                                        <Button
                                            size="lg"
                                            className="bg-[#e24848] text-white hover:bg-[#f05a5a]"
                                            onClick={() =>
                                                router.post(
                                                    route('assessments.start', lesson.id),
                                                )
                                            }
                                        >
                                            {attempt ? 'Resume Assessment' : 'Start Assessment'}
                                        </Button>
                                        <Button asChild variant="outline" size="lg" className="border-white/20 bg-white/5 text-white hover:bg-white/10 hover:text-white">
                                            <Link href={route('lessons.show', lesson.id)}>
                                                Back to Lesson
                                            </Link>
                                        </Button>
                                    </div>
                                ) : (
                                    <div className="mt-8 rounded-3xl border border-amber-400/30 bg-amber-500/10 px-5 py-4 text-sm text-amber-100">
                                        Assessment remains locked until your video watch progress reaches 95%.
                                        Current watch progress: {eligibility.watch_progress ?? 0}%.
                                    </div>
                                )}
                            </div>

                            <div className="relative bg-[#101010] p-8 lg:p-10">
                                {assessment.thumbnail_url ? (
                                    <img
                                        src={assessment.thumbnail_url}
                                        alt={assessment.title}
                                        className="h-full min-h-[280px] w-full rounded-[28px] object-cover"
                                    />
                                ) : (
                                    <div className="flex h-full min-h-[280px] items-center justify-center rounded-[28px] border border-white/10 bg-[radial-gradient(circle_at_top,_rgba(226,72,72,0.22),_transparent_32%),linear-gradient(180deg,#181818_0%,#0d0d0d_100%)] px-8 text-center text-sm font-medium text-white/50">
                                        Assessment cover art can live here through the scoreboard thumbnail.
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
