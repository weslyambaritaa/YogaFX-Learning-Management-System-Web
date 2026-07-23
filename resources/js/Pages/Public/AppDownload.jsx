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
        >
            <div
                className="text-center text-white"
                style={{ fontFamily: FONT_FAMILY }}
            >
                <div className="space-y-5">
                    <AppStoreBadges
                        googlePlayUrl={appDownload?.google_play_url ?? null}
                        appStoreUrl={appDownload?.app_store_url ?? null}
                        imageClassName="h-16 w-auto object-contain"
                    />

                </div>
            </div>
        </PublicFlowLayout>
    );
}
