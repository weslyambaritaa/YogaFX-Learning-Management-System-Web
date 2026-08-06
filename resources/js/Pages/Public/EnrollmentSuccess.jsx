import { Button } from "@/Components/ui/button";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";
import { Link } from "@inertiajs/react";
import { Check, LoaderCircle } from "lucide-react";
import { useEffect, useState } from "react";

const FONT_FAMILY = "'Montserrat', sans-serif";
const COUNTDOWN_SECONDS = 5;

export default function EnrollmentSuccess({ onboarding }) {
    const [secondsRemaining, setSecondsRemaining] = useState(COUNTDOWN_SECONDS);

    const packageTitle =
        onboarding?.package?.title ??
        onboarding?.access_tier?.name ??
        "Your Package";

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
            title="Enrollment Success"
            heading={null}
            description={null}
        >
            <div
                className="flex justify-center py-6"
                style={{ fontFamily: FONT_FAMILY }}
            >
                <div className="w-full max-w-lg">
                    {isLoading ? (
                        <div
                            className="rounded-[18px] border border-white/15 bg-[#111111] px-6 py-10 text-center shadow-[0_24px_80px_rgba(0,0,0,0.45)] sm:px-8"
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
                        <div className="px-4 py-8 text-center sm:px-8">
                            <p className="mx-auto mb-6 max-w-xl text-xl font-bold leading-tight text-white sm:text-2xl">
                                Congratulations
                                {studentName ? `, ${studentName}` : ""}
                            </p>

                            <div className="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-emerald-500">
                                <Check
                                    className="h-14 w-14 text-white"
                                    strokeWidth={3.2}
                                    aria-hidden="true"
                                />
                            </div>

                            <h2 className="mx-auto mt-6 max-w-xl text-2xl font-bold leading-tight text-white sm:text-3xl">
                                Congratulations on Your Enrollment Application
                                Success
                            </h2>

                            <p className="mx-auto mt-8 max-w-xl text-base font-medium leading-7 text-white">
                                To Access {packageTitle}, Pre-course Preparation
                            </p>

                            <div className="mt-4 flex justify-center px-4">
                                <Button
                                    asChild
                                    className="h-auto max-w-full rounded-[5px] bg-[#DB202C] px-6 py-3.5 text-center text-sm font-bold leading-5 text-white hover:bg-[#c31c28]"
                                >
                                    <Link
                                        href={onboarding.continue_url}
                                        className="inline-flex items-center justify-center text-center !whitespace-normal"
                                    >
                                        Click Here
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </PublicFlowLayout>
    );
}
