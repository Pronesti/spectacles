<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Function-style Pest tests in these directories are bound to the
| application's base test case. Existing class-based PHPUnit tests
| declare their parent class themselves and are unaffected.
|
*/

pest()->extend(TestCase::class)->in('Feature', 'Browser');
