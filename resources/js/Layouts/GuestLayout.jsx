import StudentBackButton from '@/Components/student/StudentBackButton';
import { Link } from '@inertiajs/react';
export default function GuestLayout({ children }) {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-black px-4 py-10 sm:py-12">
            <div className="mb-6 w-full max-w-lg">
                <StudentBackButton fallbackHref={route('login')} />
            </div>
            {/* Logo */}
            <div className="mb-6 sm:mb-8">
                <Link href="/">
                    <img
                        src="https://yogafx.b-cdn.net/content/Logo%20YogAFX.png"
                        alt="YogaFX Logo"
                        className="h-16 w-auto object-contain"
                    />
                </Link>
            </div>
            {/* Form content (no card wrapper) */}
            <div className="w-full max-w-lg px-5 sm:px-8">
                {children}
            </div>
        </div>
    );
}