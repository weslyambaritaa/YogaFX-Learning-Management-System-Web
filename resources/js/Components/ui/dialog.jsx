"use client";

import * as React from "react";
import { Dialog as DialogPrimitive } from "radix-ui";

import { cn } from "@/lib/utils";
import { Button } from "@/Components/ui/button";
import { XIcon } from "lucide-react";

function Dialog({ ...props }) {
    return <DialogPrimitive.Root data-slot="dialog" {...props} />;
}

function DialogTrigger({ ...props }) {
    return <DialogPrimitive.Trigger data-slot="dialog-trigger" {...props} />;
}

function DialogPortal({ ...props }) {
    return <DialogPrimitive.Portal data-slot="dialog-portal" {...props} />;
}

function DialogClose({ ...props }) {
    return <DialogPrimitive.Close data-slot="dialog-close" {...props} />;
}

function DialogOverlay({ className, ...props }) {
    return (
        <DialogPrimitive.Overlay
            data-slot="dialog-overlay"
            className={cn(
                "fixed inset-0 isolate z-50 bg-slate-950/55 backdrop-blur-sm duration-150 data-open:animate-in data-open:fade-in-0 data-closed:animate-out data-closed:fade-out-0",
                className,
            )}
            {...props}
        />
    );
}

function DialogContent({
    className,
    children,
    showCloseButton = true,
    overlayClassName,
    ...props
}) {
    return (
        <DialogPortal>
            <DialogOverlay className={overlayClassName} />
            <DialogPrimitive.Content
                data-slot="dialog-content"
                className={cn(
                    "fixed top-1/2 left-1/2 z-50 grid w-full max-w-[calc(100%-2rem)] -translate-x-1/2 -translate-y-1/2 gap-0 rounded-xl border border-slate-200 bg-white p-0 text-slate-900 shadow-2xl ring-1 ring-black/5 duration-150 outline-none sm:max-w-md data-open:animate-in data-open:fade-in-0 data-open:zoom-in-95 data-closed:animate-out data-closed:fade-out-0 data-closed:zoom-out-95",
                    className,
                )}
                {...props}
            >
                {children}
                {showCloseButton && (
                    <DialogPrimitive.Close data-slot="dialog-close" asChild>
                        <Button
                            variant="ghost"
                            className="absolute top-3 right-3 h-8 w-8 rounded-lg p-0 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                            size="icon-sm"
                        >
                            <XIcon className="h-4 w-4" />
                            <span className="sr-only">Close</span>
                        </Button>
                    </DialogPrimitive.Close>
                )}
            </DialogPrimitive.Content>
        </DialogPortal>
    );
}

function DialogHeader({ className, ...props }) {
    return (
        <div
            data-slot="dialog-header"
            className={cn("flex flex-col gap-2 px-6 py-5", className)}
            {...props}
        />
    );
}

function DialogFooter({
    className,
    showCloseButton = false,
    children,
    ...props
}) {
    return (
        <div
            data-slot="dialog-footer"
            className={cn(
                "flex flex-col-reverse gap-2 rounded-b-xl border-t border-slate-200 bg-slate-50/95 px-6 py-4 sm:flex-row sm:justify-end",
                className,
            )}
            {...props}
        >
            {children}
            {showCloseButton && (
                <DialogPrimitive.Close asChild>
                    <Button
                        variant="outline"
                        className="border-slate-300 bg-white text-slate-700 hover:bg-slate-100"
                    >
                        Close
                    </Button>
                </DialogPrimitive.Close>
            )}
        </div>
    );
}

function DialogTitle({ className, ...props }) {
    return (
        <DialogPrimitive.Title
            data-slot="dialog-title"
            className={cn(
                "text-base font-semibold leading-none text-slate-950",
                className,
            )}
            {...props}
        />
    );
}

function DialogDescription({ className, ...props }) {
    return (
        <DialogPrimitive.Description
            data-slot="dialog-description"
            className={cn("text-sm leading-6 text-slate-600", className)}
            {...props}
        />
    );
}

export {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogOverlay,
    DialogPortal,
    DialogTitle,
    DialogTrigger,
};
