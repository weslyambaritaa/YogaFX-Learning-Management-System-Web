import PublicEnrollmentProgress from "@/Components/public/PublicEnrollmentProgress";
import StudentBackButton from "@/Components/student/StudentBackButton";
import { Head, Link } from "@inertiajs/react";
import { Check, LoaderCircle } from "lucide-react";
import { useEffect, useState } from "react";

const FONT_FAMILY = "'Montserrat', sans-serif";
const COUNTDOWN_SECONDS = 5;

function isMasterclassLandingPath() {
    if (typeof window === "undefined") {
        return false;
    }

    return /^\/masterclass(?:$|[-/])/.test(window.location.pathname);
}

function MasterclassLoadingCard({ secondsRemaining }) {
    return (
        <div
            className="flex min-h-[410px] w-full max-w-lg flex-col items-center justify-center rounded-[18px] border border-white/15 bg-[#111111] px-6 py-10 text-center shadow-[0_24px_80px_rgba(0,0,0,0.45)] sm:min-h-[450px] sm:px-10"
            role="status"
            aria-live="polite"
            aria-label={`Loading ${secondsRemaining} seconds`}
        >
            <img
                src="https://yogafx.b-cdn.net/content/Logo%20YogAFX.png"
                alt="YogaFX"
                className="mb-8 h-16 w-auto object-contain sm:h-20"
            />

            <div className="relative h-24 w-24 shrink-0">
                <LoaderCircle
                    className="absolute inset-0 h-24 w-24 animate-spin text-[#DB202C] drop-shadow-[0_0_10px_rgba(219,32,44,0.85)] motion-reduce:animate-none"
                    strokeWidth={2.4}
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

            <p className="mt-6 text-sm font-bold text-white">Loading...</p>
        </div>
    );
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

    const [welcomePhase, setWelcomePhase] = useState("initial-countdown");

    const [secondsRemaining, setSecondsRemaining] = useState(COUNTDOWN_SECONDS);

    const isCountingDown =
        welcomePhase === "initial-countdown" ||
        welcomePhase === "final-countdown";

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
            if (secondsRemaining <= 1) {
                if (welcomePhase === "initial-countdown") {
                    setSecondsRemaining(0);
                    setWelcomePhase("welcome");
                    return;
                }

                if (welcomePhase === "final-countdown") {
                    setSecondsRemaining(0);
                    setShowWelcomeOverlay(false);
                }

                return;
            }

            setSecondsRemaining((current) => Math.max(current - 1, 1));
        }, 1000);

        return () => {
            window.clearTimeout(timeoutId);
        };
    }, [showWelcomeOverlay, isCountingDown, secondsRemaining, welcomePhase]);

    const startFinalCountdown = () => {
        if (welcomePhase !== "welcome") {
            return;
        }

        setSecondsRemaining(COUNTDOWN_SECONDS);
        setWelcomePhase("final-countdown");
    };

    return (
        <>
            <Head title={title} />

            <div className="relative isolate min-h-screen overflow-x-hidden bg-black text-white">
                {progressStep ? (
                    <div
                        aria-hidden="true"
                        className="pointer-events-none fixed inset-0 z-0 flex items-center justify-center overflow-hidden"
                    >
                        <img
                            src="/images/yogafx-white-icon.png"
                            alt=""
                            className="w-[300px] max-w-none select-none opacity-[0.15] sm:w-[420px] lg:w-[560px]"
                        />
                    </div>
                ) : null}

                <div className="relative z-10 mx-auto flex min-h-screen max-w-[1280px] flex-col px-4 py-6 sm:px-6 lg:px-10">
                    {showBackButton ? (
                        <div className="w-full pt-4">
                            <StudentBackButton fallbackHref={route("login")} />
                        </div>
                    ) : null}

                    <div
                        className={[
                            "flex w-full flex-col items-center",
                            progressStep
                                ? "flex-none justify-start pt-0"
                                : "flex-1 justify-center pt-6 lg:pt-8",
                        ].join(" ")}
                    >
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
                            <div className="mb-5 w-full">
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

                                        <div className="w-full text-left">
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

                        {/* Global Copyright */}
                        <footer
                            className="mt-6 w-full text-center"
                            style={{
                                fontFamily: FONT_FAMILY,
                            }}
                        >
                            <p className="text-sm font-bold text-white/70">
                                © 2026 Yoga
                                <span className="text-[#DB202C]">FX</span>
                            </p>
                        </footer>
                    </div>
                </div>
            </div>

            {showWelcomeOverlay ? (
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="masterclass-welcome-title"
                    className="fixed inset-0 z-[9999] flex min-h-[100dvh] w-full items-center justify-center overflow-y-auto bg-black px-4 py-8 text-white sm:px-6"
                    style={{
                        fontFamily: FONT_FAMILY,
                    }}
                >
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-0"
                        style={{
                            backgroundImage:
                                "radial-gradient(circle at 50% 48%, rgba(16,185,129,0.10), transparent 22%), radial-gradient(circle at 50% 78%, rgba(219,32,44,0.08), transparent 28%)",
                        }}
                    />

                    <div className="relative z-10 mx-auto flex w-full max-w-2xl flex-col items-center text-center">
                        {isCountingDown ? (
                            <MasterclassLoadingCard
                                secondsRemaining={secondsRemaining}
                            />
                        ) : (
                            <>
                                <img
                                    src="https://yogafx.b-cdn.net/content/Logo%20YogAFX.png"
                                    alt="YogaFX"
                                    className="h-20 w-auto object-contain sm:h-24"
                                />

                                <h1
                                    id="masterclass-welcome-title"
                                    className="mt-12 max-w-xl text-3xl font-bold leading-tight text-white sm:text-4xl"
                                >
                                    Congratulations!
                                </h1>

                                <div className="mt-10 flex h-32 w-32 shrink-0 items-center justify-center rounded-full bg-emerald-500 shadow-[0_0_45px_rgba(16,185,129,0.35)]">
                                    <Check
                                        className="h-20 w-20 text-white"
                                        strokeWidth={4.5}
                                        aria-hidden="true"
                                    />
                                </div>

                                <h2 className="mx-auto mt-10 max-w-2xl text-2xl font-bold leading-tight text-white sm:text-3xl">
                                    We Are Thrilled That You Are Joining Mr.
                                    Ian&apos;s {title} Practical MasterClass In
                                    Beautiful Bali
                                </h2>

                                <p className="mx-auto mt-6 max-w-xl text-base font-semibold italic leading-relaxed text-white sm:text-lg">
                                    Your Enrollment Starts Now
                                </p>

                                <button
                                    type="button"
                                    onClick={startFinalCountdown}
                                    className="mt-10 inline-flex min-h-[64px] w-full max-w-[300px] items-center justify-center rounded-[8px] bg-[#DB202C] px-8 py-5 text-xl font-bold italic text-white shadow-[0_12px_35px_rgba(219,32,44,0.3)] transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#c01a25] focus:outline-none focus:ring-4 focus:ring-[#DB202C]/35 sm:text-2xl"
                                    style={{
                                        fontFamily: FONT_FAMILY,
                                    }}
                                >
                                    Let&apos;s Get Started
                                </button>
                            </>
                        )}
                    </div>
                </div>
            ) : null}
        </>
    );
}
