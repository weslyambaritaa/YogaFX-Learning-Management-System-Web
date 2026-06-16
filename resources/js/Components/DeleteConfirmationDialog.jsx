import { Button } from "@/Components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/Components/ui/dialog";
import { router } from "@inertiajs/react";
import { useState } from "react";

export default function DeleteConfirmationDialog({
    href = null,
    onConfirm = null,
    title,
    description,
    triggerLabel = "Delete",
    trigger = null,
    triggerClassName = "text-sm font-medium text-rose-600 hover:text-rose-800",
    triggerDisabled = false,
    confirmLabel = "Yes, Delete",
}) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const handleConfirm = () => {
        setProcessing(true);

        const finish = () => {
            setProcessing(false);
            setOpen(false);
        };

        if (onConfirm) {
            onConfirm({ onFinish: finish });
            return;
        }

        router.delete(href, {
            onFinish: finish,
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {trigger ?? (
                    <button
                        type="button"
                        className={triggerClassName}
                        disabled={triggerDisabled}
                    >
                        {triggerLabel}
                    </button>
                )}
            </DialogTrigger>
            <DialogContent showCloseButton={false}>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setOpen(false)}
                        disabled={processing}
                        className="border-slate-300 bg-white text-slate-700 hover:bg-slate-100"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={handleConfirm}
                        disabled={processing}
                        className="bg-rose-600 text-white hover:bg-rose-700"
                    >
                        {processing ? "Deleting..." : confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
