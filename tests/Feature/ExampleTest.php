<?php

test('redirects guests to login', function () {
    $response = $this->get('/');

    $response->assertRedirectToRoute('login');
});
