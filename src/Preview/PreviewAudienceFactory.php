<?php

namespace FlatRate\WikiContext\Preview;

use FlatRate\WikiContext\Support\GhostPreviewPolicy;
use InvalidArgumentException;

/**
 * Builds ordinary audience principals for ghost preview.
 *
 * ADMIN_ELEVATED_VISIBILITY_FOR_USER_PREVIEW=false
 */
final class PreviewAudienceFactory
{
    public function create(?string $profile = null): PreviewAudience
    {
        $profile = $profile ?? GhostPreviewPolicy::DEFAULT_AUDIENCE;
        $allowed = GhostPreviewPolicy::audienceProfiles();

        if (!in_array($profile, $allowed, true)) {
            throw new InvalidArgumentException('preview_audience_unsupported:' . $profile);
        }

        return new PreviewAudience($profile, false);
    }

    /**
     * @return list<PreviewAudience>
     */
    public function requiredAudiences(): array
    {
        $audiences = [];
        foreach (GhostPreviewPolicy::audienceProfiles() as $profile) {
            $audiences[] = $this->create($profile);
        }

        return $audiences;
    }
}
