import { Button } from "@/Components/ui/button";
import YogaFXText from "@/Components/YogaFXText";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";
import { Link } from "@inertiajs/react";
import { Check, LoaderCircle } from "lucide-react";
import { useEffect, useState } from "react";

const FONT_FAMILY = "'Montserrat', sans-serif";
const COUNTDOWN_SECONDS = 5;

export default function PaymentSuccess({ onboarding, student = null }) {
    const [secondsRemaining, setSecondsRemaining] = useState(COUNTDOWN_SECONDS);

    /*
     * Nama siswa dibuat sama persis seperti EnrollmentSuccess:
     * 1. Gunakan student.name bila tersedia.
     * 2. Jika tidak tersedia, gabungkan first_name dan last_name.
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

    return (
        <PublicFlowLayout
            title={onboarding.title ?? "Payment Success"}
            eyebrow={isLoading ? null : "\u00A0"}
            heading={
                isLoading
                    ? null
                    : studentName
                      ? `Congratulations, ${studentName}!`
                      : "Congratulations!"
            }
        >
            <div
                className="flex justify-center pb-8 pt-2"
                style={{ fontFamily: FONT_FAMILY }}
            >
                <div className="w-full max-w-2xl text-center">
                    {isLoading ? (
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
                                        key={secondsRemaining}
                                        className="text-3xl font-bold text-white"
                                    >
                                        {secondsRemaining}
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
                                {onboarding.eyebrow ?? "Payment Approved"}
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
                                className="mt-9 min-h-[64px] w-full max-w-[360px] rounded-[8px] bg-[#DB202C] px-10 py-6 text-base font-bold italic text-white shadow-[0_12px_35px_rgba(219,32,44,0.3)] transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#c01a25]"
                            >
                                <Link
                                    href={onboarding.continue_url}
                                    className="inline-flex items-center justify-center whitespace-nowrap"
                                >
                                    Continue to Enrollment
                                </Link>
                            </Button>
                        </>
                    )}
                </div>
            </div>
        </PublicFlowLayout>
    );
}
