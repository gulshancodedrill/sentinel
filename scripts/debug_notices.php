<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

$account = \Drupal\user\Entity\User::load(1);
\Drupal::currentUser()->setAccount($account);

$request = Request::create('/portal/notices');
$request->attributes->set('_format', 'html');

$kernel = \Drupal::service('http_kernel');

try {
  $response = $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, FALSE);
  print substr($response->getContent(), 0, 500);
}
catch (\Throwable $e) {
  print \get_class($e) . ': ' . $e->getMessage() . "\n";
  foreach ($e->getTrace() as $frame) {
    $file = $frame['file'] ?? '[internal]';
    $line = $frame['line'] ?? 0;
    $func = ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '');
    print $file . ':' . $line . ' ' . $func . "\n";
  }
}
