<?php

declare(strict_types=1);

test('application returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

test('example test passes', function () {
    expect(true)->toBeTrue();
});
