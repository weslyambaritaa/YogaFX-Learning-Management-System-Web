import PublicCheckoutPanel from "@/Components/public/PublicCheckoutPanel";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";

export default function Checkout({ checkout }) {
    const packageTitle = checkout.package?.title ?? checkout.access_tier?.name ?? 'YogaFX Package';
    const packageDescription =
        checkout.package?.description ??
        'PayPal still handles payment processing behind the scenes, while this page keeps checkout calm, onsite, and tied to the correct YogaFX offer.';

    return (
        <PublicFlowLayout
            title="Checkout"
            eyebrow="Secure Checkout"
            heading={packageTitle}
            description={packageDescription}
        >
            <PublicCheckoutPanel checkout={checkout} />
        </PublicFlowLayout>
    );
}
