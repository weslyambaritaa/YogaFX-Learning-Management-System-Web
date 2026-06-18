import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-black px-4">
            {/* Logo */}
            <div className="mb-8">
                <Link href="/">
                    <img
                        src="https://yogafx.b-cdn.net/content/Logo%20YogAFX.png"
                        alt="YogaFX Logo"
                        className="h-16 w-auto object-contain"
                    />
                </Link>
            </div>

            {/* Card form login */}
            <div className="w-full max-w-md overflow-hidden rounded-xl bg-white border border-gray-200 px-8 py-6 shadow-2xl">
                {children}
            </div>
        </div>
    );
}