<?php

namespace App\Events;

use App\Models\Document;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Document $document) {}
}
