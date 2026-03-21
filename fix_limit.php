<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$plan = \App\Models\Plan::where('slug', 'free')->first();
if ($plan) {
    $limits = $plan->limits;
    $limits['documents_month'] = 500;
    $plan->limits = $limits;
    $plan->save();
    echo "Free plan limit: 500\n";
}

$sub = \App\Models\Subscription::where('tenant_id', 1)->first();
if ($sub) {
    $usage = $sub->usage ?? [];
    $usage['documents_month'] = 180;
    $sub->usage = $usage;
    $sub->save();
    echo "Usage reset to 180\n";
}
echo "Done\n";
