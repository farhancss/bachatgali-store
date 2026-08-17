<?php

declare(strict_types=1);

it('exposes a health endpoint for the load balancer', function () {
    $this->get('/up')->assertOk();
});

/*
| The storefront home page assertion arrives in phase 2, once there is a real
| page to assert against. It is omitted here on purpose: the Blade layout
| calls @vite, so the test would require `npm run build` to have run and would
| fail on a fresh clone for reasons unrelated to the code under test.
*/
