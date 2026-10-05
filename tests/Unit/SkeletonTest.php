<?php

declare(strict_types=1);

use Sunrice\Facades\Sunrice;

it('loads the service provider and reports a version', function () {
    expect(Sunrice::version())->toBeString()->not->toBeEmpty();
});
