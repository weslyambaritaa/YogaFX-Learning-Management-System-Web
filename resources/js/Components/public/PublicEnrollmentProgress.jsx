import { Check } from "lucide-react";

const STEPS = [
    {
        number: 1,
        label: "Payment",
    },
    {
        number: 2,
        label: "Enrollment Form",
    },
    {
        number: 3,
        label: "Dashboard Sign In",
    },
];

export default function PublicEnrollmentProgress({
    currentStep = 1,
}) {
    const normalizedCurrentStep = Math.min(
        Math.max(Number(currentStep) || 1, 1),
        STEPS.length,
    );

    return (
        <nav
            aria-label="Enrollment progress"
            className="w-full"
        >
            <ol className="mx-auto grid w-full max-w-2xl grid-cols-3">
                {STEPS.map((step, index) => {
                    const isCompleted =
                        step.number < normalizedCurrentStep;

                    const isCurrent =
                        step.number === normalizedCurrentStep;

                    return (
                        <li
                            key={step.number}
                            className="relative flex min-w-0 flex-col items-center text-center"
                        >
                            {index < STEPS.length - 1 ? (
                                <span
                                    aria-hidden="true"
                                    className={[
                                        "absolute",
                                        "left-[calc(50%+22px)]",
                                        "top-[21px]",
                                        "h-[3px]",
                                        "w-[calc(100%-44px)]",
                                        "rounded-full",
                                        "sm:left-[calc(50%+24px)]",
                                        "sm:top-[23px]",
                                        "sm:w-[calc(100%-48px)]",
                                        step.number <
                                        normalizedCurrentStep
                                            ? "bg-emerald-500"
                                            : "bg-white/20",
                                    ].join(" ")}
                                />
                            ) : null}

                            <div
                                aria-current={
                                    isCurrent ? "step" : undefined
                                }
                                className={[
                                    "relative z-10",
                                    "flex h-11 w-11 items-center justify-center",
                                    "rounded-full border-2",
                                    "text-sm font-bold",
                                    "transition-all duration-200",
                                    "sm:h-12 sm:w-12 sm:text-base",
                                    isCompleted
                                        ? "border-emerald-500 bg-emerald-500 text-white shadow-[0_0_24px_rgba(16,185,129,0.28)]"
                                        : isCurrent
                                          ? "border-[#DB202C] bg-[#DB202C] text-white shadow-[0_0_24px_rgba(219,32,44,0.35)]"
                                          : "border-white/35 bg-black text-white/55",
                                ].join(" ")}
                            >
                                {isCompleted ? (
                                    <Check
                                        className="h-6 w-6"
                                        strokeWidth={3}
                                        aria-hidden="true"
                                    />
                                ) : (
                                    step.number
                                )}
                            </div>

                            <span
                                className={[
                                    "mt-3 px-1",
                                    "text-[11px] font-semibold leading-4",
                                    "sm:text-sm sm:leading-5",
                                    isCompleted
                                        ? "text-emerald-400"
                                        : isCurrent
                                          ? "text-[#ff6b75]"
                                          : "text-white/45",
                                ].join(" ")}
                            >
                                {step.label}
                            </span>
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}