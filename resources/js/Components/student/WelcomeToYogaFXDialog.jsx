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

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                showCloseButton={false}
                onInteractOutside={(event) => event.preventDefault()}
                onEscapeKeyDown={(event) => event.preventDefault()}
                overlayClassName="bg-black/70 backdrop-blur-sm"
                className="
                    w-[calc(100%-24px)]
                    max-h-[95dvh]
                    overflow-y-auto
                    rounded-[18px]
                    border
                    border-white/15
                    bg-[#141110]
                    p-0
                    text-white
                    shadow-[0_30px_90px_rgba(0,0,0,0.55)]
                    sm:max-w-[760px]
                    sm:overflow-visible
                "
                style={{
                    fontFamily: FONT_FAMILY,
                }}
            >
                <div className="px-6 py-6 sm:px-10 sm:py-7">
                    <DialogHeader className="items-center text-center">
                        <img
                            src={STUDENT_LOGO_URL}
                            alt="YogaFX"
                            className="h-14 w-auto object-contain sm:h-16"
                        />

                        <DialogTitle className="mt-4 text-center text-[23px] font-bold leading-[1.25] tracking-[-0.02em] text-white sm:text-[30px]">
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

                    <div className="mt-5 text-center">
                        <p className="text-xl font-bold text-white sm:text-2xl">
                            Let&apos;s Get Started
                        </p>

                        <Button
                            type="button"
                            className="
                                mt-4
                                min-h-[58px]
                                w-full
                                max-w-[420px]
                                rounded-[8px]
                                bg-[#DB202C]
                                px-8
                                py-4
                                text-lg
                                font-bold
                                italic
                                text-white
                                shadow-[0_12px_35px_rgba(219,32,44,0.3)]
                                transition-all
                                duration-200
                                hover:-translate-y-0.5
                                hover:bg-[#c01a25]
                                focus:outline-none
                                focus:ring-4
                                focus:ring-[#DB202C]/35
                                sm:text-xl
                            "
                            onClick={() => {
                                if (onContinueBrowser) {
                                    onContinueBrowser();
                                    return;
                                }

                                onOpenChange(false);
                            }}
                        >
                            Continue Using Browser
                        </Button>
                    </div>

                    {hasAppDownload ? (
                        <div className="mt-5 text-center">
                            <div
                                className="mx-auto flex max-w-[420px] items-center gap-4"
                                aria-hidden="true"
                            >
                                <div className="h-[2px] flex-1 bg-white/45" />

                                <span className="text-sm font-extrabold uppercase tracking-[0.2em] text-white">
                                    OR
                                </span>

                                <div className="h-[2px] flex-1 bg-white/45" />
                            </div>

                            <p className="mt-4 text-lg font-bold text-white sm:text-xl">
                                Get The App
                            </p>

                            <div className="mt-4">
                                {hasQrCode ? (
                                    <div className="hidden sm:block">
                                        <div
                                            className="
                                                mx-auto
                                                flex
                                                w-fit
                                                items-center
                                                justify-center
                                                overflow-hidden
                                                rounded-[16px]
                                                border
                                                border-white/10
                                                bg-white
                                                p-2.5
                                                shadow-[0_18px_50px_rgba(0,0,0,0.28)]
                                            "
                                        >
                                            <img
                                                src={appDownload.qr_image_url}
                                                alt="YogaFX mobile app QR code"
                                                className="h-32 w-32 object-contain"
                                            />
                                        </div>
                                    </div>
                                ) : (
                                    <div className="hidden sm:block">
                                        <AppStoreBadges
                                            googlePlayUrl={
                                                appDownload?.google_play_url
                                            }
                                            appStoreUrl={
                                                appDownload?.app_store_url
                                            }
                                        />
                                    </div>
                                )}

                                <div className="sm:hidden">
                                    <AppStoreBadges
                                        googlePlayUrl={
                                            appDownload?.google_play_url
                                        }
                                        appStoreUrl={appDownload?.app_store_url}
                                    />
                                </div>
                            </div>
                        </div>
                    ) : null}
                </div>
            </DialogContent>
        </Dialog>
    );
}
