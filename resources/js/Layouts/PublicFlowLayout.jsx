import { Head, Link } from '@inertiajs/react';

export default function PublicFlowLayout({
    title,
    eyebrow,
    heading,
    description,
    aside,
    children,
}) {
    return (
        <>
            <Head title={title} />

            <div className="min-h-screen bg-black text-gray-900">
                <div className="absolute inset-0" />

                <div className="relative mx-auto flex min-h-screen max-w-[1280px] flex-col px-4 py-6 sm:px-6 lg:px-10">
                    {/* Header — tanpa Simulated Payment Flow */}
                    <header className="flex items-center justify-center mt-6">
                        <Link href="/">
                            <img
                                src="https://yogafx.b-cdn.net/content/Logo%20YogAFX.png"
                                alt="YogaFX"
                                className="h-16 w-auto object-contain"
                            />
                        </Link>
                    </header>

                    <main className="flex flex-1 items-center py-6 lg:py-8">
                        <div className="grid w-full gap-8 lg:grid-cols-[minmax(0,1fr)_360px]">
                            {/* Card kiri */}
                            <section className="rounded-[10px] border border-gray-200 bg-white p-6 shadow-[0_30px_100px_rgba(0,0,0,0.4)] sm:p-8 lg:p-10">
                                <div className="space-y-5">
                                    <div className="space-y-3">
                                        {/* Eyebrow — hanya merender komponen jika properti eyebrow diisi teks */}
                                        {eyebrow && (
                                            <p className="text-sm font-semibold text-gray-900">
                                                {eyebrow}
                                            </p>
                                        )}
                                        
                                        {/* Heading — hitam */}
                                        <h1 className="max-w-3xl text-4xl font-semibold tracking-[-0.04em] text-gray-900 sm:text-5xl">
                                            {heading}
                                        </h1>
                                        
                                        {/* Description — abu gelap */}
                                        <p className="max-w-2xl text-sm leading-7 text-gray-600 sm:text-base">
                                            {description}
                                        </p>
                                    </div>

                                    {/* Inner card form */}
                                    <div className="rounded-[28px] border border-gray-200 bg-gray-50 p-4 sm:p-5">
                                        {children}
                                    </div>
                                </div>
                            </section>

                            {/* Card kanan */}
                            <aside className="rounded-[10px] border border-gray-200 bg-white p-6 sm:p-7 self-start">
                                {aside}
                            </aside>
                        </div>
                    </main>
                </div>
            </div>
        </>
    );
}