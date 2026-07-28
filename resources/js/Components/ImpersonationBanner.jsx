import { Button } from "@/Components/ui/button";
import { router, usePage } from "@inertiajs/react";

export default function ImpersonationBanner() {
    const { impersonation } = usePage().props;

    if (!impersonation?.active) {
        return null;
    }

    const handleBackToAdmin = () => {
        router.post(route("impersonation.stop"));
    };

    return (
        <div className="sticky top-0 z-50 flex flex-wrap items-center justify-center gap-2 bg-amber-500 px-4 py-2 text-sm font-medium text-amber-950">
            <span>
                Sedang login sebagai{" "}
                <strong>{impersonation.student_name ?? "student"}</strong> —
                mode tampilan read-only.
            </span>
            <Button
                type="button"
                size="sm"
                variant="outline"
                className="h-7 border-amber-950/30 bg-amber-50 px-3 text-xs text-amber-950 hover:bg-amber-100"
                onClick={handleBackToAdmin}
            >
                Kembali ke Admin
            </Button>
        </div>
    );
}
