import PublicCheckoutPanel from "@/Components/public/PublicCheckoutPanel";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";

export default function Checkout({ checkout }) {
    return (
        <PublicFlowLayout
            title="Checkout"
            eyebrow="Secure Checkout"
            heading="Complete YogaFX checkout without leaving this flow"
            description="PayPal still handles payment processing behind the scenes, while this page keeps checkout calm, onsite, and tied to the correct product tier."
        >
            <PublicCheckoutPanel checkout={checkout} />
        </PublicFlowLayout>
    );
}
