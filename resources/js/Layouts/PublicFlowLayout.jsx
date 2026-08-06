import PublicEnrollmentProgress from "@/Components/public/PublicEnrollmentProgress";
import StudentBackButton from "@/Components/student/StudentBackButton";
import { Head, Link } from "@inertiajs/react";
import { Check, LoaderCircle } from "lucide-react";
import { useEffect, useRef, useState } from "react";

const FONT_FAMILY = "'Montserrat', sans-serif";
const COUNTDOWN_SECONDS = 3;

function isMasterclassLandingPath() {
    if (typeof window === "undefined") {
        return false;
    }

    return /^\/masterclass(?:$|[-/])/.test(window.location.pathname);
}

export default function PublicFlowLayout({
    title,
    eyebrow,
    heading,
    description,
    aside,
    children,
    footer,
    showBackButton = false,
    largeLogo = false,
    showMasterclassWelcome = true,
    progressStep = null,
}) {
    const [showWelcomeOverlay, setShowWelcomeOverlay] = useState(
        () => showMasterclassWelcome && isMasterclassLandingPath(),
    );
    const [secondsRemaining, setSecondsRemaining] = useState(COUNTDOWN_SECONDS);
    const [isWelcomeOverlayClosing, setIsWelcomeOverlayClosing] =
        useState(false);

    const welcomeOverlayTimerRef = useRef(null);
    const isCountingDown = secondsRemaining > 0;

    useEffect(() => {
        if (!showWelcomeOverlay) {
            return undefined;
        }

        const previousBodyOverflow = document.body.style.overflow;
        const previousHtmlOverflow = document.documentElement.style.overflow;

        document.body.style.overflow = "hidden";
        document.documentElement.style.overflow = "hidden";

        return () => {
            document.body.style.overflow = previousBodyOverflow;
            document.documentElement.style.overflow = previousHtmlOverflow;
        };
    }, [showWelcomeOverlay]);

    useEffect(() => {
        if (!showWelcomeOverlay || !isCountingDown) {
            return undefined;
        }

        const timeoutId = window.setTimeout(() => {
            setSecondsRemaining((current) => Math.max(current - 1, 0));
        }, 1000);

        return () => {
            window.clearTimeout(timeoutId);
        };
    }, [showWelcomeOverlay, isCountingDown, secondsRemaining]);

    useEffect(() => {
        return () => {
            if (welcomeOverlayTimerRef.current) {
                window.clearTimeout(welcomeOverlayTimerRef.current);
            }
        };
    }, []);

    const closeWelcomeOverlay = () => {
        if (isCountingDown || isWelcomeOverlayClosing) {
            return;
        }

        setIsWelcomeOverlayClosing(true);

        welcomeOverlayTimerRef.current = window.setTimeout(() => {
            setShowWelcomeOverlay(false);
            setIsWelcomeOverlayClosing(false);
        }, 750);
    };

    return (
        <>
            <Head title={title} />

            <div className="min-h-screen bg-black text-white">
                <div className="absolute inset-0" />

                <div className="relative mx-auto flex min-h-screen max-w-[1280px] flex-col px-4 py-6 sm:px-6 lg:px-10">
                    {showBackButton ? (
                        <div className="w-full pt-4">
                            <StudentBackButton fallbackHref={route("login")} />
                        </div>
                    ) : null}

                    <div className="flex flex-1 flex-col items-center justify-center pb-12 pt-6 lg:pt-8">
                        <header
                            className={[
                                "flex items-center justify-center",
                                progressStep
                                    ? "mb-5"
                                    : largeLogo
                                      ? "mb-10"
                                      : "mb-8",
                            ].join(" ")}
                        >
                            <Link
                                href="/"
                                className="inline-flex items-center justify-center"
                            >
                                <img
                                    src="https://yogafx.b-cdn.net/content/Logo%20YogAFX.png"
                                    alt="YogaFX"
                                    className={
                                        largeLogo
                                            ? "h-28 w-auto object-contain sm:h-32"
                                            : "h-16 w-auto object-contain"
                                    }
                                />
                            </Link>
                        </header>

                        {progressStep ? (
                            <div className="mb-8 w-full">
                                <PublicEnrollmentProgress
                                    currentStep={progressStep}
                                />
                            </div>
                        ) : null}

                        <main className="flex w-full flex-col items-center justify-center">
                            <div className="flex w-full max-w-xl flex-col items-center gap-8">
                                <section className="w-full">
                                    <div className="flex w-full flex-col space-y-5 text-center">
                                        {(eyebrow ||
                                            heading ||
                                            description) && (
                                            <div className="space-y-3">
                                                {eyebrow && (
                                                    <p className="text-sm font-semibold text-white">
                                                        {eyebrow}
                                                    </p>
                                                )}

                                                {heading && (
                                                    <h1 className="mx-auto max-w-3xl text-3xl font-semibold tracking-[-0.04em] text-white sm:text-4xl">
                                                        {heading}
                                                    </h1>
                                                )}

                                                {description && (
                                                    <p className="mx-auto max-w-2xl text-sm leading-7 text-white/70 sm:text-base">
                                                        {description}
                                                    </p>
                                                )}
                                            </div>
                                        )}

                                        <div className="w-full py-4 text-left">
                                            {children}
                                        </div>

                                        {footer && (
                                            <div className="mt-2 w-full">
                                                {footer}
                                            </div>
                                        )}
                                    </div>
                                </section>

                                {aside && (
                                    <aside className="w-full text-center">
                                        {aside}
                                    </aside>
                                )}
                            </div>
                        </main>
                    </div>
                </div>
            </div>

            {showWelcomeOverlay ? (
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="masterclass-welcome-title"
                    className={[
                        "fixed inset-0 z-[9999]",
                        "flex min-h-[100dvh] w-full",
                        "items-center justify-center overflow-hidden",
                        "bg-black px-4 py-4 text-white sm:px-6 sm:py-8",
                        "transition-transform duration-700",
                        "ease-[cubic-bezier(0.76,0,0.24,1)]",
                        "will-change-transform",
                        isWelcomeOverlayClosing
                            ? "-translate-y-full"
                            : "translate-y-0",
                    ].join(" ")}
                    style={{ fontFamily: FONT_FAMILY }}
                >
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-0"
                        style={{
                            backgroundImage:
                                "radial-gradient(circle at 50% 45%, rgba(219,32,44,0.16), transparent 34%), radial-gradient(circle at 85% 90%, rgba(219,32,44,0.10), transparent 28%)",
                        }}
                    />

                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-[#DB202C]/70 to-transparent"
                    />

                    <div
                        className={[
                            "relative z-10 mx-auto w-full max-w-lg",
                            "transition-all duration-500",
                            isWelcomeOverlayClosing
                                ? "-translate-y-14 opacity-0"
                                : "translate-y-0 opacity-100",
                        ].join(" ")}
                    >
                        <div
                            className="
                                flex
                                min-h-[410px]
                                w-full
                                flex-col
                                items-center
                                justify-center
                                overflow-y-auto
                                rounded-[18px]
                                border
                                border-white/15
                                bg-[#111111]
                                px-6
                                py-8
                                text-center
                                shadow-[0_24px_80px_rgba(0,0,0,0.45)]
                                sm:min-h-[450px]
                                sm:px-10
                                sm:py-10
                            "
                        >
                            {isCountingDown ? (
                                <div
                                    className="flex w-full flex-col items-center justify-center"
                                    role="status"
                                    aria-live="polite"
                                >
                                    <div className="relative h-24 w-24 shrink-0">
                                        <LoaderCircle
                                            className="absolute inset-0 h-24 w-24 animate-spin text-[#DB202C] motion-reduce:animate-none"
                                            strokeWidth={1.8}
                                            aria-hidden="true"
                                        />

                                        <div className="absolute inset-0 flex items-center justify-center">
                                            <span
                                                key={secondsRemaining}
                                                className="text-3xl font-bold leading-none text-white"
                                            >
                                                {secondsRemaining}
                                            </span>
                                        </div>
                                    </div>

                                    <p className="mt-6 text-sm font-semibold leading-6 text-white">
                                        Loading...
                                    </p>
                                </div>
                            ) : (
                                <div className="flex w-full flex-col items-center justify-center">
                                    <div className="flex h-20 w-20 shrink-0 items-center justify-center rounded-full bg-emerald-500 shadow-[0_0_34px_rgba(16,185,129,0.24)]">
                                        <Check
                                            className="h-11 w-11 text-white"
                                            strokeWidth={3}
                                            aria-hidden="true"
                                        />
                                    </div>

                                    <div className="mt-6 w-full space-y-4">
                                        <h1
                                            id="masterclass-welcome-title"
                                            className="mx-auto max-w-xl text-2xl font-bold leading-tight text-white sm:text-3xl"
                                        >
                                            Congratulations!
                                        </h1>

                                        <p className="mx-auto max-w-xl text-base font-semibold leading-7 text-white sm:text-lg sm:leading-8">
                                            We Are Thrilled That You Are Joining
                                            Mr. Ian&apos;s Bikram Hot Yoga
                                            26&amp;2 Yoga Teacher Training
                                        </p>

                                        <p className="text-base font-medium italic leading-7 text-white sm:text-lg">
                                            Your Enrollment Starts Now
                                        </p>
                                    </div>

                                    <button
                                        type="button"
                                        onClick={closeWelcomeOverlay}
                                        disabled={isWelcomeOverlayClosing}
                                        className="
                                            mt-8
                                            inline-flex
                                            min-h-[56px]
                                            w-full
                                            max-w-[320px]
                                            items-center
                                            justify-center
                                            rounded-[5px]
                                            bg-[#DB202C]
                                            px-8
                                            py-4
                                            text-base
                                            font-bold
                                            text-white
                                            shadow-[0_14px_38px_rgba(219,32,44,0.30)]
                                            transition-all
                                            duration-200
                                            hover:-translate-y-0.5
                                            hover:bg-[#c31c28]
                                            focus:outline-none
                                            focus:ring-4
                                            focus:ring-[#DB202C]/35
                                            disabled:cursor-wait
                                            disabled:opacity-80
                                        "
                                        style={{ fontFamily: FONT_FAMILY }}
                                    >
                                        Let&apos;s Get Started
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            ) : null}
        </>
    );
}
