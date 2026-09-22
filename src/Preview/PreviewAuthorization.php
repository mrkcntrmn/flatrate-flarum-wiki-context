<?php

namespace FlatRate\WikiContext\Preview;

use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;

/**
 * Server-side WIKI-001P ghost-preview authorization.
 *
 * Preview authority is never derived from a query parameter, secret URL, or
 * client-only presentation state. Only a real Flarum administrator may enter
 * the preview control path.
 */
final class PreviewAuthorization
{
    public function assertAdmin(User $actor): void
    {
        if (!$actor->id || !$actor->isAdmin()) {
            throw new PermissionDeniedException();
        }
    }
}
