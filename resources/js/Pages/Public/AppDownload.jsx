import AppStoreBadges from "@/Components/public/AppStoreBadges";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";

const FONT_FAMILY = "'Montserrat', sans-serif";

export default function AppDownload({ appDownload }) {
    const hasAnyLink = Boolean(appDownload?.has_any_link);

    return (
        <PublicFlowLayout
            title="Download App"
            heading={
                <span
                    className="block text-balance"
                    style={{
                        fontFamily: FONT_FAMILY,
                        fontSize: "clamp(26px, 3.4vw, 34px)",
                        fontWeight: 700,
                        lineHeight: 1.2,
                    }}
                >
                    Download the YogaFX app
                </span>
            }
            description={
                <span
                    className="block text-balance"
                    style={{
                        fontFamily: FONT_FAMILY,
                        fontSize: "18px",
                        fontWeight: 500,
                        lineHeight: 1.6,
                    }}
                >
                    Choose your store below.
                </span>
            }
        >
            <div
                className="rounded-[5px] border border-white/10 bg-white/[0.04] p-6 text-center text-white sm:p-8"
                style={{ fontFamily: FONT_FAMILY }}
            >
                <div className="space-y-5">
                    <AppStoreBadges
                        googlePlayUrl={appDownload?.google_play_url ?? null}
                        appStoreUrl={appDownload?.app_store_url ?? null}
                        imageClassName="h-16 w-auto object-contain"
                    />
                    <p className="text-sm leading-7 text-white/65">
                        {hasAnyLink
                            ? "Open the store that matches your device."
                            : "Store links are not available yet."}
                    </p>
                </div>
            </div>
        </PublicFlowLayout>
    );
}
