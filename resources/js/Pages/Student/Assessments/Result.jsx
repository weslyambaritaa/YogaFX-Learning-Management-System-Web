import { Button } from "@/Components/ui/button";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link } from "@inertiajs/react";
import { Check } from "lucide-react";
import { useEffect, useState } from "react";

function completionIntroStorageKey(attemptId) {
    return `assessment-result-intro-seen-${attemptId}`;
}

export default function AssessmentResult({
    lesson,
    assessment,
    attempt,
    nextLesson,
}) {
    const [stage, setStage] = useState("loading");
    const [countdown, setCountdown] = useState(5);

    useEffect(() => {
        const storageKey = completionIntroStorageKey(attempt.id);

        if (window.sessionStorage.getItem(storageKey) === "1") {
            setStage("result");

            return undefined;
        }

        setStage("loading");
        setCountdown(5);

        const interval = window.setInterval(() => {
            setCountdown((currentValue) => {
                if (currentValue <= 0) {
                    window.clearInterval(interval);
                    setStage("success");

                    return 0;
                }

                return currentValue - 1;
            });
        }, 1000);

        const successTimeout = window.setTimeout(() => {
            window.sessionStorage.setItem(storageKey, "1");
            setStage("result");
        }, 7000);

        return () => {
            window.clearInterval(interval);
            window.clearTimeout(successTimeout);
        };
    }, [attempt.id]);

    return (
        <AuthenticatedLayout
            studentVariant="immersive"
            studentContentClassName="bg-black"
            header={
                <div>
                    <div className="text-sm font-bold text-white/70">
                        {lesson.title}
                    </div>
                    <h2 className="text-2xl font-bold text-white">
                        Assessment Result
                    </h2>
                </div>
            }
        >
            <Head title={`${assessment.title} Result`} />

            <div className="bg-black pt-6 pb-0 sm:pt-8">
                <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                    {stage === "loading" && (
                        <div className="rounded-[6px] border border-white/10 bg-[#111111] p-6 text-center shadow-[0_18px_50px_rgba(0,0,0,0.28)] md:p-8">
                            <div className="relative mx-auto flex size-28 items-center justify-center">
                                <div className="absolute inset-0 rounded-full border-4 border-white/10" />
                                <div className="absolute inset-0 animate-spin rounded-full border-4 border-transparent border-t-[#e24848] border-r-[#e24848]/60" />
                                <div className="relative flex size-20 items-center justify-center rounded-full border border-white/12 bg-white/8 text-4xl font-semibold text-white">
                                    {Math.max(countdown, 1)}
                                </div>
                            </div>
                            <div className="mx-auto mt-6 max-w-2xl">
                                <div className="text-xs font-bold uppercase tracking-[0.18em] text-red-200/90">
                                    Finalizing Assessment
                                </div>
                                <h3 className="mt-4 text-4xl font-bold tracking-tight text-white">
                                    Preparing your final result
                                </h3>
                                <p className="mt-3 text-base font-bold leading-7 text-white/80">
                                    We are preparing your result.
                                </p>
                            </div>
                        </div>
                    )}

                    {stage === "success" && (
                        <div className="rounded-[6px] border border-white/10 bg-[#111111] p-6 text-center shadow-[0_18px_50px_rgba(0,0,0,0.28)] md:p-8">
                            <div className="mx-auto flex size-24 items-center justify-center rounded-full bg-emerald-500 shadow-[0_0_36px_rgba(16,185,129,0.28)]">
                                <Check
                                    className="size-14 text-white"
                                    strokeWidth={4.5}
                                    aria-hidden="true"
                                />
                            </div>
                            <div className="mx-auto mt-6 max-w-2xl">
                                <div className="mt-6 text-xs font-bold uppercase tracking-[0.18em] text-emerald-200">
                                    Assessment Complete
                                </div>
                                <h3 className="mt-4 text-4xl font-bold tracking-tight text-white">
                                    Your result is ready
                                </h3>
                                <p className="mt-3 text-base font-bold leading-7 text-white/80">
                                    The final summary is ready to review.
                                </p>
                            </div>
                        </div>
                    )}

                    {stage === "result" && (
                        <div className="rounded-[6px] border border-white/10 bg-[#111111] p-6 text-center shadow-[0_18px_50px_rgba(0,0,0,0.28)] md:p-8">
                            <div className="mx-auto max-w-2xl">
                                <div className="mx-auto flex size-24 items-center justify-center rounded-full bg-emerald-500 shadow-[0_0_36px_rgba(16,185,129,0.28)]">
                                    <Check
                                        className="size-14 text-white"
                                        strokeWidth={4.5}
                                        aria-hidden="true"
                                    />
                                </div>
                                <div className="text-xs font-bold uppercase tracking-[0.18em] text-red-200/90">
                                    Assessment Complete
                                </div>
                                <h3 className="mt-4 text-4xl font-bold tracking-tight text-white">
                                    {attempt.percentage_correct}%
                                </h3>
                                <p className="mt-3 text-base font-bold leading-7 text-white/80">
                                    {attempt.gradable_questions > 0
                                        ? `You answered ${attempt.correct_answers} of ${attempt.gradable_questions} graded questions correctly.`
                                        : "This assessment does not have auto-graded questions yet, so no correctness percentage is available."}
                                </p>
                                <p className="mt-4 text-sm font-bold text-white/70">
                                    This assessment is complete. Retake is
                                    disabled.
                                </p>

                                <div className="mt-6 grid gap-3 md:grid-cols-3">
                                    <div className="rounded-[5px] border border-white/10 bg-white/[0.05] px-4 py-4">
                                        <div className="text-xs font-bold uppercase tracking-[0.16em] text-white/70">
                                            Correct Answers
                                        </div>
                                        <div className="mt-2 text-xl font-bold text-white">
                                            {attempt.correct_answers}/
                                            {attempt.gradable_questions}
                                        </div>
                                    </div>
                                    <div className="rounded-[5px] border border-white/10 bg-white/[0.05] px-4 py-4">
                                        <div className="text-xs font-bold uppercase tracking-[0.16em] text-white/70">
                                            Status
                                        </div>
                                        <div className="mt-2 text-xl font-bold text-white">
                                            {attempt.status}
                                        </div>
                                    </div>
                                    <div className="rounded-[5px] border border-white/10 bg-white/[0.05] px-4 py-4">
                                        <div className="text-xs font-bold uppercase tracking-[0.16em] text-white/70">
                                            Completed At
                                        </div>
                                        <div className="mt-2 text-base font-bold text-white">
                                            {attempt.completed_at ?? "Just now"}
                                        </div>
                                    </div>
                                </div>

                                <div className="mt-6 flex flex-wrap justify-center gap-3">
                                    {nextLesson?.url && (
                                        <Button
                                            asChild
                                            size="lg"
                                            className="rounded-[5px] bg-[#e24848] font-bold text-white hover:bg-[#f05a5a]"
                                        >
                                            <Link href={nextLesson.url}>
                                                Next Lesson
                                            </Link>
                                        </Button>
                                    )}
                                    <Button
                                        asChild
                                        size="lg"
                                        className="rounded-[5px] bg-white/10 font-bold text-white hover:bg-white/15"
                                    >
                                        <Link
                                            href={route(
                                                "lessons.show",
                                                lesson.id,
                                            )}
                                        >
                                            Return to Lesson
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
