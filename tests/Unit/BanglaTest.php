<?php

namespace Tests\Unit;

use App\Support\Bangla;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class BanglaTest extends TestCase
{
    public function test_money_uses_bangla_digits_and_the_taka_symbol(): void
    {
        $this->assertSame('৳ ১,২৩৪.৫০', Bangla::money(1234.5));
        $this->assertSame('৳ ০.০০', Bangla::money(null));
        $this->assertSame('৳ ৭২.৫০', Bangla::money('72.50'));
    }

    public function test_number_keeps_grouping_and_decimals(): void
    {
        $this->assertSame('১২,৩৪৫.৬৭', Bangla::number('12345.67'));
        $this->assertSame('৫.০০০', Bangla::number(5, 3));
    }

    public function test_digits_transliterates_only_ascii_digits(): void
    {
        $this->assertSame('০১২৩৪৫৬৭৮৯', Bangla::digits('0123456789'));
        $this->assertSame('CBP-২০২৬-০০০১২৩', Bangla::digits('CBP-2026-000123'));
    }

    public function test_date_uses_bangla_digits(): void
    {
        $this->assertSame('২৬/১০/২০২৬', Bangla::date(new DateTimeImmutable('2026-10-26')));
        $this->assertSame('', Bangla::date(null));
    }
}
