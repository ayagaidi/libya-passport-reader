<?php

namespace Tests\Unit;

use App\Services\Passport\VisualZone\TesseractTsvParser;
use PHPUnit\Framework\TestCase;

final class TesseractTsvParserTest extends TestCase
{
    public function test_it_builds_lines_and_normalized_confidence_from_tsv(): void
    {
        $tsv = implode("\n", [
            "level\tpage_num\tblock_num\tpar_num\tline_num\tword_num\tleft\ttop\twidth\theight\tconf\ttext",
            "5\t1\t1\t1\t1\t1\t10\t20\t70\t20\t90.0\tSurname",
            "5\t1\t1\t1\t1\t2\t90\t20\t60\t20\t80.0\tTESTER",
            "5\t1\t1\t1\t2\t1\t10\t60\t90\t20\t70.0\tNationality",
            "5\t1\t1\t1\t2\t2\t110\t60\t60\t20\t60.0\tLIBYAN",
        ]);

        $result = (new TesseractTsvParser)->parse($tsv);

        self::assertSame("Surname TESTER\nNationality LIBYAN", $result['text']);
        self::assertSame(0.75, $result['confidence']);
        self::assertSame(0.85, $result['lines'][0]['confidence']);
        self::assertSame(0.65, $result['lines'][1]['confidence']);
        self::assertSame(['left' => 10, 'top' => 20, 'width' => 140, 'height' => 20], $result['lines'][0]['box']);
    }
}
