import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import { Lock, X } from 'lucide-react';

function titleFor(kind) {
    return kind === 'lesson' ? 'Complete Previous Lesson' : 'Complete Previous Module';
}

function descriptionFor(kind) {
    return kind === 'lesson'
        ? 'Finish the previous lesson to continue.'
        : 'Finish the previous module to continue.';
}

export default function LockedContentDialog({
    open,
    onOpenChange,
    kind = 'module',
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                showCloseButton={false}
                className="max-w-[440px] overflow-hidden rounded-[22px] border border-white/10 bg-[#141110] p-0 text-white ring-white/10"
                overlayClassName="bg-black/65 backdrop-blur-sm"
            >
                <div className="p-6 sm:p-7">
                    <div className="relative">
                        <DialogClose asChild>
                            <button
                                type="button"
                                className="absolute right-4 top-4 inline-flex size-9 items-center justify-center rounded-full border border-white/10 bg-white/[0.04] text-white/55 transition hover:bg-white/[0.08] hover:text-white"
                            >
                                <span className="sr-only">Close dialog</span>
                                <X className="size-4" />
                            </button>
                        </DialogClose>
                    </div>

                    <DialogHeader className="space-y-4">
                        <div className="flex items-center gap-4">
                            <div className="flex size-12 shrink-0 items-center justify-center rounded-[16px] bg-[#DB202C] text-white">
                                <Lock className="size-5" />
                            </div>
                            <DialogTitle className="text-2xl font-semibold tracking-[-0.02em] text-white">
                                {titleFor(kind)}
                            </DialogTitle>
                        </div>

                        <DialogDescription className="pr-8 text-sm leading-7 text-white/70">
                            {descriptionFor(kind)}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="mt-6 flex justify-end">
                        <DialogClose asChild>
                            <Button
                                type="button"
                                className="rounded-[12px] bg-[#DB202C] px-5 text-white hover:bg-[#c31c28]"
                            >
                                Okay
                            </Button>
                        </DialogClose>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
