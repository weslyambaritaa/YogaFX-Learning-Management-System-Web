import { Head, Link } from '@inertiajs/react';

export default function PublicFlowLayout({
    title,
    eyebrow,
    heading,
    description,
    aside,
    children,
    footer,
}) {
    return (
        <>
            <Head title={title} />

            <div className="min-h-screen bg-black text-white">
                <div className="absolute inset-0" />

                <div className="relative mx-auto flex min-h-screen max-w-[1280px] flex-col px-4 py-6 sm:px-6 lg:px-10">
                    {/* Header */}
                    <header className="flex items-center justify-center mt-10">
                        <Link href="/">
                            <img
                                src="https://yogafx.b-cdn.net/content/Logo%20YogAFX.png"
                                alt="YogaFX"
                                className="h-16 w-auto object-contain"
                            />
                        </Link>
                    </header>

                    <main className="flex flex-1 items-start py-6 lg:py-8">
                        <div className="grid w-full gap-8 lg:grid-cols-[minmax(0,1fr)_360px]">
                            {/* Kiri — tanpa card putih */}
                            <section className="p-6 sm:p-8 lg:p-10">
                                <div className="space-y-5">
                                    <div className="space-y-3">
                                        {eyebrow && (
                                            <p className="text-sm font-semibold text-white">
                                                {eyebrow}
                                            </p>
                                        )}
                                        <h1 className="max-w-3xl text-4xl font-semibold tracking-[-0.04em] text-white sm:text-5xl">
                                            {heading}
                                        </h1>
                                        <p className="max-w-2xl text-sm leading-7 text-white/70 sm:text-base">
                                            {description}
                                        </p>
                                    </div>

                                    {/* Inner card form */}
                                    <div className="py-4">
                                        {children}
                                    </div>

                                    {/* Footer — di luar inner card */}
                                    {footer && (
                                        <div className="mt-2">
                                            {footer}
                                        </div>
                                    )}
                                </div>
                            </section>

                            {/* Kanan — tanpa card putih */}
                            <aside className="p-6 sm:p-7 self-start">
                                {aside}
                            </aside>
                        </div>
                    </main>
                </div>
            </div>
        </>
    );
}