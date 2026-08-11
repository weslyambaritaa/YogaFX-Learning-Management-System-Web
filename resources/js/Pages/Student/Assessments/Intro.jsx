import { Button } from "@/Components/ui/button";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, router } from "@inertiajs/react";

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
            studentContentClassName="bg-black"
        >
            <Head title={assessment.title} />

            <div className="bg-black pt-6 pb-0 sm:pt-8">
                <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                    <div className="overflow-hidden rounded-[6px] border border-white/10 bg-[#111111] shadow-[0_18px_50px_rgba(0,0,0,0.28)]">
                        <div className="p-6 sm:p-7 lg:p-8">
                            <div className="text-xs font-bold uppercase tracking-[0.18em] text-[#f16d6d]">
                                Assessment
                            </div>

                            <h3 className="mt-3 text-3xl font-bold tracking-tight text-white">
                                {assessment.title}
                            </h3>

                            <p className="mt-4 max-w-3xl text-base font-bold leading-7 text-white">
                                {assessment.description ||
                                    "Move one step at a time and submit when you're ready."}
                            </p>

                            <div className="mt-6 grid gap-3 sm:grid-cols-3">
                                <div className="rounded-[5px] border border-white/10 bg-white/[0.05] px-4 py-4">
                                    <div className="text-xs font-bold uppercase tracking-[0.16em] text-white/70">
                                        Duration
                                    </div>

                                    <div className="mt-2 text-xl font-bold text-white sm:text-[22px]">
                                        {assessment.duration_minutes
                                            ? `${assessment.duration_minutes} min`
                                            : "Untimed"}
                                    </div>
                                </div>

                                <div className="rounded-[5px] border border-white/10 bg-white/[0.05] px-4 py-4">
                                    <div className="text-xs font-bold uppercase tracking-[0.16em] text-white/70">
                                        Progress Bar
                                    </div>

                                    <div className="mt-2 text-xl font-bold text-white sm:text-[22px]">
                                        {assessment.show_progress_bar
                                            ? "Shown"
                                            : "Hidden"}
                                    </div>
                                </div>

                                <div className="rounded-[5px] border border-white/10 bg-white/[0.05] px-4 py-4">
                                    <div className="text-xs font-bold uppercase tracking-[0.16em] text-white/70">
                                        Navigation
                                    </div>

                                    <div className="mt-2 text-xl font-bold text-white sm:text-[22px]">
                                        {assessment.allow_back_navigation
                                            ? "Back allowed"
                                            : "Forward only"}
                                    </div>
                                </div>
                            </div>

                            {completedAttempt ? (
                                <div className="mt-6 rounded-[5px] border border-emerald-400/30 bg-emerald-500/10 px-5 py-4 text-white">
                                    <div className="text-sm font-bold uppercase tracking-[0.16em] text-emerald-200">
                                        Completed
                                    </div>

                                    <p className="mt-2 text-sm font-bold leading-6 text-white">
                                        You already completed this assessment.
                                        Retake is disabled.
                                    </p>

                                    <div className="mt-4 flex flex-wrap gap-3">
                                        <Button
                                            asChild
                                            size="lg"
                                            className="rounded-[5px] bg-[#e24848] font-bold text-white hover:bg-[#f05a5a]"
                                        >
                                            <Link
                                                href={route(
                                                    "assessments.result",
                                                    {
                                                        lesson: lesson.id,
                                                        attempt:
                                                            completedAttempt.id,
                                                    },
                                                )}
                                            >
                                                View Result
                                            </Link>
                                        </Button>

                                        <Button
                                            asChild
                                            variant="outline"
                                            size="lg"
                                            className="rounded-[5px] border-white/20 bg-white/5 font-bold text-white hover:bg-white/10 hover:text-white"
                                        >
                                            <Link
                                                href={route(
                                                    "lessons.show",
                                                    lesson.id,
                                                )}
                                            >
                                                Back to Lesson
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            ) : eligibility.is_unlocked ? (
                                <div className="mt-6 flex flex-wrap gap-3">
                                    <Button
                                        size="lg"
                                        className="rounded-[5px] bg-[#e24848] font-bold text-white hover:bg-[#f05a5a]"
                                        onClick={() =>
                                            router.post(
                                                route(
                                                    "assessments.start",
                                                    lesson.id,
                                                ),
                                            )
                                        }
                                    >
                                        {attempt
                                            ? "Resume Assessment"
                                            : "Start Assessment"}
                                    </Button>

                                    <Button
                                        asChild
                                        variant="outline"
                                        size="lg"
                                        className="rounded-[5px] border-white/20 bg-white/5 font-bold text-white hover:bg-white/10 hover:text-white"
                                    >
                                        <Link
                                            href={route(
                                                "lessons.show",
                                                lesson.id,
                                            )}
                                        >
                                            Back to Lesson
                                        </Link>
                                    </Button>
                                </div>
                            ) : (
                                <div className="mt-6 rounded-[5px] border border-amber-400/30 bg-amber-500/10 px-5 py-4 text-sm font-bold leading-6 text-amber-100">
                                    Assessment remains locked until your video
                                    watch progress reaches 95%. Current watch
                                    progress: {eligibility.watch_progress ?? 0}
                                    %.
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
