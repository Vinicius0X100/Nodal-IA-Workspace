<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::create('/api/test', 'POST', [], [], [
    'attachments' => [
        new \Illuminate\Http\UploadedFile(__DIR__.'/phpunit.xml', 'phpunit.xml', 'text/xml', null, true)
    ]
]);

$attachments = $request->file('attachments', []);
foreach ($attachments as $file) {
    if (!$file instanceof \Illuminate\Http\UploadedFile) {
        echo "Not an UploadedFile\n";
    } else {
        echo "Valid file: " . $file->getClientOriginalName() . "\n";
    }
}
