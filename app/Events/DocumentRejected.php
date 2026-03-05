<?php

namespace App\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentRejected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Model $document,
        public array $errorData
    ) {}
}
