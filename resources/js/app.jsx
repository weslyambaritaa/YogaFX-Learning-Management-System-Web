import "../css/app.css";
import "flag-icons/css/flag-icons.min.css";
import { createInertiaApp } from "@inertiajs/react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createRoot } from "react-dom/client";

window.addEventListener("vite:preloadError", (event) => {
    event.preventDefault();

    const storageKey = "vite-preload-error-reload-at";
    const lastReloadAt = Number(
        window.sessionStorage.getItem(storageKey) ?? 0,
    );

    /*
     * Reload halaman satu kali untuk mengambil manifest dan hashed
     * assets terbaru setelah deployment.
     *
     * Batas 10 detik mencegah reload loop jika asset memang belum
     * tersedia pada server.
     */
    if (Date.now() - lastReloadAt < 10_000) {
        return;
    }

    window.sessionStorage.setItem(
        storageKey,
        String(Date.now()),
    );

    window.location.reload();
});

createInertiaApp({
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob("./Pages/**/*.jsx"),
        ),

    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});