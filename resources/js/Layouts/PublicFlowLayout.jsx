import StudentBackButton from "@/Components/student/StudentBackButton";
import { Head, Link } from "@inertiajs/react";

export default function PublicFlowLayout({
    title,
    eyebrow,
    heading,
    description,
    aside,
    children,
    footer,
    showBackButton = false,
    largeLogo = false,
}) {
    return (
        <>
            <Head title={title} />

            <div className="min-h-screen bg-black text-white">
                <div className="absolute inset-0" />

                <div className="relative mx-auto flex min-h-screen max-w-[1280px] flex-col px-4 py-6 sm:px-6 lg:px-10">
                    {showBackButton ? (
                        <div className="w-full pt-4">
                            <StudentBackButton fallbackHref={route("login")} />
                        </div>
                    ) : null}

                    <div className="flex flex-1 flex-col items-center justify-center pb-12 pt-6 lg:pt-8">
                        <header
                            className={
                                largeLogo
                                    ? "mb-10 flex items-center justify-center"
                                    : "mb-8 flex items-center justify-center"
                            }
                        >
                            <Link
                                href="/"
                                className="inline-flex items-center justify-center"
                            >
                                <img
                                    src="https://yogafx.b-cdn.net/content/Logo%20YogAFX.png"
                                    alt="YogaFX"
                                    className={
                                        largeLogo
                                            ? "h-28 w-auto object-contain sm:h-32"
                                            : "h-16 w-auto object-contain"
                                    }
                                />
                            </Link>
                        </header>

                        <main className="flex w-full flex-col items-center justify-center">
                            <div className="flex w-full max-w-xl flex-col items-center gap-8">
                                <section className="w-full">
                                    <div className="flex w-full flex-col space-y-5 text-center">
                                        {(eyebrow ||
                                            heading ||
                                            description) && (
                                            <div className="space-y-3">
                                                {eyebrow && (
                                                    <p className="text-sm font-semibold text-white">
                                                        {eyebrow}
                                                    </p>
                                                )}

                                                {heading && (
                                                    <h1 className="mx-auto max-w-3xl text-3xl font-semibold tracking-[-0.04em] text-white sm:text-4xl">
                                                        {heading}
                                                    </h1>
                                                )}

                                                {description && (
                                                    <p className="mx-auto max-w-2xl text-sm leading-7 text-white/70 sm:text-base">
                                                        {description}
                                                    </p>
                                                )}
                                            </div>
                                        )}

                                        <div className="w-full py-4 text-left">
                                            {children}
                                        </div>

                                        {footer && (
                                            <div className="mt-2 w-full">
                                                {footer}
                                            </div>
                                        )}
                                    </div>
                                </section>

                                {aside && (
                                    <aside className="w-full text-center">
                                        {aside}
                                    </aside>
                                )}
                            </div>
                        </main>
                    </div>
                </div>
            </div>
        </>
    );
}
