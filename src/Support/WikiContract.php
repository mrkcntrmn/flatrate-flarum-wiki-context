<?php

namespace FlatRate\WikiContext\Support;

/**
 * Explicit semantic wiki-contract version bound into preview acceptance receipts.
 *
 * Bump deliberately when membership, directory-display, or audience-visibility
 * contract semantics change in a way that must invalidate prior PASS receipts.
 */
final class WikiContract
{
    public const VERSION = 'flatrate.wiki.context.contract.v1';
}
