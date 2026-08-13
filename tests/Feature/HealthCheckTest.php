<?php

declare(strict_types=1);

it('exposes a health endpoint for the load balancer', function () {
    $this->get('/up')->assertOk();
});

it('serves the storefront home page', function () {
    $this->get('/')->assertOk();
});
