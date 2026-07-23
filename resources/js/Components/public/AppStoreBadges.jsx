const GOOGLE_PLAY_BADGE_URL =
    "https://yogafx.b-cdn.net/content/vecteezy_google-play-store-download-button-in-white-colors-download_12871364.png";
const APP_STORE_BADGE_URL =
    "https://yogafx.b-cdn.net/content/vecteezy_app-store-download-button-in-white-colors-download-on-the_12871374.png";

export default function AppStoreBadges({
    googlePlayUrl,
    appStoreUrl,
    className = "",
    itemClassName = "transition-transform hover:scale-[1.02]",
    imageClassName = "h-14 w-auto object-contain",
    disabledClassName = "cursor-not-allowed opacity-45",
}) {
    const items = [
        {
            key: "google-play",
            href: googlePlayUrl,
            src: GOOGLE_PLAY_BADGE_URL,
            alt: "Download on Google Play",
        },
        {
            key: "app-store",
            href: appStoreUrl,
            src: APP_STORE_BADGE_URL,
            alt: "Download on the App Store",
        },
    ];

    return (
        <div
            className={`flex flex-wrap items-center justify-center gap-3 ${className}`}
        >
            {items.map((item) =>
                item.href ? (
                    <a
                        key={item.key}
                        href={item.href}
                        target="_blank"
                        rel="noopener noreferrer"
                        className={itemClassName}
                    >
                        <img
                            src={item.src}
                            alt={item.alt}
                            className={imageClassName}
                        />
                    </a>
                ) : (
                    <div
                        key={item.key}
                        aria-disabled="true"
                        className={disabledClassName}
                    >
                        <img
                            src={item.src}
                            alt={`${item.alt} not available`}
                            className={imageClassName}
                        />
                    </div>
                ),
            )}
        </div>
    );
}
