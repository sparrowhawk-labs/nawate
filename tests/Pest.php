<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use SparrowhawkLabs\Jess\Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
