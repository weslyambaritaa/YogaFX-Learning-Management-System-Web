import { Button } from "@/Components/ui/button";
import YogaFXText from "@/Components/YogaFXText";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";
import { Link } from "@inertiajs/react";
import { Check, LoaderCircle } from "lucide-react";
import { useEffect, useState } from "react";

const FONT_FAMILY = "'Montserrat', sans-serif";
const COUNTDOWN_SECONDS = 5;

function PaymentSuccessLoadingOverlay({ secondsRemaining }) {
    return (
        <div
            className="fixed inset-0 z-[9999] flex min-h-[100dvh] w-full items-center justify-center overflow-y-auto bg-black px-4 py-8 text-white sm:px-6"
            style={{ fontFamily: FONT_FAMILY }}
            role="status"
            aria-live="polite"
            aria-label={`Payment success page will open in ${secondsRemaining} seconds`}
        >
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0"
                style={{
                    backgroundImage:
                        "radial-gradient(circle at 50% 45%, rgba(219,32,44,0.16), transparent 34%), radial-gradient(circle at 85% 90%, rgba(219,32,44,0.10), transparent 28%)",
                }}
            />

            <div className="relative z-10 flex min-h-[410px] w-full max-w-lg flex-col items-center justify-center rounded-[18px] border border-white/15 bg-[#111111] px-6 py-10 text-center shadow-[0_24px_80px_rgba(0,0,0,0.45)] sm:min-h-[450px] sm:px-10">
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
        </div>
    );
}

export default function PaymentSuccess({ onboarding, student = null }) {
    const [secondsRemaining, setSecondsRemaining] = useState(COUNTDOWN_SECONDS);

    /*
     * Nama siswa:
     * 1. Gunakan student.name bila tersedia.
     * 2. Jika tidak tersedia, gunakan onboarding.student.name.
     * 3. Jika tidak tersedia, gabungkan first_name dan last_name.
     */
    const fullNameFromFields = [
        student?.first_name ?? onboarding?.student?.first_name,
        student?.last_name ?? onboarding?.student?.last_name,
    ]
        .filter(Boolean)
        .join(" ")
        .trim();

    const studentName = String(
        student?.name ?? onboarding?.student?.name ?? fullNameFromFields,
    ).trim();

    const isLoading = secondsRemaining > 0;

    useEffect(() => {
        if (!isLoading) {
            return undefined;
        }

        const timeoutId = window.setTimeout(() => {
            setSecondsRemaining((current) => Math.max(current - 1, 0));
        }, 1000);

        return () => {
            window.clearTimeout(timeoutId);
        };
    }, [isLoading, secondsRemaining]);

    useEffect(() => {
        if (!isLoading) {
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
    }, [isLoading]);

    return (
        <>
            <PublicFlowLayout
                title={onboarding.title ?? "Payment Success"}
                eyebrow={isLoading ? null : "\u00A0"}
                heading={
                    isLoading ? null : (
                        <>
                            <span className="block text-4xl font-bold leading-tight text-white sm:text-5xl">
                                Congratulations
                            </span>

                            {studentName ? (
                                <span className="mt-3 block text-3xl font-bold leading-tight text-white sm:text-4xl">
                                    {studentName}
                                </span>
                            ) : null}
                        </>
                    )
                }
            >
                {!isLoading ? (
                    <div
                        className="flex justify-center pb-8 pt-2"
                        style={{
                            fontFamily: FONT_FAMILY,
                        }}
                    >
                        <div className="w-full max-w-2xl text-center">
                            <div className="mx-auto flex h-32 w-32 items-center justify-center rounded-full bg-emerald-500 shadow-[0_0_45px_rgba(16,185,129,0.35)]">
                                <Check
                                    className="h-20 w-20 text-white"
                                    strokeWidth={3}
                                    aria-hidden="true"
                                />
                            </div>

                            <h2 className="mt-10 text-3xl font-bold leading-tight text-white md:text-4xl">
                                {onboarding.eyebrow ?? "Transfer Received"}
                            </h2>

                            <p className="mx-auto mt-6 max-w-xl text-lg font-semibold leading-relaxed text-white">
                                <YogaFXText
                                    text={
                                        onboarding.message ??
                                        "Please Continue To Your Enrollment Application Form To Complete Your Details And Access Your Dashboard."
                                    }
                                    fxClassName="!text-[#DB202C]"
                                />
                            </p>

                            <Button
                                asChild
                                className="mt-10 inline-flex min-h-[64px] w-auto max-w-full items-center justify-center rounded-[8px] bg-[#DB202C] px-8 py-5 text-lg font-bold italic text-white shadow-[0_12px_35px_rgba(219,32,44,0.3)] transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#c01a25] focus:outline-none focus:ring-4 focus:ring-[#DB202C]/35 sm:px-10 sm:text-xl"
                            >
                                <Link
                                    href={onboarding.continue_url}
                                    className="inline-flex items-center justify-center whitespace-nowrap"
                                >
                                    Continue to Enrollment
                                </Link>
                            </Button>
                        </div>
                    </div>
                ) : null}
            </PublicFlowLayout>

            {isLoading ? (
                <PaymentSuccessLoadingOverlay
                    secondsRemaining={secondsRemaining}
                />
            ) : null}
        </>
    );
}
