import AppStoreBadges from "@/Components/public/AppStoreBadges";
import { Button } from "@/Components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from "@/Components/ui/dialog";

const FONT_FAMILY = "'Montserrat', sans-serif";

const STUDENT_LOGO_URL = "https://yogafx.b-cdn.net/content/Logo%20YogAFX.png";

export default function WelcomeToYogaFXDialog({
    open,
    onOpenChange,
    onContinueBrowser,
    appDownload,
    studentName = "Student",
    accessTierLabel = "Access Tier",
}) {
    const hasAppDownload = Boolean(appDownload?.has_any_link);
    const hasQrCode = Boolean(appDownload?.qr_image_url);

    const handleContinueBrowser = () => {
        if (onContinueBrowser) {
            onContinueBrowser();
            return;
        }

        onOpenChange(false);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                showCloseButton={false}
                overlayClassName="bg-black/70 backdrop-blur-sm"
                onInteractOutside={(event) => event.preventDefault()}
                onEscapeKeyDown={(event) => event.preventDefault()}
                className="
                    w-[calc(100%-20px)]
                    max-h-[calc(100dvh-20px)]
                    overflow-hidden
                    rounded-[14px]
                    border
                    border-white/15
                    bg-[#141110]
                    p-0
                    text-white
                    shadow-[0_24px_70px_rgba(0,0,0,0.55)]
                    sm:max-w-[720px]
                "
                style={{
                    fontFamily: FONT_FAMILY,
                }}
            >
                <div className="px-5 py-5 sm:px-8 sm:py-6">
                    <DialogHeader className="items-center text-center">
                        <img
                            src={STUDENT_LOGO_URL}
                            alt="YogaFX"
                            className="h-12 w-auto object-contain sm:h-14"
                        />

                        <DialogTitle className="mt-3 text-center text-[21px] font-bold leading-[1.25] tracking-[-0.02em] text-white sm:text-[27px]">
                            <span className="block">
                                Welcome {studentName} to Your
                            </span>

                            <span className="mt-1 block">
                                Yoga
                                <span className="text-[#DB202C]">FX</span>{" "}
                                {accessTierLabel} Pre-Course Preparation
                            </span>
                        </DialogTitle>
                    </DialogHeader>

                    <div className="mt-4 text-center">
                        <p className="text-lg font-bold text-white sm:text-xl">
                            Let&apos;s Get Started
                        </p>
                    </div>

                    {hasAppDownload ? (
                        <div className="mt-5 text-center">
                            <p className="text-base font-bold text-white sm:text-lg">
                                Download the App
                            </p>

                            {/* DESKTOP / LAPTOP: QR ONLY */}
                            {hasQrCode ? (
                                <div className="mt-3 hidden sm:flex sm:justify-center">
                                    <div
                                        className="
                                            flex
                                            w-fit
                                            items-center
                                            justify-center
                                            overflow-hidden
                                            rounded-[10px]
                                            bg-white
                                            p-2
                                            shadow-[0_14px_35px_rgba(0,0,0,0.25)]
                                        "
                                    >
                                        <img
                                            src={appDownload.qr_image_url}
                                            alt="YogaFX mobile app QR code"
                                            className="h-28 w-28 object-contain lg:h-32 lg:w-32"
                                        />
                                    </div>
                                </div>
                            ) : (
                                <div className="mt-3 hidden sm:block">
                                    <AppStoreBadges
                                        googlePlayUrl={
                                            appDownload?.google_play_url
                                        }
                                        appStoreUrl={appDownload?.app_store_url}
                                    />
                                </div>
                            )}

                            {/* MOBILE: STORE BUTTONS ONLY */}
                            <div className="mt-3 sm:hidden">
                                <AppStoreBadges
                                    googlePlayUrl={appDownload?.google_play_url}
                                    appStoreUrl={appDownload?.app_store_url}
                                />
                            </div>
                        </div>
                    ) : null}

                    <div className="mt-5">
                        <div
                            className="mx-auto flex max-w-[400px] items-center gap-3"
                            aria-hidden="true"
                        >
                            <div className="h-px flex-1 bg-white/45" />

                            <span className="text-xs font-extrabold uppercase tracking-[0.18em] text-white">
                                OR
                            </span>

                            <div className="h-px flex-1 bg-white/45" />
                        </div>
                    </div>

                    <div className="mt-4 text-center">
                        <p className="text-base font-bold text-white sm:text-lg">
                            Continue using browser
                        </p>

                        <Button
                            type="button"
                            onClick={handleContinueBrowser}
                            className="
                                mt-3
                                min-h-[52px]
                                w-full
                                max-w-[360px]
                                rounded-[7px]
                                bg-[#DB202C]
                                px-7
                                py-3
                                text-base
                                font-bold
                                italic
                                text-white
                                shadow-[0_10px_28px_rgba(219,32,44,0.28)]
                                transition-colors
                                duration-200
                                hover:bg-[#c01a25]
                                focus:outline-none
                                focus:ring-4
                                focus:ring-[#DB202C]/35
                                sm:text-lg
                            "
                        >
                            Click Here
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
