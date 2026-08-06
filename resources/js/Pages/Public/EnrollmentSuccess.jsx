import { Button } from "@/Components/ui/button";
import YogaFXText from "@/Components/YogaFXText";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";
import { Link, router } from "@inertiajs/react";
import { Check, LoaderCircle } from "lucide-react";
import { useEffect, useState } from "react";

const FONT_FAMILY = "'Montserrat', sans-serif";
const INITIAL_COUNTDOWN_SECONDS = 5;
const REDIRECT_COUNTDOWN_SECONDS = 3;

export default function EnrollmentSuccess({
    onboarding,
    student = null,
}) {
    const [secondsRemaining, setSecondsRemaining] = useState(
        INITIAL_COUNTDOWN_SECONDS,
    );

    const [isRedirecting, setIsRedirecting] = useState(false);

    const [redirectSecondsRemaining, setRedirectSecondsRemaining] =
        useState(REDIRECT_COUNTDOWN_SECONDS);

    const packageTitle =
        onboarding?.package?.title ??
        onboarding?.access_tier?.name ??
        "Your Package";

    const continueUrl = onboarding?.continue_url ?? "";

    const fullNameFromFields = [
        student?.first_name ?? onboarding?.student?.first_name,
        student?.last_name ?? onboarding?.student?.last_name,
    ]
        .filter(Boolean)
        .join(" ")
        .trim();

    const studentName = String(
        student?.name ??
            onboarding?.student?.name ??
            fullNameFromFields,
    ).trim();

    const isInitialLoading = secondsRemaining > 0;
    const showLoading = isInitialLoading || isRedirecting;

    const displayedLoadingSeconds = isRedirecting
        ? redirectSecondsRemaining
        : secondsRemaining;

    useEffect(() => {
        if (!isInitialLoading) {
            return undefined;
        }

        const timeoutId = window.setTimeout(() => {
            setSecondsRemaining((current) =>
                Math.max(current - 1, 0),
            );
        }, 1000);

        return () => {
            window.clearTimeout(timeoutId);
        };
    }, [isInitialLoading, secondsRemaining]);

    useEffect(() => {
        if (!isRedirecting || !continueUrl) {
            return undefined;
        }

        if (redirectSecondsRemaining <= 0) {
            router.visit(continueUrl);
            return undefined;
        }

        const timeoutId = window.setTimeout(() => {
            setRedirectSecondsRemaining((current) =>
                Math.max(current - 1, 0),
            );
        }, 1000);

        return () => {
            window.clearTimeout(timeoutId);
        };
    }, [
        continueUrl,
        isRedirecting,
        redirectSecondsRemaining,
    ]);

    const handleContinue = (event) => {
        event.preventDefault();

        if (!continueUrl || isRedirecting) {
            return;
        }

        setRedirectSecondsRemaining(REDIRECT_COUNTDOWN_SECONDS);
        setIsRedirecting(true);
    };

    return (
        <PublicFlowLayout
            title="Enrollment Success"
            eyebrow={showLoading ? null : "\u00A0"}
            heading={
                showLoading
                    ? null
                    : studentName
                      ? `Congratulations, ${studentName}`
                      : "Congratulations"
            }
        >
            <div
                className="flex justify-center pb-8 pt-2"
                style={{ fontFamily: FONT_FAMILY }}
            >
                <div className="w-full max-w-2xl text-center">
                    {showLoading ? (
                        <div
                            className="mx-auto max-w-lg rounded-[18px] border border-white/15 bg-[#111111] px-6 py-10 text-center shadow-[0_24px_80px_rgba(0,0,0,0.45)] sm:px-8"
                            role="status"
                            aria-live="polite"
                        >
                            <div className="relative mx-auto h-24 w-24">
                                <LoaderCircle
                                    className="absolute inset-0 h-24 w-24 animate-spin text-[#DB202C] motion-reduce:animate-none"
                                    strokeWidth={1.8}
                                    aria-hidden="true"
                                />

                                <div className="absolute inset-0 flex items-center justify-center">
                                    <span
                                        key={displayedLoadingSeconds}
                                        className="text-3xl font-bold text-white"
                                    >
                                        {displayedLoadingSeconds}
                                    </span>
                                </div>
                            </div>

                            <p className="mt-6 text-sm font-semibold text-white">
                                Loading...
                            </p>
                        </div>
                    ) : (
                        <>
                            <div className="mx-auto flex h-32 w-32 items-center justify-center rounded-full bg-emerald-500 shadow-[0_0_45px_rgba(16,185,129,0.35)]">
                                <Check
                                    className="h-20 w-20 text-white"
                                    strokeWidth={3}
                                    aria-hidden="true"
                                />
                            </div>

                            <h2 className="mt-10 text-3xl font-bold leading-tight text-white md:text-4xl">
                                Your Enrollment Application Approved.
                            </h2>

                            <p className="mx-auto mt-6 max-w-xl text-lg font-semibold leading-relaxed text-white">
                                <YogaFXText
                                    text={`To Access ${packageTitle}, Pre-course Preparation`}
                                    fxClassName="!text-[#DB202C]"
                                />
                            </p>

                            <Button
                                asChild
                                className="mt-9 min-h-[64px] w-full max-w-[360px] rounded-[8px] bg-[#DB202C] px-10 py-6 text-base font-bold italic text-white shadow-[0_12px_35px_rgba(219,32,44,0.3)] transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#c01a25]"
                            >
                                <Link
                                    href={continueUrl || "#"}
                                    onClick={handleContinue}
                                    className="inline-flex items-center justify-center whitespace-nowrap"
                                >
                                    Click Here
                                </Link>
                            </Button>
                        </>
                    )}
                </div>
            </div>
        </PublicFlowLayout>
    );
}