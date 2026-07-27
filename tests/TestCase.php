<?php

namespace Tests;

use App\Services\Analytics\SetQuery;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // SetQuery memoises per request; a test process is many requests, so
        // clear it or one test's rows leak into the next.
        SetQuery::flushMemo();
    }
}
