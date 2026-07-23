import { Check, X } from "lucide-react";

const FONT_FAMILY = "'Montserrat', sans-serif";

const SPECIAL_CHARACTER_PATTERN = /[!@#$%^&*(),.?":{}|<>_\-\\/\[\]`~+=';]/;

function buildRules(password) {
    const value = String(password ?? "");

    return [
        {
            key: "length",
            label: "At least 8 characters",
            passed: value.length >= 8,
        },
        {
            key: "uppercase",
            label: "Uppercase letter (A-Z)",
            passed: /[A-Z]/.test(value),
        },
        {
            key: "lowercase",
            label: "Lowercase letter (a-z)",
            passed: /[a-z]/.test(value),
        },
        {
            key: "number",
            label: "Number (0-9)",
            passed: /\d/.test(value),
        },
        {
            key: "symbol",
            label: "Special character (!@#$%^&*)",
            passed: SPECIAL_CHARACTER_PATTERN.test(value),
        },
    ];
}

function strengthForRules(rules) {
    const passedCount = rules.filter((rule) => rule.passed).length;

    if (passedCount <= 1) {
        return {
            label: "Weak",
            width: "20%",
            barClassName: "bg-[#DB202C]",
            textClassName: "text-[#DB202C]",
        };
    }

    if (passedCount <= 3) {
        return {
            label: "Medium",
            width: "60%",
            barClassName: "bg-amber-500",
            textClassName: "text-amber-600",
        };
    }

    return {
        label: "Strong",
        width: "100%",
        barClassName: "bg-emerald-500",
        textClassName: "text-emerald-600",
    };
}

export default function PasswordRequirementsCard({ password = "" }) {
    const rules = buildRules(password);
    const strength = strengthForRules(rules);

    return (
        <div
            className="rounded-[14px] bg-[#f2f2f2] p-6 text-black"
            style={{ fontFamily: FONT_FAMILY }}
        >
            <h3 className="text-[18px] font-bold text-black">
                Password Requirements:
            </h3>

            <div className="mt-5 space-y-2.5">
                {rules.map((rule) => (
                    <div
                        key={rule.key}
                        className="flex items-center gap-3 text-[16px] font-medium text-black"
                    >
                        <span
                            className={[
                                "flex size-6 items-center justify-center rounded-sm",
                                rule.passed
                                    ? "bg-emerald-500 text-white"
                                    : "text-[#DB202C]",
                            ].join(" ")}
                        >
                            {rule.passed ? (
                                <Check className="size-4" strokeWidth={3} />
                            ) : (
                                <X className="size-5" strokeWidth={3} />
                            )}
                        </span>
                        <span>{rule.label}</span>
                    </div>
                ))}
            </div>

            <div className="mt-6 flex items-center gap-2 text-[18px] font-bold text-black">
                <span>Strength:</span>
                <span className={strength.textClassName}>{strength.label}</span>
            </div>

            <div className="mt-3 h-5 overflow-hidden rounded-full bg-white">
                <div
                    className={`h-full transition-all duration-200 ${strength.barClassName}`}
                    style={{ width: strength.width }}
                />
            </div>
        </div>
    );
}
