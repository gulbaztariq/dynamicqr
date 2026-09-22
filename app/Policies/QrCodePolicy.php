<?php

namespace App\Policies;

use App\Models\QrCode;
use App\Models\User;

class QrCodePolicy
{
    /** Staff can reach anything; customers only their own codes. */
    public function view(User $user, QrCode $qrCode): bool
    {
        return $user->isStaff() || $qrCode->user_id === $user->id;
    }

    /**
     * Changing where a printed code points. A super admin can lock an individual
     * code (user_can_edit = false) when a customer should not be able to move it.
     */
    public function updateTargetUrl(User $user, QrCode $qrCode): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return $qrCode->user_id === $user->id
            && $qrCode->user_can_edit
            && $user->is_active;
    }

    /** Label and notes — the cosmetic fields a customer owns. */
    public function update(User $user, QrCode $qrCode): bool
    {
        return $this->updateTargetUrl($user, $qrCode);
    }

    /** Pausing a code is the owner's call, or staff's. */
    public function togglePause(User $user, QrCode $qrCode): bool
    {
        return $this->updateTargetUrl($user, $qrCode);
    }

    public function manage(User $user, QrCode $qrCode): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, QrCode $qrCode): bool
    {
        return $user->isSuperAdmin();
    }
}
