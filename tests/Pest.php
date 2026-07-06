<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tatun55\Nawate\Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
