import { Button } from "@/Components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/Components/ui/dialog";

const FONT_FAMILY = "'Montserrat', sans-serif";

export default function StudentAppDownloadDialog({
    open,
    onOpenChange,
    qrImageUrl,
    title = "Get it on your mobile!",
    description = "Scan this QR code from your phone to continue your YogaFX experience on mobile.",
    maxWidthClassName = "sm:max-w-md",
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                showCloseButton={false}
                overlayClassName="bg-black/70 backdrop-blur-sm"
                className={`border border-white/10 bg-[#141110] p-0 text-white ${maxWidthClassName}`}
                style={{ fontFamily: FONT_FAMILY }}
            >
                <div className="p-6 sm:p-8">
                    <DialogHeader className="items-center space-y-4 text-center">
                        <div className="overflow-hidden rounded-[18px] border border-white/10 bg-white p-3 shadow-[0_18px_50px_rgba(0,0,0,0.28)]">
                            {qrImageUrl ? (
                                <img
                                    src={qrImageUrl}
                                    alt="YogaFX mobile app QR code"
                                    className="h-52 w-52 object-contain sm:h-60 sm:w-60"
                                />
                            ) : (
                                <div className="flex h-52 w-52 items-center justify-center rounded-[12px] border border-dashed border-slate-300 text-center text-sm text-slate-500 sm:h-60 sm:w-60">
                                    QR code is not available yet.
                                </div>
                            )}
                        </div>
                        <div className="space-y-2">
                            <DialogTitle className="text-2xl font-semibold text-white sm:text-3xl">
                                {title}
                            </DialogTitle>
                            <DialogDescription className="max-w-md text-sm leading-7 text-white/65">
                                {description}
                            </DialogDescription>
                        </div>
                    </DialogHeader>
                </div>
                <DialogFooter className="border-white/10 bg-black/20 sm:justify-center">
                    <Button
                        type="button"
                        variant="outline"
                        className="border-white/15 bg-white/5 text-white hover:bg-white/10 hover:text-white"
                        onClick={() => onOpenChange(false)}
                    >
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
