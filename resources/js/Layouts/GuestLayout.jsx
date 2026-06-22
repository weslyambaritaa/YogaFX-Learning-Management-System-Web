import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-black px-4 py-10 sm:py-12">
            {/* Logo */}
            <div className="mb-6 sm:mb-8">
                <Link href="/">
                    <img
                        src="https://yogafx.b-cdn.net/content/Logo%20YogAFX.png"
                        alt="YogaFX Logo"
                        className="h-14 w-auto object-contain sm:h-16"
                    />
                </Link>
            </div>

            {/* Card form login */}
            <div className="w-full max-w-lg overflow-hidden rounded-xl border border-gray-200 bg-white px-5 py-6 shadow-2xl sm:px-8">
                {children}
            </div>
        </div>
    );
}
