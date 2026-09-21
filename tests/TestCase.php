<?php

namespace Tests;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * 各テストの実行終了後に呼ばれる処理
     */
    protected function tearDown(): void
    {
        // Carbonで固定した日時をリセットし、実際の現在時刻に戻す
        Carbon::setTestNow();

        parent::tearDown();
    }
}
