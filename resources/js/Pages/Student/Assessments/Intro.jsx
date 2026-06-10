import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

export default function AssessmentIntro({ lesson, assessment, eligibility, attempt }) {
    return (
        <AuthenticatedLayout
            header={
                <div>
                    <div className="text-sm text-slate-500">
                        {lesson.module?.title ?? 'Assessment'}
                    </div>
                    <h2 className="text-2xl font-semibold text-slate-900">
                        {assessment.title}
                    </h2>
                </div>
            }
        >
            <Head title={assessment.title} />

            <div className="bg-[radial-gradient(circle_at_top,_rgba(93,122,97,0.16),_transparent_35%),linear-gradient(180deg,#f6f3ec_0%,#f8faf8_40%,#f6f7f5_100%)] py-12">
                <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                    <div className="overflow-hidden rounded-[36px] border border-white/60 bg-white/85 shadow-[0_24px_60px_rgba(15,23,42,0.08)] backdrop-blur">
                        <div className="grid gap-0 lg:grid-cols-[1.1fr_0.9fr]">
                            <div className="p-8 lg:p-10">
                                <div className="text-xs font-semibold uppercase tracking-[0.18em] text-[#5f7462]">
                                    Assessment
                                </div>
                                <h3 className="mt-4 text-3xl font-semibold tracking-tight text-slate-900">
                                    {assessment.title}
                                </h3>
                                <p className="mt-4 max-w-2xl text-base leading-7 text-slate-600">
                                    {assessment.description ||
                                        'Move through one screen at a time and let the scoreboard guide the next step.'}
                                </p>

                                <div className="mt-8 grid gap-4 sm:grid-cols-3">
                                    <div className="rounded-2xl bg-[#f4f1e9] px-4 py-4">
                                        <div className="text-xs uppercase tracking-[0.16em] text-slate-500">
                                            Duration
                                        </div>
                                        <div className="mt-2 text-lg font-semibold text-slate-900">
                                            {assessment.duration_minutes
                                                ? `${assessment.duration_minutes} min`
                                                : 'Untimed'}
                                        </div>
                                    </div>
                                    <div className="rounded-2xl bg-[#eef3ee] px-4 py-4">
                                        <div className="text-xs uppercase tracking-[0.16em] text-slate-500">
                                            Progress Bar
                                        </div>
                                        <div className="mt-2 text-lg font-semibold text-slate-900">
                                            {assessment.show_progress_bar ? 'Shown' : 'Hidden'}
                                        </div>
                                    </div>
                                    <div className="rounded-2xl bg-[#f5f6fa] px-4 py-4">
                                        <div className="text-xs uppercase tracking-[0.16em] text-slate-500">
                                            Navigation
                                        </div>
                                        <div className="mt-2 text-lg font-semibold text-slate-900">
                                            {assessment.allow_back_navigation
                                                ? 'Back allowed'
                                                : 'Forward only'}
                                        </div>
                                    </div>
                                </div>

                                {eligibility.is_unlocked ? (
                                    <div className="mt-8 flex flex-wrap gap-3">
                                        <Button
                                            size="lg"
                                            onClick={() =>
                                                router.post(
                                                    route('assessments.start', lesson.id),
                                                )
                                            }
                                        >
                                            {attempt ? 'Resume Assessment' : 'Start Assessment'}
                                        </Button>
                                        <Button asChild variant="outline" size="lg">
                                            <Link href={route('lessons.show', lesson.id)}>
                                                Back to Lesson
                                            </Link>
                                        </Button>
                                    </div>
                                ) : (
                                    <div className="mt-8 rounded-3xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
                                        Assessment remains locked until your video watch progress reaches 95%.
                                        Current watch progress: {eligibility.watch_progress ?? 0}%.
                                    </div>
                                )}
                            </div>

                            <div className="relative bg-[#eef2ec] p-8 lg:p-10">
                                {assessment.thumbnail_url ? (
                                    <img
                                        src={assessment.thumbnail_url}
                                        alt={assessment.title}
                                        className="h-full min-h-[280px] w-full rounded-[28px] object-cover"
                                    />
                                ) : (
                                    <div className="flex h-full min-h-[280px] items-center justify-center rounded-[28px] bg-gradient-to-br from-[#dfe8dd] via-[#f5f3ee] to-white px-8 text-center text-sm font-medium text-slate-500">
                                        Premium assessment intro art can live here through the scoreboard thumbnail.
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
