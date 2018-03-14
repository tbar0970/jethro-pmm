<?php

$finder = Symfony\CS\Finder\DefaultFinder::create()
    ->exclude('include/phpword')
    ->exclude('include/swiftmailer')
    ->exclude('resources')
    ->in(__DIR__)
;

return Symfony\CS\Config\Config::create()
    ->level(Symfony\CS\FixerInterface::NONE_LEVEL)
    ->fixers(array('trailing_spaces', 'encoding'))
    ->finder($finder)
;
