<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$file = new \Illuminate\Http\UploadedFile(__DIR__.'/phpunit.xml', 'phpunit.xml', 'text/xml', null, true);

$request = Illuminate\Http\Request::create('/api/test', 'POST', [
    'attachments' => [$file]
], [], [
    'attachments' => [$file]
]);

var_dump($request->hasFile('attachments'));
var_dump(array_keys($request->file('attachments', [])));
