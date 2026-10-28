<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // public/build is gitignored, so a fresh checkout has no Vite manifest
        // and every page render would 500. Tests assert on Inertia props, not
        // on built assets, so they must not depend on `npm run build`.
        $this->withoutVite();
    }
}
