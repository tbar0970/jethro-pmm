<?php

$finder = (new PhpCsFixer\Finder())
    ->exclude([
      'include/phpword',
      'include/swiftmailer',
      'resources'
    ])
    ->in(__DIR__);

return (new PhpCsFixer\Config())
    ->setRules([
      'no_trailing_whitespace'=>true,
      'encoding'=>true
    ])
    ->setFinder($finder);
