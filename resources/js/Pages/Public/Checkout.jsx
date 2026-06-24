import PublicCheckoutPanel from "@/Components/public/PublicCheckoutPanel";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";
import { usePage } from "@inertiajs/react";

function firstCheckoutMessage(errors = {}, flash = {}) {
    return (
        errors.payment_method ||
        errors.general ||
        flash.payment_error ||
        flash.error ||
        ""
    );
}

export default function Checkout({ checkout }) {
    const { errors = {}, flash = {} } = usePage().props;
    const checkoutMessage = firstCheckoutMessage(errors, flash);

    return (
        <PublicFlowLayout
            title="Checkout"
            eyebrow="Secure Checkout"
            heading="Complete YogaFX checkout without leaving this flow"
            description="Keep your billing details on this page while PayPal handles approval behind the scenes. If a payment attempt fails, you can retry from here without starting over."
        >
            <div className="space-y-6">
                {checkoutMessage ? (
                    <div className="rounded-xl border border-rose-400/30 bg-rose-500/10 px-5 py-4 text-sm font-medium text-rose-100">
                        {checkoutMessage}
                    </div>
                ) : null}

                <PublicCheckoutPanel
                    key={checkout.create_order_url}
                    checkout={checkout}
                    initialServerError={checkoutMessage}
                />
            </div>
        </PublicFlowLayout>
    );
}