<?php

namespace App\Listeners;

use App\Events\DocumentCreated;
use App\Services\Plan\PlanService;

class IncrementDocumentUsage
{
    public function __construct(
        private PlanService $planService,
    ) {}

    public function handle(DocumentCreated $event): void
    {
        $document = $event->document;
        $tenant = $document->tenant ?? null;

        if (! $tenant) {
            return;
        }

        $this->planService->incrementUsage($tenant, 'documents');
    }
}
