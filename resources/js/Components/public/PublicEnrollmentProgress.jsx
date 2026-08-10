import { Check } from "lucide-react";

const STEPS = [
    {
        number: 1,
        label: "Deposit",
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

export default function PublicEnrollmentProgress({ currentStep = 1 }) {
    const normalizedCurrentStep = Math.min(
        Math.max(Number(currentStep) || 1, 1),
        STEPS.length,
    );

    return (
        <nav aria-label="Enrollment progress" className="w-full">
            <ol className="mx-auto grid w-full max-w-2xl grid-cols-3">
                {STEPS.map((step, index) => {
                    const isCompleted = step.number < normalizedCurrentStep;

                    const isCurrent = step.number === normalizedCurrentStep;

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
                                        "transition-colors duration-300",
                                        "sm:left-[calc(50%+24px)]",
                                        "sm:top-[23px]",
                                        "sm:w-[calc(100%-48px)]",
                                        isCompleted
                                            ? "border-emerald-500 bg-emerald-500 text-white shadow-[0_0_24px_rgba(16,185,129,0.28)]"
                                            : isCurrent
                                              ? "border-[#DB202C] bg-[#DB202C] text-white shadow-[0_0_24px_rgba(219,32,44,0.35)]"
                                              : "border-[#DB202C] bg-[#DB202C] text-white",
                                    ].join(" ")}
                                />
                            ) : null}

                            <div
                                aria-current={isCurrent ? "step" : undefined}
                                className={[
                                    "relative z-10",
                                    "flex h-11 w-11 items-center justify-center",
                                    "rounded-full border-2",
                                    "text-sm font-bold",
                                    "transition-all duration-300",
                                    "sm:h-12 sm:w-12 sm:text-base",

                                    isCompleted
                                        ? "border-emerald-500 bg-emerald-500 text-white shadow-[0_0_24px_rgba(16,185,129,0.28)]"
                                        : isCurrent
                                          ? "border-[#DB202C] bg-[#DB202C] text-white shadow-[0_0_24px_rgba(219,32,44,0.35)]"
                                          : "border-[rgba(219,32,44,0.55)] bg-[rgba(219,32,44,0.15)] text-white",
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

                            <span className="mt-3 whitespace-nowrap px-1 text-[11px] font-bold leading-4 text-white sm:text-sm sm:leading-5">
                                {step.label}
                            </span>
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
