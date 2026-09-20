<?php

namespace FlatRate\WikiContext\Context;

/**
 * Request-local transient semantic state validated during Discussion\Event\Saving.
 *
 * Stored only on the in-memory Discussion relation bag until Started fires.
 * Never serialized or persisted as a model attribute.
 */
final class PendingContextWrite
{
    public function __construct(
        public readonly ValidatedContextWrite $validated
    ) {
    }
}
