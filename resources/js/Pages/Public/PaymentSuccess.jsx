import { Button } from "@/Components/ui/button";
import YogaFXText from "@/Components/YogaFXText";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";
import { Head, Link } from "@inertiajs/react";
import { Check, LoaderCircle } from "lucide-react";
import { useEffect, useState } from "react";

const FONT_FAMILY = "'Montserrat', sans-serif";

const COUNTDOWN_SECONDS = 5;

const YOGAFX_LOGO_URL = "https://yogafx.b-cdn.net/content/Logo%20YogAFX.png";

function PaymentSuccessLoadingOverlay({ secondsRemaining }) {
    return (
        <div
            className="
                fixed
                inset-0
                z-[9999]
                flex
                min-h-[100dvh]
                w-full
                items-center
                justify-center
                overflow-y-auto
                bg-black
                px-4
                py-8
                text-white
                sm:px-6
            "
            style={{
                fontFamily: FONT_FAMILY,
            }}
        >
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0"
                style={{
                    backgroundImage:
                        "radial-gradient(circle at 50% 42%, rgba(219,32,44,0.08), transparent 24%), radial-gradient(circle at 50% 76%, rgba(255,255,255,0.025), transparent 30%)",
                }}
            />

            <div
                className="
                    relative
                    z-10
                    mx-auto
                    flex
                    min-h-[450px]
                    w-full
                    max-w-lg
                    flex-col
                    items-center
                    justify-center
                    rounded-[18px]
                    border
                    border-white/15
                    bg-[#111111]
                    px-6
                    py-10
                    text-center
                    shadow-[0_24px_80px_rgba(0,0,0,0.55)]
                    sm:px-8
                    sm:py-12
                "
                role="status"
                aria-live="polite"
            >
                <img
                    src={YOGAFX_LOGO_URL}
                    alt="YogaFX"
                    className="
                        h-16
                        w-auto
                        object-contain
                        sm:h-20
                    "
                />

                <div className="relative mx-auto mt-10 h-24 w-24">
                    <LoaderCircle
                        className="
                            absolute
                            inset-0
                            h-24
                            w-24
                            animate-spin
                            text-[#DB202C]
                            motion-reduce:animate-none
                        "
                        strokeWidth={2.4}
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

                <p className="mt-6 text-base font-semibold text-white">
                    Loading Payment...
                </p>
            </div>
        </div>
    );
}

export default function PaymentSuccess({ onboarding, student = null }) {
    const [secondsRemaining, setSecondsRemaining] = useState(COUNTDOWN_SECONDS);

    const studentName = String(
        student?.name ?? onboarding?.student?.name ?? "",
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

    if (isLoading) {
        return (
            <>
                <Head title={onboarding?.title ?? "Payment Success"} />

                <PaymentSuccessLoadingOverlay
                    secondsRemaining={secondsRemaining}
                />
            </>
        );
    }

    return (
        <PublicFlowLayout
            title={onboarding?.title ?? "Payment Success"}
            heading={null}
            description={null}
            eyebrow={null}
        >
            <div
                className="flex justify-center pb-8 pt-2"
                style={{
                    fontFamily: FONT_FAMILY,
                }}
            >
                <div className="w-full max-w-2xl text-center">
                    <div
                        className="
                            mx-auto
                            flex
                            h-32
                            w-32
                            items-center
                            justify-center
                            rounded-full
                            bg-emerald-500
                            shadow-[0_0_45px_rgba(16,185,129,0.35)]
                        "
                    >
                        <Check
                            className="h-20 w-20 text-white"
                            strokeWidth={4.5}
                            aria-hidden="true"
                        />
                    </div>

                    <div className="mt-8">
                        <h1 className="text-4xl font-bold leading-tight text-white sm:text-5xl">
                            Congratulations
                        </h1>

                        {studentName ? (
                            <p className="mt-2 text-3xl font-bold leading-tight text-white sm:text-4xl">
                                {studentName}
                            </p>
                        ) : null}
                    </div>

                    <h2 className="mt-8 text-2xl font-bold leading-tight text-white sm:text-3xl">
                        <YogaFXText
                            text={onboarding?.eyebrow ?? "Payment Approved"}
                            fxClassName="!text-[#DB202C]"
                        />
                    </h2>

                    <p className="mx-auto mt-6 max-w-xl text-base font-semibold leading-7 text-white sm:text-lg">
                        Please Continue To Your Enrollment Application Form To
                        Complete Your Details And Access Your Dashboard.
                    </p>

                    <Button
                        asChild
                        className="
                            mt-9
                            min-h-[64px]
                            w-full
                            max-w-[360px]
                            rounded-[8px]
                            bg-[#DB202C]
                            px-8
                            py-5
                            text-base
                            font-bold
                            italic
                            text-white
                            shadow-[0_12px_35px_rgba(219,32,44,0.3)]
                            transition-all
                            duration-200
                            hover:-translate-y-0.5
                            hover:bg-[#c01a25]
                            focus:outline-none
                            focus:ring-4
                            focus:ring-[#DB202C]/35
                            sm:text-xl
                        "
                    >
                        <Link
                            href={onboarding.continue_url}
                            className="
                                inline-flex
                                items-center
                                justify-center
                                whitespace-nowrap
                            "
                        >
                            Continue To Enrollment
                        </Link>
                    </Button>
                </div>
            </div>
        </PublicFlowLayout>
    );
}
