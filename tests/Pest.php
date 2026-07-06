<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use SparrowhawkLabs\Nawate\Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
