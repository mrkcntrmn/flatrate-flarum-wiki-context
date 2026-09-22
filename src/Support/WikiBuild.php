<?php

namespace FlatRate\WikiContext\Support;

/**
 * Explicit, immutable extension build authority for preview acceptance receipts.
 *
 * Do not derive this from git metadata, composer opportunistic versioning, or
 * runtime filesystem state. Bump deliberately when the packaged build changes
 * in a way that must invalidate prior PASS receipts.
 */
final class WikiBuild
{
    public const BUILD_ID = 'flatrate-wiki-context.build.p1b.20260922';
}
