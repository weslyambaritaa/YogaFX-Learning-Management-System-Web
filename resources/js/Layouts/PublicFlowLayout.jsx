import StudentBackButton from "@/Components/student/StudentBackButton";
import { Head, Link } from "@inertiajs/react";
import { useEffect, useRef, useState } from "react";

const FONT_FAMILY = "'Montserrat', sans-serif";

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
}) {
    const [showWelcomeOverlay, setShowWelcomeOverlay] = useState(
        () => showMasterclassWelcome && isMasterclassLandingPath(),
    );
    const [isWelcomeOverlayClosing, setIsWelcomeOverlayClosing] =
        useState(false);

    const welcomeOverlayTimerRef = useRef(null);

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
        return () => {
            if (welcomeOverlayTimerRef.current) {
                window.clearTimeout(welcomeOverlayTimerRef.current);
            }
        };
    }, []);

    const closeWelcomeOverlay = () => {
        if (isWelcomeOverlayClosing) {
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
                            className={
                                largeLogo
                                    ? "mb-10 flex items-center justify-center"
                                    : "mb-8 flex items-center justify-center"
                            }
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
                        "bg-black px-5 py-8 text-white",
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
                                "radial-gradient(circle at 50% 45%, rgba(219,32,44,0.18), transparent 34%), radial-gradient(circle at 85% 90%, rgba(219,32,44,0.12), transparent 28%)",
                        }}
                    />

                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-[#DB202C]/70 to-transparent"
                    />

                    <div
                        className={[
                            "relative z-10 mx-auto w-full max-w-4xl text-center",
                            "transition-all duration-500",
                            isWelcomeOverlayClosing
                                ? "-translate-y-14 opacity-0"
                                : "translate-y-0 opacity-100",
                        ].join(" ")}
                    >
                        <img
                            src="https://yogafx.b-cdn.net/content/Logo%20YogAFX.png"
                            alt="YogaFX"
                            className="mx-auto h-24 w-auto object-contain sm:h-28"
                        />

                        <h1
                            id="masterclass-welcome-title"
                            className="mt-8 text-4xl font-bold leading-tight tracking-[-0.04em] text-white sm:text-5xl md:text-6xl"
                        >
                            Congratulations!
                        </h1>

                        <p className="mx-auto mt-7 max-w-3xl text-xl font-semibold leading-relaxed text-white sm:text-2xl md:text-3xl">
                            We Are Thrilled That You Are Joining Mr. Ian&apos;s
                            Bikram Hot Yoga 26&amp;2 Yoga Teacher Training
                        </p>

                        <p className="mt-6 text-lg font-medium italic text-white/75 sm:text-xl">
                            Your Enrollment Starts Now
                        </p>

                        <button
                            type="button"
                            onClick={closeWelcomeOverlay}
                            disabled={isWelcomeOverlayClosing}
                            className="mt-10 inline-flex min-h-[64px] min-w-[270px] items-center justify-center rounded-[8px] bg-[#DB202C] px-10 py-5 text-base font-bold text-white shadow-[0_18px_50px_rgba(219,32,44,0.34)] transition-all duration-200 hover:-translate-y-1 hover:bg-[#c51c27] hover:shadow-[0_22px_60px_rgba(219,32,44,0.44)] focus:outline-none focus:ring-4 focus:ring-[#DB202C]/35 disabled:cursor-wait disabled:opacity-80 sm:min-w-[330px] sm:text-lg"
                            style={{ fontFamily: FONT_FAMILY }}
                        >
                            Let&apos;s Get Started
                        </button>
                    </div>
                </div>
            ) : null}
        </>
    );
}
