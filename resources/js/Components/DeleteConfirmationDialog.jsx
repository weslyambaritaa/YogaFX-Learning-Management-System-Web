import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/Components/ui/dialog';
import { router } from '@inertiajs/react';
import { useState } from 'react';

export default function DeleteConfirmationDialog({
    href = null,
    onConfirm = null,
    title,
    description,
    triggerLabel = 'Delete',
    trigger = null,
    triggerClassName = 'text-sm font-medium text-rose-600 hover:text-rose-800',
    triggerDisabled = false,
    confirmLabel = 'Yes, Delete',
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
            <DialogContent
                className="max-w-md border border-slate-200 bg-white p-0 text-slate-900 shadow-2xl ring-1 ring-black/5"
                overlayClassName="fixed inset-0 z-50 bg-slate-950/55 backdrop-blur-sm data-open:animate-in data-open:fade-in-0 data-closed:animate-out data-closed:fade-out-0"
            >
                <DialogHeader className="gap-3 px-6 py-5">
                    <DialogTitle className="text-lg font-semibold text-slate-950">
                        {title}
                    </DialogTitle>
                    <DialogDescription className="text-sm leading-6 text-slate-600">
                        {description}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter className="rounded-b-xl border-slate-200 bg-slate-50/95 px-6 py-4">
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
                        {processing ? 'Deleting...' : confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
