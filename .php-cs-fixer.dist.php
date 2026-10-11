<?php
$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/public', __DIR__ . '/tests'])
    ->name('*.php');

return (new PhpCsFixer\Config())->setRules(['@PSR12' => true])->setFinder($finder);