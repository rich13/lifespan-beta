<?php

namespace App\Models\Traits;

use App\Models\SpanCapabilities\BandCapability;
use App\Models\Span;
use Illuminate\Support\Collection;

trait HasBandCapabilities
{
    /**
     * Get the band capability for this span
     */
    public function asBand(): ?BandCapability
    {
        if ($this->type_id !== 'band') {
            return null;
        }
        return new BandCapability($this);
    }

    /**
     * Get current members of the band
     */
    public function getCurrentMembers(): Collection
    {
        return $this->asBand()?->getCurrentMembers() ?? collect();
    }

    /**
     * Get all members (past and present) of the band
     */
    public function getAllMembers(): Collection
    {
        return $this->asBand()?->getAllMembers() ?? collect();
    }

    /**
     * Get the band's discography (things created by the band)
     */
    public function getDiscography(): Collection
    {
        return $this->asBand()?->getDiscography() ?? collect();
    }

    /**
     * Get the band's status (active/disbanded)
     */
    public function getStatus(): ?string
    {
        return $this->asBand()?->getStatus();
    }

    /**
     * Add a member to the band
     */
    public function addMember(Span $member, array $metadata = []): void
    {
        $this->asBand()?->addMember($member, $metadata);
    }

    /**
     * Remove a member from the band
     */
    public function removeMember(Span $member): void
    {
        $this->asBand()?->removeMember($member);
    }
} 