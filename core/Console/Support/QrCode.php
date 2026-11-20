<?php

namespace Vayu\Console\Support;

/**
 * Minimal, dependency-free QR Code generator (byte mode).
 *
 * Supports automatic version selection and the four error-correction
 * levels, with Reed–Solomon ECC, data masking and penalty scoring —
 * enough to encode a URL and render it as a scannable terminal image.
 *
 * Algorithm ported from Project Nayuki's public-domain "QR Code generator".
 *
 * @see https://www.nayuki.io/page/qr-code-generator-library
 */
final class QrCode
{
    public const ECC_LOW = 0;
    public const ECC_MEDIUM = 1;
    public const ECC_QUARTILE = 2;
    public const ECC_HIGH = 3;

    /** Format-info bits per ECC level (L, M, Q, H). */
    private const ECC_FORMAT_BITS = [1, 0, 3, 2];

    private const PENALTY_N1 = 3;
    private const PENALTY_N2 = 3;
    private const PENALTY_N3 = 40;
    private const PENALTY_N4 = 10;

    /** ECC codewords per block, indexed [ecl][version]. */
    private const ECC_CODEWORDS_PER_BLOCK = [
        [-1, 7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28, 30, 28, 28, 28, 28, 30, 30, 26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        [-1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28],
        [-1, 13, 22, 18, 26, 18, 24, 18, 22, 20, 24, 28, 26, 24, 20, 30, 24, 28, 28, 26, 30, 28, 30, 30, 30, 30, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        [-1, 17, 28, 22, 16, 22, 28, 26, 26, 24, 28, 24, 28, 22, 24, 24, 30, 28, 28, 26, 28, 30, 24, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
    ];

    /** Number of error-correction blocks, indexed [ecl][version]. */
    private const NUM_ERROR_CORRECTION_BLOCKS = [
        [-1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8, 8, 9, 9, 10, 12, 12, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 21, 22, 24, 25],
        [-1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16, 17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49],
        [-1, 1, 1, 2, 2, 4, 4, 6, 6, 8, 8, 8, 10, 12, 16, 12, 17, 16, 18, 21, 20, 23, 23, 25, 27, 29, 34, 34, 35, 38, 40, 43, 45, 48, 51, 53, 56, 59, 62, 65, 68],
        [-1, 1, 1, 2, 4, 4, 4, 5, 6, 8, 8, 11, 11, 16, 16, 18, 16, 19, 21, 25, 25, 25, 34, 30, 32, 35, 37, 40, 42, 45, 48, 51, 54, 57, 60, 63, 66, 70, 74, 77, 81],
    ];

    private int $version;
    private int $size;

    /** @var array<int, array<int, bool>> row-major grid of modules (true = dark). */
    private array $modules = [];

    /** @var array<int, array<int, bool>> which modules are function patterns. */
    private array $isFunction = [];

    private function __construct(int $version, int $size)
    {
        $this->version = $version;
        $this->size = $size;

        for ($y = 0; $y < $size; $y++) {
            $this->modules[$y] = array_fill(0, $size, false);
            $this->isFunction[$y] = array_fill(0, $size, false);
        }
    }

    /**
     * Encode a string of text/bytes into a QR Code.
     */
    public static function encode(string $text, int $ecl = self::ECC_MEDIUM): self
    {
        $bytes = array_values(unpack('C*', $text) ?: []);
        $length = count($bytes);

        // Smallest version that fits the byte-mode payload.
        $version = 0;
        for ($v = 1; $v <= 40; $v++) {
            $capacityBits = self::numDataCodewords($v, $ecl) * 8;
            $charCountBits = $v <= 9 ? 8 : 16;
            $usedBits = 4 + $charCountBits + ($length * 8);
            if ($usedBits <= $capacityBits) {
                $version = $v;
                break;
            }
        }

        if ($version === 0) {
            throw new \RuntimeException('Data too long to encode as a QR Code.');
        }

        $charCountBits = $version <= 9 ? 8 : 16;

        // Build the bit stream: mode indicator (byte = 0100), char count, data.
        $bits = [];
        self::appendBits($bits, 0b0100, 4);
        self::appendBits($bits, $length, $charCountBits);
        foreach ($bytes as $b) {
            self::appendBits($bits, $b, 8);
        }

        $dataCapacityBits = self::numDataCodewords($version, $ecl) * 8;

        // Terminator + bit/byte padding.
        self::appendBits($bits, 0, min(4, $dataCapacityBits - count($bits)));
        self::appendBits($bits, 0, (8 - count($bits) % 8) % 8);

        $padByte = 0xEC;
        while (count($bits) < $dataCapacityBits) {
            self::appendBits($bits, $padByte, 8);
            $padByte ^= 0xEC ^ 0x11;
        }

        // Pack bits into data codewords.
        $dataCodewords = [];
        for ($i = 0; $i < count($bits); $i += 8) {
            $byte = 0;
            for ($j = 0; $j < 8; $j++) {
                $byte = ($byte << 1) | $bits[$i + $j];
            }
            $dataCodewords[] = $byte;
        }

        $qr = new self($version, $version * 4 + 17);
        $allCodewords = $qr->addEccAndInterleave($dataCodewords, $ecl);
        $qr->drawFunctionPatterns($ecl);
        $qr->drawCodewords($allCodewords);
        $qr->selectBestMask($ecl);

        return $qr;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function moduleAt(int $x, int $y): bool
    {
        return $this->modules[$y][$x] ?? false;
    }

    /**
     * Render as a compact terminal image using half-block characters: each
     * text line carries two module rows (upper half = foreground, lower
     * half = background) so the whole code stays small — one char wide and
     * half a char tall per module, roughly square on a typical 1:2 cell.
     *
     * A quiet zone of 2 light modules keeps the code reliably scannable on
     * phones while staying as compact as possible in the terminal.
     *
     * Colours are emitted as 24-bit true colour (pure #000 / #fff) rather
     * than the 8-colour "bright white", whose theme-dependent grey washes
     * out the contrast and makes the code hard to scan.
     *
     * @param int $quiet number of light modules to pad on every side
     */
    public function toTerminal(int $quiet = 2): string
    {
        $total = $this->size + 2 * $quiet;

        $dark = function (int $x, int $y) use ($quiet): bool {
            $mx = $x - $quiet;
            $my = $y - $quiet;
            if ($mx < 0 || $my < 0 || $mx >= $this->size || $my >= $this->size) {
                return false; // quiet zone is light
            }

            return $this->modules[$my][$mx];
        };

        $black = '0;0;0';
        $white = '255;255;255';

        $lines = [];
        for ($y = 0; $y < $total; $y += 2) {
            $line = '';
            for ($x = 0; $x < $total; $x++) {
                $top = $dark($x, $y);
                $bottom = ($y + 1 < $total) && $dark($x, $y + 1);
                // Upper half-block: foreground paints the top module,
                // background paints the bottom module.
                $fg = $top ? $black : $white;
                $bg = $bottom ? $black : $white;
                $line .= "\033[38;2;{$fg};48;2;{$bg}m\xE2\x96\x80"; // ▀
            }
            $line .= "\033[0m";
            $lines[] = $line;
        }

        return implode(PHP_EOL, $lines);
    }

    /* ------------------------------------------------------------------ */
    /* Encoding internals                                                 */
    /* ------------------------------------------------------------------ */

    /**
     * @param array<int,int> $bits
     */
    private static function appendBits(array &$bits, int $value, int $count): void
    {
        for ($i = $count - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }

    private static function numRawDataModules(int $version): int
    {
        $size = $version * 4 + 17;
        $result = $size * $size;
        $result -= 8 * 8 * 3;              // three finder patterns + separators
        $result -= 15 * 2 + 1;            // format info + dark module
        $result -= ($size - 16) * 2;      // timing patterns

        if ($version >= 2) {
            $numAlign = intdiv($version, 7) + 2;
            $result -= ($numAlign - 1) * ($numAlign - 1) * 25; // alignment patterns
            $result -= ($numAlign - 2) * 2 * 20;               // timing overlap
            if ($version >= 7) {
                $result -= 6 * 3 * 2;      // version info
            }
        }

        return $result;
    }

    private static function numDataCodewords(int $version, int $ecl): int
    {
        return intdiv(self::numRawDataModules($version), 8)
            - self::ECC_CODEWORDS_PER_BLOCK[$ecl][$version]
            * self::NUM_ERROR_CORRECTION_BLOCKS[$ecl][$version];
    }

    /**
     * @param array<int,int> $data
     * @return array<int,int>
     */
    private function addEccAndInterleave(array $data, int $ecl): array
    {
        $numBlocks = self::NUM_ERROR_CORRECTION_BLOCKS[$ecl][$this->version];
        $blockEccLen = self::ECC_CODEWORDS_PER_BLOCK[$ecl][$this->version];
        $rawCodewords = intdiv(self::numRawDataModules($this->version), 8);
        $numShortBlocks = $numBlocks - $rawCodewords % $numBlocks;
        $shortBlockLen = intdiv($rawCodewords, $numBlocks);

        $divisor = $this->reedSolomonDivisor($blockEccLen);

        /** @var array<int, array<int,int>> $blocks */
        $blocks = [];
        $k = 0;
        for ($i = 0; $i < $numBlocks; $i++) {
            $datLen = $shortBlockLen - $blockEccLen + ($i < $numShortBlocks ? 0 : 1);
            $dat = array_slice($data, $k, $datLen);
            $k += $datLen;
            $ecc = $this->reedSolomonRemainder($dat, $divisor);

            $block = $dat;
            if ($i < $numShortBlocks) {
                $block[] = 0; // placeholder column skipped during interleave
            }
            foreach ($ecc as $b) {
                $block[] = $b;
            }
            $blocks[$i] = $block;
        }

        $result = [];
        $blockLen = $shortBlockLen + 1;
        for ($i = 0; $i < $blockLen; $i++) {
            for ($j = 0; $j < $numBlocks; $j++) {
                // Skip the padding column that only short blocks carry.
                if ($i !== $shortBlockLen - $blockEccLen || $j >= $numShortBlocks) {
                    $result[] = $blocks[$j][$i];
                }
            }
        }

        return $result;
    }

    /**
     * @return array<int,int>
     */
    private function reedSolomonDivisor(int $degree): array
    {
        $result = array_fill(0, $degree, 0);
        $result[$degree - 1] = 1;

        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = $this->gfMultiply($result[$j], $root);
                if ($j + 1 < $degree) {
                    $result[$j] ^= $result[$j + 1];
                }
            }
            $root = $this->gfMultiply($root, 0x02);
        }

        return $result;
    }

    /**
     * @param array<int,int> $data
     * @param array<int,int> $divisor
     * @return array<int,int>
     */
    private function reedSolomonRemainder(array $data, array $divisor): array
    {
        $degree = count($divisor);
        $result = array_fill(0, $degree, 0);

        foreach ($data as $b) {
            $factor = $b ^ $result[0];
            array_shift($result);
            $result[] = 0;
            for ($i = 0; $i < $degree; $i++) {
                $result[$i] ^= $this->gfMultiply($divisor[$i], $factor);
            }
        }

        return $result;
    }

    private function gfMultiply(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ ((($z >> 7) & 1) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }

        return $z & 0xFF;
    }

    /* ------------------------------------------------------------------ */
    /* Matrix construction                                                */
    /* ------------------------------------------------------------------ */

    private function drawFunctionPatterns(int $ecl): void
    {
        // Timing patterns.
        for ($i = 0; $i < $this->size; $i++) {
            $this->setFunctionModule(6, $i, $i % 2 === 0);
            $this->setFunctionModule($i, 6, $i % 2 === 0);
        }

        // Finder patterns (with surrounding separators).
        $this->drawFinderPattern(3, 3);
        $this->drawFinderPattern($this->size - 4, 3);
        $this->drawFinderPattern(3, $this->size - 4);

        // Alignment patterns.
        $positions = $this->alignmentPatternPositions();
        $count = count($positions);
        for ($i = 0; $i < $count; $i++) {
            for ($j = 0; $j < $count; $j++) {
                $skip = ($i === 0 && $j === 0)
                    || ($i === 0 && $j === $count - 1)
                    || ($i === $count - 1 && $j === 0);
                if (!$skip) {
                    $this->drawAlignmentPattern($positions[$i], $positions[$j]);
                }
            }
        }

        $this->drawFormatBits($ecl, 0); // reserve the area; overwritten after masking
        $this->drawVersion();
    }

    private function drawFinderPattern(int $x, int $y): void
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $dist = max(abs($dx), abs($dy));
                $xx = $x + $dx;
                $yy = $y + $dy;
                if ($xx >= 0 && $xx < $this->size && $yy >= 0 && $yy < $this->size) {
                    $this->setFunctionModule($xx, $yy, $dist !== 2 && $dist !== 4);
                }
            }
        }
    }

    private function drawAlignmentPattern(int $x, int $y): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $this->setFunctionModule($x + $dx, $y + $dy, max(abs($dx), abs($dy)) !== 1);
            }
        }
    }

    /**
     * @return array<int,int>
     */
    private function alignmentPatternPositions(): array
    {
        if ($this->version === 1) {
            return [];
        }

        $numAlign = intdiv($this->version, 7) + 2;
        $step = $this->version === 32
            ? 26
            : intdiv($this->version * 4 + $numAlign * 2 + 1, $numAlign * 2 - 2) * 2;

        $result = array_fill(0, $numAlign, 0);
        $result[0] = 6;
        $pos = $this->size - 7;
        for ($i = $numAlign - 1; $i >= 1; $i--) {
            $result[$i] = $pos;
            $pos -= $step;
        }

        return $result;
    }

    private function drawFormatBits(int $ecl, int $mask): void
    {
        $data = (self::ECC_FORMAT_BITS[$ecl] << 3) | $mask;
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ ((($rem >> 9) & 1) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;

        for ($i = 0; $i <= 5; $i++) {
            $this->setFunctionModule(8, $i, $this->getBit($bits, $i));
        }
        $this->setFunctionModule(8, 7, $this->getBit($bits, 6));
        $this->setFunctionModule(8, 8, $this->getBit($bits, 7));
        $this->setFunctionModule(7, 8, $this->getBit($bits, 8));
        for ($i = 9; $i < 15; $i++) {
            $this->setFunctionModule(14 - $i, 8, $this->getBit($bits, $i));
        }

        for ($i = 0; $i < 8; $i++) {
            $this->setFunctionModule($this->size - 1 - $i, 8, $this->getBit($bits, $i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->setFunctionModule(8, $this->size - 15 + $i, $this->getBit($bits, $i));
        }
        $this->setFunctionModule(8, $this->size - 8, true); // always dark
    }

    private function drawVersion(): void
    {
        if ($this->version < 7) {
            return;
        }

        $rem = $this->version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ ((($rem >> 11) & 1) * 0x1F25);
        }
        $bits = ($this->version << 12) | $rem;

        for ($i = 0; $i < 18; $i++) {
            $bit = $this->getBit($bits, $i);
            $a = $this->size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $this->setFunctionModule($a, $b, $bit);
            $this->setFunctionModule($b, $a, $bit);
        }
    }

    /**
     * @param array<int,int> $data
     */
    private function drawCodewords(array $data): void
    {
        $i = 0;
        $bitLength = count($data) * 8;

        for ($right = $this->size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $this->size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y = $upward ? $this->size - 1 - $vert : $vert;
                    if (!$this->isFunction[$y][$x] && $i < $bitLength) {
                        $this->modules[$y][$x] = $this->getBit($data[$i >> 3], 7 - ($i & 7));
                        $i++;
                    }
                }
            }
        }
    }

    private function applyMask(int $mask): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->isFunction[$y][$x]) {
                    continue;
                }
                switch ($mask) {
                    case 0: $invert = ($x + $y) % 2 === 0; break;
                    case 1: $invert = $y % 2 === 0; break;
                    case 2: $invert = $x % 3 === 0; break;
                    case 3: $invert = ($x + $y) % 3 === 0; break;
                    case 4: $invert = (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0; break;
                    case 5: $invert = ($x * $y) % 2 + ($x * $y) % 3 === 0; break;
                    case 6: $invert = (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0; break;
                    case 7: $invert = ((($x + $y) % 2) + ($x * $y) % 3) % 2 === 0; break;
                    default: $invert = false;
                }
                if ($invert) {
                    $this->modules[$y][$x] = !$this->modules[$y][$x];
                }
            }
        }
    }

    private function selectBestMask(int $ecl): void
    {
        $bestMask = 0;
        $minPenalty = PHP_INT_MAX;

        for ($mask = 0; $mask < 8; $mask++) {
            $this->applyMask($mask);
            $this->drawFormatBits($ecl, $mask);
            $penalty = $this->penaltyScore();
            if ($penalty < $minPenalty) {
                $minPenalty = $penalty;
                $bestMask = $mask;
            }
            $this->applyMask($mask); // undo
        }

        $this->applyMask($bestMask);
        $this->drawFormatBits($ecl, $bestMask);
    }

    private function penaltyScore(): int
    {
        $result = 0;
        $size = $this->size;

        // Rows: runs of same colour + finder-like patterns.
        for ($y = 0; $y < $size; $y++) {
            $runColor = false;
            $runLen = 0;
            $history = [0, 0, 0, 0, 0, 0, 0];
            for ($x = 0; $x < $size; $x++) {
                if ($this->modules[$y][$x] === $runColor) {
                    $runLen++;
                    if ($runLen === 5) {
                        $result += self::PENALTY_N1;
                    } elseif ($runLen > 5) {
                        $result++;
                    }
                } else {
                    $this->finderPenaltyAddHistory($runLen, $history);
                    if (!$runColor) {
                        $result += $this->finderPenaltyCountPatterns($history) * self::PENALTY_N3;
                    }
                    $runColor = $this->modules[$y][$x];
                    $runLen = 1;
                }
            }
            $result += $this->finderPenaltyTerminate($runColor, $runLen, $history) * self::PENALTY_N3;
        }

        // Columns.
        for ($x = 0; $x < $size; $x++) {
            $runColor = false;
            $runLen = 0;
            $history = [0, 0, 0, 0, 0, 0, 0];
            for ($y = 0; $y < $size; $y++) {
                if ($this->modules[$y][$x] === $runColor) {
                    $runLen++;
                    if ($runLen === 5) {
                        $result += self::PENALTY_N1;
                    } elseif ($runLen > 5) {
                        $result++;
                    }
                } else {
                    $this->finderPenaltyAddHistory($runLen, $history);
                    if (!$runColor) {
                        $result += $this->finderPenaltyCountPatterns($history) * self::PENALTY_N3;
                    }
                    $runColor = $this->modules[$y][$x];
                    $runLen = 1;
                }
            }
            $result += $this->finderPenaltyTerminate($runColor, $runLen, $history) * self::PENALTY_N3;
        }

        // 2x2 blocks of the same colour.
        for ($y = 0; $y < $size - 1; $y++) {
            for ($x = 0; $x < $size - 1; $x++) {
                $c = $this->modules[$y][$x];
                if ($c === $this->modules[$y][$x + 1]
                    && $c === $this->modules[$y + 1][$x]
                    && $c === $this->modules[$y + 1][$x + 1]) {
                    $result += self::PENALTY_N2;
                }
            }
        }

        // Dark/light balance.
        $dark = 0;
        for ($y = 0; $y < $size; $y++) {
            foreach ($this->modules[$y] as $m) {
                if ($m) {
                    $dark++;
                }
            }
        }
        $total = $size * $size;
        $k = intdiv(abs($dark * 20 - $total * 10) + $total - 1, $total) - 1;
        $result += $k * self::PENALTY_N4;

        return $result;
    }

    /**
     * @param array<int,int> $history
     */
    private function finderPenaltyAddHistory(int $runLen, array &$history): void
    {
        if ($history[0] === 0) {
            $runLen += $this->size; // light border before the first run
        }
        array_unshift($history, $runLen);
        array_pop($history);
    }

    /**
     * @param array<int,int> $history
     */
    private function finderPenaltyCountPatterns(array $history): int
    {
        $n = $history[1];
        $core = $n > 0
            && $history[2] === $n
            && $history[3] === $n * 3
            && $history[4] === $n
            && $history[5] === $n;

        return ($core && $history[0] >= $n * 4 && $history[6] >= $n ? 1 : 0)
            + ($core && $history[6] >= $n * 4 && $history[0] >= $n ? 1 : 0);
    }

    /**
     * @param array<int,int> $history
     */
    private function finderPenaltyTerminate(bool $runColor, int $runLen, array $history): int
    {
        if ($runColor) {
            $this->finderPenaltyAddHistory($runLen, $history);
            $runLen = 0;
        }
        $runLen += $this->size; // light border after the last run
        $this->finderPenaltyAddHistory($runLen, $history);

        return $this->finderPenaltyCountPatterns($history);
    }

    private function setFunctionModule(int $x, int $y, bool $isDark): void
    {
        $this->modules[$y][$x] = $isDark;
        $this->isFunction[$y][$x] = true;
    }

    private function getBit(int $value, int $index): bool
    {
        return (($value >> $index) & 1) !== 0;
    }
}
