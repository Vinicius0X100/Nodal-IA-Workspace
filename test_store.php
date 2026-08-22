<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$file = new \Illuminate\Http\UploadedFile(__DIR__.'/phpunit.xml', 'phpunit.xml', 'text/xml', null, true);
$path = $file->store('my-folder', 'chat-attachments');
echo "Stored at: " . $path . "\n";
echo "Exists? " . (Illuminate\Support\Facades\Storage::disk('chat-attachments')->exists($path) ? 'Yes' : 'No') . "\n";
