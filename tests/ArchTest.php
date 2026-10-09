<?php

declare(strict_types=1);

arch('every file declares strict types')
    ->expect('Jessecruz\ResendInboxBundle')
    ->toUseStrictTypes();

arch('classes are final, except Doctrine entities (proxies extend them)')
    ->expect('Jessecruz\ResendInboxBundle')
    ->classes()
    ->toBeFinal()
    ->ignoring(['Jessecruz\ResendInboxBundle\Entity', 'Jessecruz\ResendInboxBundle\Tests']);

arch('decisions stay in the core: no Laravel here')
    ->expect('Jessecruz\ResendInboxBundle')
    ->not->toUse(['Illuminate', 'Livewire']);

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'var_dump', 'print_r', 'ray'])
    ->not->toBeUsed();
