<?php

use App\Models\AccessTier;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        AccessTier::query()
            ->get()
            ->each(function (AccessTier $tier): void {
                $canonicalSlug = AccessTier::canonicalSlug($tier->slug);
                $paymentLink = AccessTier::publicPaymentPathForSlug($canonicalSlug);

                if ($canonicalSlug === $tier->slug && $paymentLink === $tier->payment_link) {
                    return;
                }

                if ($paymentLink === null) {
                    return;
                }

                $tier->forceFill([
                    'slug' => $canonicalSlug,
                    'payment_link' => $paymentLink,
                ])->save();
            });
    }

    public function down(): void
    {
        //
    }
};
