<?php

use App\Models\User;

test('allows unverified users when verification is optional', function () {
    config()->set('app.email_verification_required', false);

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('redirects unverified users to the verification prompt when verification is required', function () {
    config()->set('app.email_verification_required', true);

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('verification.notice'));
});
