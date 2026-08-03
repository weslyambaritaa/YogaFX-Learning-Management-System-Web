import { Button } from "@/Components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/Components/ui/dialog";

const STUDENT_LOGO_URL = "https://yogafx.b-cdn.net/content/Logo%20YogAFX.png";

export default function WelcomeToYogaFXDialog({
    open,
    onOpenChange,
    appDownload,
}) {
    const showQr = Boolean(appDownload?.has_any_link);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                showCloseButton={false}
                overlayClassName="bg-black/70 backdrop-blur-sm"
                className="border border-white/10 bg-[#141110] p-0 text-white sm:max-w-md"
            >
                <div className="p-8 sm:p-10">
                    <DialogHeader className="items-center space-y-5 text-center">
                        <img
                            src={STUDENT_LOGO_URL}
                            alt="YogaFX"
                            className="h-16 w-auto object-contain sm:h-20"
                        />
                        <div className="space-y-2">
                            <DialogTitle className="text-2xl font-semibold text-white sm:text-3xl">
                                Welcome to Yoga
                                <span className="text-[#c00000]">FX</span>
                            </DialogTitle>
                        </div>
                    </DialogHeader>

                    {showQr ? (
                        <div className="mt-6 space-y-3 text-center">
                            <div className="mx-auto flex w-fit max-w-full items-center justify-center overflow-hidden rounded-[18px] border border-white/10 bg-white p-3 shadow-[0_18px_50px_rgba(0,0,0,0.28)]">
                                {appDownload?.qr_image_url ? (
                                    <img
                                        src={appDownload.qr_image_url}
                                        alt="YogaFX mobile app QR code"
                                        className="h-40 w-40 object-contain sm:h-44 sm:w-44"
                                    />
                                ) : (
                                    <div className="flex h-40 w-40 items-center justify-center rounded-xl border border-dashed border-slate-300 text-center text-sm text-slate-500 sm:h-44 sm:w-44">
                                        QR code is not available yet.
                                    </div>
                                )}
                            </div>
                            <p className="text-sm text-white/60">
                                Get app on your mobile!
                            </p>
                        </div>
                    ) : null}
                </div>
                <DialogFooter className="border-t border-white/10 bg-black/20 px-6 pb-6 pt-4 sm:justify-center">
                    <Button
                        type="button"
                        className="w-full sm:w-auto"
                        onClick={() => onOpenChange(false)}
                    >
                        Continue
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
