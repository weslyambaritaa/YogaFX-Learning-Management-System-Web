<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Certificate;
use App\Models\CertificateDownloadEvent;
use App\Models\Module;
use App\Models\User;

class CertificateDownloadTrackingService
{
    public function record(User $user, Certificate $certificate): void
    {
        if (! $user->access_tier_id) {
            return;
        }

        Module::query()
            ->where('certificate_enabled', true)
            ->whereHas('accessTiers', fn ($query) => $query->where('access_tiers.id', $user->access_tier_id))
            ->withCount([
                'lessons',
                'assignments as live_assignments_count' => fn ($query) => $query->where('status', Assignment::STATUS_LIVE),
            ])
            ->get()
            ->filter(fn (Module $module) => $module->lessons_count === 0 && $module->live_assignments_count === 0)
            ->each(function (Module $module) use ($certificate, $user): void {
                CertificateDownloadEvent::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'module_id' => $module->id,
                    ],
                    [
                        'certificate_id' => $certificate->id,
                        'downloaded_at' => now(),
                    ],
                );
            });
    }
}
