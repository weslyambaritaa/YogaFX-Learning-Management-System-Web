import { useEffect, useState } from "react";

const toneClasses = {
    success: "border-emerald-200 bg-emerald-50 text-emerald-900",
    error: "border-rose-200 bg-rose-50 text-rose-900",
};

export default function TransientStatusBanner({
    message,
    tone = "success",
    noticeKey = null,
    autoHideMs = 3000,
    dismissOnInteract = true,
    className = "",
    onDismiss = null,
}) {
    const [visible, setVisible] = useState(Boolean(message));

    useEffect(() => {
        setVisible(Boolean(message));
    }, [message, tone, noticeKey]);

    useEffect(() => {
        if (!message || !visible) {
            return undefined;
        }

        const timeoutId = window.setTimeout(() => {
            setVisible(false);
            onDismiss?.();
        }, autoHideMs);

        return () => window.clearTimeout(timeoutId);
    }, [autoHideMs, message, onDismiss, visible]);

    useEffect(() => {
        if (!dismissOnInteract || !message || !visible) {
            return undefined;
        }

        const handlePointerDown = () => {
            setVisible(false);
            onDismiss?.();
        };

        window.addEventListener("pointerdown", handlePointerDown, {
            passive: true,
        });

        return () => {
            window.removeEventListener("pointerdown", handlePointerDown);
        };
    }, [dismissOnInteract, message, onDismiss, visible]);

    if (!message || !visible) {
        return null;
    }

    return (
        <div
            role={tone === "error" ? "alert" : "status"}
            aria-live={tone === "error" ? "assertive" : "polite"}
            className={[
                "rounded-[5px] border px-4 py-3 text-sm",
                toneClasses[tone] ?? toneClasses.success,
                className,
            ].join(" ")}
        >
            {message}
        </div>
    );
}
