<?php
namespace Ntuple\Synctree\Util\Support\Decimal;

class DecimalSupport
{
    private const DEFAULT_SCALE = 16;
    private const CLASSIC_DECIMAL_NUMBER_REGEXP = '/^([+\-]?)0*(([1-9][0-9]*|[0-9])(\.[0-9]+)?)$/';
    private const EXP_NOTATION_NUMBER_REGEXP = '/^ (?P<sign> [+\-]?) 0*(?P<mantissa> [0-9](?P<decimals> \.[0-9]+)?) [eE] (?P<expSign> [+\-]?)(?P<exp> \d+)$/x';
    private const EXP_NUM_GROUPS_NUMBER_REGEXP = '/^ (?P<int> \d*) (?: \. (?P<dec> \d+) ) E (?P<sign>[\+\-]) (?P<exp>\d+) $/x';

    protected $value;
    private $scale;

    /**
     * DecimalSupport constructor.
     * @param string $value
     * @param int $scale
     */
    private function __construct(string $value, int $scale)
    {
        $this->value = $value;
        $this->scale = $scale;
    }

    private function __clone()
    {
    }

    /**
     * Decimal "constructor".
     *
     * @param mixed $value
     * @param int|null $scale
     * @return DecimalSupport
     */
    public static function create($value, int $scale = null): DecimalSupport
    {
        if (is_int($value)) {
            return self::fromInteger($value);
        }

        if (is_float($value)) {
            return self::fromFloat($value, $scale);
        }

        if (is_string($value)) {
            return self::fromString($value, $scale);
        }

        if ($value instanceof self) {
            return self::fromDecimal($value, $scale);
        }

        throw new DecimalException('invalid value');
    }

    /**
     * @param int $intValue
     * @return DecimalSupport
     */
    public static function fromInteger(int $intValue): DecimalSupport
    {
        self::paramsValidation($intValue, null);

        return new static((string)$intValue, 0);
    }

    /**
     * @param float $fltValue
     * @param int|null $scale
     * @return DecimalSupport
     */
    public static function fromFloat(float $fltValue, int $scale = null): DecimalSupport
    {
        self::paramsValidation($fltValue, $scale);

        if (is_infinite($fltValue)) {
            throw new DecimalException('value must be a finite number');
        }

        if (is_nan($fltValue)) {
            throw new DecimalException('value can\'t be NaN');
        }

        $strValue = (string) $fltValue;
        $hasPoint = (false !== strpos($strValue, '.'));

        if (preg_match(self::EXP_NUM_GROUPS_NUMBER_REGEXP, $strValue, $capture)) {
            if (null === $scale) {
                $scale = ('-' === $capture['sign'])
                    ? $capture['exp'] + strlen($capture['dec'])
                    : self::DEFAULT_SCALE;
            }
            $strValue = number_format($fltValue, $scale, '.', '');
        } else {
            $naturalScale = (
                strlen((string)fmod($fltValue, 1.0)) - 2 - (($fltValue < 0) ? 1 : 0) + (!$hasPoint ? 1 : 0)
            );

            if (null === $scale) {
                $scale = $naturalScale;
            } else {
                $strValue .= ($hasPoint ? '' : '.') . str_pad('', $scale - $naturalScale, '0');
            }
        }

        return new static($strValue, $scale);
    }

    /**
     * @param string $strValue
     * @param int|null $scale
     * @return DecimalSupport
     */
    public static function fromString(string $strValue, int $scale = null): DecimalSupport
    {
        self::paramsValidation($strValue, $scale);

        if (preg_match(self::CLASSIC_DECIMAL_NUMBER_REGEXP, $strValue, $captures) === 1) {
            // Now it's time to strip leading zeros in order to normalize inner values
            $value = self::normalizeSign($captures[1]) . $captures[2];
            $minScale = isset($captures[4]) ? max(0, strlen($captures[4]) - 1) : 0;
        } elseif (preg_match(self::EXP_NOTATION_NUMBER_REGEXP, $strValue, $captures) === 1) {
            [$minScale, $value] = self::fromExpNotationString(
                $scale,
                $captures['sign'],
                $captures['mantissa'],
                strlen($captures['mantissa']) - 1,
                $captures['expSign'],
                (int) $captures['exp']
            );
        } else {
            throw new DecimalException('value must be a number');
        }

        $scale = $scale ?? $minScale;
        if ($scale < $minScale) {
            $value = self::innerRound($value, $scale);
        } elseif ($minScale < $scale) {
            $hasPoint = (false !== strpos($value, '.'));
            $value .= ($hasPoint ? '' : '.') . str_pad('', $scale - $minScale, '0');
        }

        return new static($value, $scale);
    }

    /**
     * Constructs a new Decimal object based on a previous one,
     * but changing it's $scale property.
     *
     * @param  DecimalSupport  $decValue
     * @param  null|int $scale
     * @return DecimalSupport
     */
    public static function fromDecimal(DecimalSupport $decValue, int $scale = null): DecimalSupport
    {
        self::paramsValidation($decValue, $scale);

        // This block protect us from unnecessary additional instances
        if ($scale === null || $scale >= $decValue->scale) {
            return $decValue;
        }

        return new static(
            self::innerRound($decValue->value, $scale),
            $scale
        );
    }

    /**
     * Adds two Decimal objects
     * @param  DecimalSupport  $b
     * @param  null|int $scale
     * @return DecimalSupport
     */
    public function add(DecimalSupport $b, int $scale = null): DecimalSupport
    {
        self::paramsValidation($b, $scale);

        return self::fromString(
            bcadd($this->value, $b->value, max($this->scale, $b->scale)),
            $scale
        );
    }

    /**
     * Subtracts two BigNumber objects
     * @param DecimalSupport $b
     * @param int|null $scale
     * @return DecimalSupport
     */
    public function sub(DecimalSupport $b, int $scale = null): DecimalSupport
    {
        self::paramsValidation($b, $scale);

        return self::fromString(
            bcsub($this->value, $b->value, max($this->scale, $b->scale)),
            $scale
        );
    }

    /**
     * Multiplies two BigNumber objects
     * @param DecimalSupport $b
     * @param int|null $scale
     * @return DecimalSupport
     */
    public function mul(DecimalSupport $b, int $scale = null): DecimalSupport
    {
        self::paramsValidation($b, $scale);

        if ($b->isZero()) {
            return DecimalConstants::Zero();
        }

        return self::fromString(
            bcmul($this->value, $b->value, $this->scale + $b->scale),
            $scale
        );
    }

    /**
     * Divides the object by $b .
     * Warning: div with $scale == 0 is not the same as
     *          integer division because it rounds the
     *          last digit in order to minimize the error.
     *
     * @param DecimalSupport $b
     * @param int|null $scale
     * @return DecimalSupport
     */
    public function div(DecimalSupport $b, int $scale = null): DecimalSupport
    {
        self::paramsValidation($b, $scale);

        if ($b->isZero()) {
            throw new DecimalException('Division by zero is not allowed');
        }

        if ($this->isZero()) {
            return DecimalConstants::Zero();
        }

        if (null !== $scale) {
            $divscale = $scale;
        } else {
            // $divscale is calculated in order to maintain a reasonable precision
            $thisAbs = $this->abs();
            $bAbs = $b->abs();

            $log10Result =
                self::innerLog10($thisAbs->value, $thisAbs->scale, 1) -
                self::innerLog10($bAbs->value, $bAbs->scale, 1);

            $divscale = (int)max(
                $this->scale + $b->scale,
                max(
                    self::countSignificativeDigits($this, $thisAbs),
                    self::countSignificativeDigits($b, $bAbs)
                ) - max(ceil($log10Result), 0),
                ceil(-$log10Result) + 1
            );
        }

        return self::fromString(
            bcdiv($this->value, $b->value, $divscale+1), $divscale
        );
    }

    /**
     * Returns the square root of this object
     * @param int|null $scale
     * @return DecimalSupport
     */
    public function sqrt(int $scale = null): DecimalSupport
    {
        if ($this->isNegative()) {
            throw new DecimalException('Decimal can\'t handle square roots of negative numbers (it\'s only for real numbers).');
        }

        if ($this->isZero()) {
            return DecimalConstants::Zero();
        }

        $sqrtScale = ($scale ?? $this->scale);

        return self::fromString(
            bcsqrt($this->value, $sqrtScale+1),
            $sqrtScale
        );
    }

    /**
     * Powers this value to $b
     *
     * @param DecimalSupport $b exponent
     * @param int|null $scale
     * @return DecimalSupport
     */
    public function pow(DecimalSupport $b, int $scale = null): DecimalSupport
    {
        if ($this->isZero()) {
            if ($b->isPositive()) {
                return self::fromDecimal($this, $scale);
            }
            throw new DecimalException('zero can\'t be powered to zero or negative numbers.');
        }

        if ($b->isZero()) {
            return DecimalConstants::One();
        }

        if ($b->isNegative()) {
            return DecimalConstants::One()->div(
                $this->pow($b->additiveInverse(), max($scale, self::DEFAULT_SCALE)),
                max($scale, self::DEFAULT_SCALE)
            );
        }

        if (0 === $b->scale) {
            $powScale = max($this->scale, $b->scale, $scale ?? 0);

            return self::fromString(
                bcpow($this->value, $b->value, $powScale+1),
                $powScale
            );
        }

        if ($this->isPositive()) {
            $powScale = max($this->scale, $b->scale, $scale ?? 0);

            $truncatedB = bcadd($b->value, '0', 0);
            $remainingB = bcsub($b->value, $truncatedB, $b->scale);

            $first_pow_approx = bcpow($this->value, $truncatedB, $powScale+1);
            $intermediate_root = self::innerPowWithLittleExponent(
                $this->value,
                $remainingB,
                $b->scale,
                $powScale+1
            );

            return self::fromString(
                bcmul($first_pow_approx, $intermediate_root, $powScale+1),
                $powScale
            );
        }

        if (!$b->isInteger()) {
            throw new DecimalException("Usually negative numbers can't be powered to non integer numbers. " . "The cases where is possible are not implemented.");
        }

        return (preg_match('/^[+\-]?[0-9]*[02468](\.0+)?$/', $b->value, $captures) === 1)
            ? $this->additiveInverse()->pow($b, $scale)                      // $b is an even number
            : $this->additiveInverse()->pow($b, $scale)->additiveInverse();  // $b is an odd number
    }

    /**
     * Returns the object's logarithm in base 10
     * @param int|null $scale
     * @return DecimalSupport
     */
    public function log10(int $scale = null): DecimalSupport
    {
        if ($this->isNegative()) {
            throw new DecimalException('Decimal can\'t handle logarithms of negative numbers (it\'s only for real numbers).');
        }

        if ($this->isZero()) {
            throw new DecimalException('Decimal can\'t represent infinite numbers.');
        }

        return self::fromString(
            self::innerLog10($this->value, $this->scale, $scale !== null ? $scale+1 : $this->scale+1),
            $scale
        );
    }

    public function isZero(int $scale = null): bool
    {
        $cmpScale = $scale ?? $this->scale;

        return (bccomp(self::innerRound($this->value, $cmpScale), '0', $cmpScale) === 0);
    }

    public function isPositive(): bool
    {
        return ($this->value[0] !== '-' && !$this->isZero());
    }

    public function isNegative(): bool
    {
        return ($this->value[0] === '-');
    }

    public function isInteger(): bool
    {
        return (preg_match('/^[+\-]?[0-9]+(\.0+)?$/', $this->value, $captures) === 1);
    }

    /**
     * Equality comparison between this object and $b
     * @param DecimalSupport $b
     * @param int|null $scale
     * @return boolean
     */
    public function equals(DecimalSupport $b, int $scale = null): bool
    {
        self::paramsValidation($b, $scale);

        if ($this === $b) {
            return true;
        }

        $cmpScale = $scale ?? max($this->scale, $b->scale);

        return (
            bccomp(
                self::innerRound($this->value, $cmpScale),
                self::innerRound($b->value, $cmpScale),
                $cmpScale
            ) === 0
        );
    }

    /**
     * $this > $b : returns 1 , $this < $b : returns -1 , $this == $b : returns 0
     *
     * @param DecimalSupport $b
     * @param int|null $scale
     * @return integer
     */
    public function comp(DecimalSupport $b, int $scale = null): int
    {
        self::paramsValidation($b, $scale);

        if ($this === $b) {
            return 0;
        }

        $cmpScale = $scale ?? max($this->scale, $b->scale);

        return bccomp(
            self::innerRound($this->value, $cmpScale),
            self::innerRound($b->value, $cmpScale),
            $cmpScale
        );
    }

    /**
     * Returns true if $this > $b, otherwise false
     *
     * @param DecimalSupport $b
     * @param int|null $scale
     * @return bool
     */
    public function isGreaterThan(DecimalSupport $b, int $scale = null): bool
    {
        return $this->comp($b, $scale) === 1;
    }

    /**
     * Returns true if $this >= $b
     *
     * @param DecimalSupport $b
     * @param int|null $scale
     * @return bool
     */
    public function isGreaterOrEqualTo(DecimalSupport $b, int $scale = null): bool
    {
        $comparisonResult = $this->comp($b, $scale);

        return $comparisonResult === 1 || $comparisonResult === 0;
    }

    /**
     * Returns true if $this < $b, otherwise false
     *
     * @param DecimalSupport $b
     * @param int|null $scale
     * @return bool
     */
    public function isLessThan(DecimalSupport $b, int $scale = null): bool
    {
        return $this->comp($b, $scale) === -1;
    }

    /**
     * Returns true if $this <= $b, otherwise false
     *
     * @param DecimalSupport $b
     * @param int|null $scale
     * @return bool
     */
    public function isLessOrEqualTo(DecimalSupport $b, int $scale = null): bool
    {
        $comparisonResult = $this->comp($b, $scale);

        return $comparisonResult === -1 || $comparisonResult === 0;
    }

    /**
     * Returns the element's additive inverse.
     * @return DecimalSupport
     */
    public function additiveInverse(): DecimalSupport
    {
        if ($this->isZero()) {
            return $this;
        }

        if ($this->isNegative()) {
            $value = substr($this->value, 1);
        } else { // if ($this->isPositive()) {
            $value = '-' . $this->value;
        }

        return new static($value, $this->scale);
    }

    /**
     * "Rounds" the Decimal to have at most $scale digits after the point
     * @param  integer $scale
     * @return DecimalSupport
     */
    public function round(int $scale = 0): DecimalSupport
    {
        if ($scale >= $this->scale) {
            return $this;
        }

        return self::fromString(self::innerRound($this->value, $scale));
    }

    /**
     * "Ceils" the Decimal to have at most $scale digits after the point
     * @param  integer $scale
     * @return DecimalSupport
     */
    public function ceil($scale = 0): DecimalSupport
    {
        if ($scale >= $this->scale) {
            return $this;
        }

        if ($this->isNegative()) {
            return self::fromString(bcadd($this->value, '0', $scale));
        }

        return $this->innerTruncate($scale);
    }

    private function innerTruncate(int $scale = 0, bool $ceil = true): DecimalSupport
    {
        $rounded = bcadd($this->value, '0', $scale);

        $rlen = strlen($rounded);
        $tlen = strlen($this->value);

        $mustTruncate = false;
        for ($i=$tlen-1; $i >= $rlen; $i--) {
            if ((int)$this->value[$i] > 0) {
                $mustTruncate = true;
                break;
            }
        }

        if ($mustTruncate) {
            $rounded = $ceil
                ? bcadd($rounded, bcpow('10', (string)-$scale, $scale), $scale)
                : bcsub($rounded, bcpow('10', (string)-$scale, $scale), $scale);
        }

        return self::fromString($rounded, $scale);
    }

    /**
     * "Floors" the Decimal to have at most $scale digits after the point
     * @param  integer $scale
     * @return DecimalSupport
     */
    public function floor(int $scale = 0): DecimalSupport
    {
        if ($scale >= $this->scale) {
            return $this;
        }

        if ($this->isNegative()) {
            return $this->innerTruncate($scale, false);
        }

        return self::fromString(bcadd($this->value, '0', $scale));
    }

    /**
     * Returns the absolute value (always a positive number)
     * @return DecimalSupport
     */
    public function abs(): DecimalSupport
    {
        return ($this->isZero() || $this->isPositive())
            ? $this
            : $this->additiveInverse();
    }

    /**
     * Calculate modulo with a decimal
     * @param DecimalSupport $d
     * @param int|null $scale
     * @return $this % $d
     */
    public function mod(DecimalSupport $d, int $scale = null): DecimalSupport
    {
        $div = $this->div($d, 1)->floor();
        return $this->sub($div->mul($d), $scale);
    }

    /**
     * Calculates the sine of this method with the highest possible accuracy
     * Note that accuracy is limited by the accuracy of predefined PI;
     *
     * @param int|null $scale
     * @return DecimalSupport sin($this)
     */
    public function sin(int $scale = null): DecimalSupport
    {
        // First normalise the number in the [0, 2PI] domain
        $x = $this->mod(DecimalConstants::PI()->mul(self::fromString("2")));

        // PI has only 32 significant numbers
        $scale = $scale ?? 32;

        return self::factorialSerie(
            $x,
            DecimalConstants::zero(),
            function ($i) {
                return ($i % 2 === 1) ? (
                ($i % 4 === 1) ? DecimalConstants::one() : DecimalConstants::negativeOne()
                ) : DecimalConstants::zero();
            },
            $scale
        );
    }

    /**
     * Calculates the cosecant of this with the highest possible accuracy
     * Note that accuracy is limited by the accuracy of predefined PI;
     *
     * @param int|null $scale
     * @return DecimalSupport
     */
    public function cosec(int $scale = null): DecimalSupport
    {
        $sin = $this->sin($scale + 2);
        if ($sin->isZero()) {
            throw new DecimalException('The cosecant of this \'angle\' is undefined.');
        }

        return DecimalConstants::one()->div($sin)->round($scale);
    }

    /**
     * Calculates the cosine of this method with the highest possible accuracy
     * Note that accuracy is limited by the accuracy of predefined PI;
     *
     * @param int|null $scale
     * @return DecimalSupport cos($this)
     */
    public function cos(int $scale = null): DecimalSupport
    {
        // First normalise the number in the [0, 2PI] domain
        $x = $this->mod(DecimalConstants::PI()->mul(self::fromString("2")));

        // PI has only 32 significant numbers
        $scale = $scale ?? 32;

        return self::factorialSerie(
            $x,
            DecimalConstants::one(),
            function ($i) {
                return ($i % 2 === 0) ? (
                ($i % 4 === 0) ? DecimalConstants::one() : DecimalConstants::negativeOne()
                ) : DecimalConstants::zero();
            },
            $scale
        );
    }

    /**
     * Calculates the secant of this with the highest possible accuracy
     * Note that accuracy is limited by the accuracy of predefined PI;
     *
     * @param int|null $scale
     * @return DecimalSupport
     */
    public function sec(int $scale = null): DecimalSupport
    {
        $cos = $this->cos($scale + 2);
        if ($cos->isZero()) {
            throw new DecimalException('The secant of this \'angle\' is undefined.');
        }

        return DecimalConstants::one()->div($cos)->round($scale);
    }

    /**
     *    Calculates the arcsine of this with the highest possible accuracy
     *
     * @param int|null $scale
     * @return DecimalSupport
     */
    public function arcsin(int $scale = null): DecimalSupport
    {
        if($this->comp(DecimalConstants::one(), $scale + 2) === 1 || $this->comp(DecimalConstants::negativeOne(), $scale + 2) === -1) {
            throw new DecimalException('The arcsin of this number is undefined.');
        }

        if ($this->round($scale)->isZero()) {
            return DecimalConstants::zero();
        }
        if ($this->round($scale)->equals(DecimalConstants::one())) {
            return DecimalConstants::pi()->div(self::fromInteger(2))->round($scale);
        }
        if ($this->round($scale)->equals(DecimalConstants::negativeOne())) {
            return DecimalConstants::pi()->div(self::fromInteger(-2))->round($scale);
        }

        $scale = $scale ?? 32;

        return self::powerSerie(
            $this,
            DecimalConstants::zero(),
            $scale
        );
    }

    /**
     *    Calculates the arccosine of this with the highest possible accuracy
     *
     * @param int|null $scale
     * @return DecimalSupport
     */
    public function arccos(int $scale = null): DecimalSupport
    {
        if($this->comp(DecimalConstants::one(), $scale + 2) === 1 || $this->comp(DecimalConstants::negativeOne(), $scale + 2) === -1) {
            throw new DecimalException('The arccos of this number is undefined.');
        }

        $piOverTwo = DecimalConstants::pi()->div(self::fromInteger(2), $scale + 2)->round($scale);

        if ($this->round($scale)->isZero()) {
            return $piOverTwo;
        }
        if ($this->round($scale)->equals(DecimalConstants::one())) {
            return DecimalConstants::zero();
        }
        if ($this->round($scale)->equals(DecimalConstants::negativeOne())) {
            return DecimalConstants::pi()->round($scale);
        }

        $scale = $scale ?? 32;

        return $piOverTwo->sub(
            self::powerSerie(
                $this,
                DecimalConstants::zero(),
                $scale
            )
        )->round($scale);
    }

    /**
     *    Calculates the arctangente of this with the highest possible accuracy
     *
     * @param int|null $scale
     * @return DecimalSupport
     */
    public function arctan(int $scale = null): DecimalSupport
    {
        $piOverFour = DecimalConstants::pi()->div(self::fromInteger(4), $scale + 2)->round($scale);

        if ($this->round($scale)->isZero()) {
            return DecimalConstants::zero();
        }
        if ($this->round($scale)->equals(DecimalConstants::one())) {
            return $piOverFour;
        }
        if ($this->round($scale)->equals(DecimalConstants::negativeOne())) {
            return DecimalConstants::negativeOne()->mul($piOverFour);
        }

        $scale = $scale ?? 32;

        return self::simplePowerSerie(
            $this,
            DecimalConstants::zero(),
            $scale + 2
        )->round($scale);
    }

    /**
     * Calculates the arccotangente of this with the highest possible accuracy
     *
     * @param int|null $scale
     * @return DecimalSupport
     */
    public function arccot(int $scale = null): DecimalSupport
    {
        $scale = $scale ?? 32;

        $piOverTwo = DecimalConstants::pi()->div(self::fromInteger(2), $scale + 2);
        if ($this->round($scale)->isZero()) {
            return $piOverTwo->round($scale);
        }

        $piOverFour = DecimalConstants::pi()->div(self::fromInteger(4), $scale + 2);
        if ($this->round($scale)->equals(DecimalConstants::one())) {
            return $piOverFour->round($scale);
        }
        if ($this->round($scale)->equals(DecimalConstants::negativeOne())) {
            return DecimalConstants::negativeOne()->mul($piOverFour, $scale + 2)->round($scale);
        }

        return $piOverTwo->sub(
            self::simplePowerSerie(
                $this,
                DecimalConstants::zero(),
                $scale + 2
            )
        )->round($scale);
    }

    /**
     * Calculates the arcsecant of this with the highest possible accuracy
     *
     * @param int|null $scale
     * @return DecimalSupport
     */
    public function arcsec(int $scale = null): DecimalSupport
    {
        if($this->comp(DecimalConstants::one(), $scale + 2) === -1 && $this->comp(DecimalConstants::negativeOne(), $scale + 2) === 1) {
            throw new DecimalException('The arcsecant of this number is undefined.');
        }

        $piOverTwo = DecimalConstants::pi()->div(self::fromInteger(2), $scale + 2)->round($scale);

        if ($this->round($scale)->equals(DecimalConstants::one())) {
            return DecimalConstants::zero();
        }
        if ($this->round($scale)->equals(DecimalConstants::negativeOne())) {
            return DecimalConstants::pi()->round($scale);
        }

        $scale = $scale ?? 32;

        return $piOverTwo->sub(
            self::powerSerie(
                DecimalConstants::one()->div($this, $scale + 2),
                DecimalConstants::zero(),
                $scale + 2
            )
        )->round($scale);
    }

    /**
     * Calculates the arccosecant of this with the highest possible accuracy
     *
     * @param int|null $scale
     * @return DecimalSupport
     */
    public function arccsc(int $scale = null): DecimalSupport
    {
        if($this->comp(DecimalConstants::one(), $scale + 2) === -1 && $this->comp(DecimalConstants::negativeOne(), $scale + 2) === 1) {
            throw new DecimalException('The arccosecant of this number is undefined.');
        }

        $scale = $scale ?? 32;

        if ($this->round($scale)->equals(DecimalConstants::one())) {
            return DecimalConstants::pi()->div(self::fromInteger(2), $scale + 2)->round($scale);
        }
        if ($this->round($scale)->equals(DecimalConstants::negativeOne())) {
            return DecimalConstants::pi()->div(self::fromInteger(-2), $scale + 2)->round($scale);
        }

        return self::powerSerie(
            DecimalConstants::one()->div($this, $scale + 2),
            DecimalConstants::zero(),
            $scale + 2
        )->round($scale);
    }

    /**
     * Returns exp($this), said in other words: e^$this .
     *
     * @param int|null $scale
     * @return DecimalSupport
     */
    public function exp(int $scale = null): DecimalSupport
    {
        if ($this->isZero()) {
            return DecimalConstants::one();
        }

        $scale = $scale ?? max(
                $this->scale,
                (int)($this->isNegative() ? self::innerLog10($this->value, $this->scale, 0) : self::DEFAULT_SCALE)
            );

        return self::factorialSerie(
            $this, DecimalConstants::one(), function () { return DecimalConstants::one(); }, $scale
        );
    }

    /**
     * Internal method used to compute sin, cos and exp
     *
     * @param DecimalSupport $x
     * @param DecimalSupport $firstTerm
     * @param callable $generalTerm
     * @param $scale
     * @return DecimalSupport
     */
    private static function factorialSerie (DecimalSupport $x, DecimalSupport $firstTerm, callable $generalTerm, int $scale): DecimalSupport
    {
        $approx = $firstTerm;
        $change = DecimalConstants::One();

        $faculty = DecimalConstants::One();    // Calculates the faculty under the sign
        $xPowerN = DecimalConstants::One();    // Calculates x^n

        for ($i = 1; !$change->floor($scale+1)->isZero(); $i++) {
            // update x^n and n! for this walkthrough
            $xPowerN = $xPowerN->mul($x);
            $faculty = $faculty->mul(self::fromInteger($i));

            /** @var DecimalSupport $multiplier */
            $multiplier = $generalTerm($i);

            if (!$multiplier->isZero()) {
                $change = $multiplier->mul($xPowerN, $scale + 2)->div($faculty, $scale + 2);
                $approx = $approx->add($change, $scale + 2);
            }
        }

        return $approx->round($scale);
    }


    /**
     * Internal method used to compute arcsine and arcosine
     *
     * @param DecimalSupport $x
     * @param DecimalSupport $firstTerm
     * @param $scale
     * @return DecimalSupport
     */
    private static function powerSerie (DecimalSupport $x, DecimalSupport $firstTerm, int $scale): DecimalSupport
    {
        $approx = $firstTerm;
        $change = DecimalConstants::One();

        $xPowerN = DecimalConstants::One();     // Calculates x^n
//        $factorN = DecimalConstants::One();      // Calculates a_n

        $numerator = DecimalConstants::one();
        $denominator = DecimalConstants::one();

        for ($i = 1; !$change->floor($scale + 2)->isZero(); $i++) {
            $xPowerN = $xPowerN->mul($x);

            if ($i % 2 === 0) {
                $factorN = DecimalConstants::zero();
            } elseif ($i === 1) {
                $factorN = DecimalConstants::one();
            } else {
                $incrementNum = self::fromInteger($i - 2);
                $numerator = $numerator->mul($incrementNum, $scale +2);

                $incrementDen = self::fromInteger($i - 1);
                $increment = self::fromInteger($i);
                $denominator = $denominator
                    ->div($incrementNum, $scale +2)
                    ->mul($incrementDen, $scale +2)
                    ->mul($increment, $scale +2);

                $factorN = $numerator->div($denominator, $scale + 2);
            }

            if (!$factorN->isZero()) {
                $change = $factorN->mul($xPowerN, $scale + 2);
                $approx = $approx->add($change, $scale + 2);
            }
        }

        return $approx->round($scale);
    }

    /**
     * Internal method used to compute arctan and arccotan
     *
     * @param DecimalSupport $x
     * @param DecimalSupport $firstTerm
     * @param $scale
     * @return DecimalSupport
     */
    private static function simplePowerSerie (DecimalSupport $x, DecimalSupport $firstTerm, int $scale): DecimalSupport
    {
        $approx = $firstTerm;
        $change = DecimalConstants::One();

        $xPowerN = DecimalConstants::One();     // Calculates x^n
//        $sign = DecimalConstants::One();      // Calculates a_n

        for ($i = 1; !$change->floor($scale + 2)->isZero(); $i++) {
            $xPowerN = $xPowerN->mul($x);

            if ($i % 2 === 0) {
                $factorN = DecimalConstants::zero();
            } else {
                if ($i % 4 === 1) {
                    $factorN = DecimalConstants::one()->div(self::fromInteger($i), $scale + 2);
                } else {
                    $factorN = DecimalConstants::negativeOne()->div(self::fromInteger($i), $scale + 2);
                }
            }

            if (!$factorN->isZero()) {
                $change = $factorN->mul($xPowerN, $scale + 2);
                $approx = $approx->add($change, $scale + 2);
            }
        }

        return $approx->round($scale);
    }

    /**
     * Calculates the tangent of this method with the highest possible accuracy
     * Note that accuracy is limited by the accuracy of predefined PI;
     *
     * @param int|null $scale
     * @return DecimalSupport tan($this)
     */
    public function tan(int $scale = null): DecimalSupport
    {
        $cos = $this->cos($scale + 2);
        if ($cos->isZero()) {
            throw new DecimalException('The tangent of this \'angle\' is undefined.');
        }

        return $this->sin($scale + 2)->div($cos)->round($scale);
    }

    /**
     * Calculates the cotangent of this method with the highest possible accuracy
     * Note that accuracy is limited by the accuracy of predefined PI;
     *
     * @param int|null $scale
     * @return DecimalSupport cotan($this)
     */
    public function cotan(int $scale = null): DecimalSupport
    {
        $sin = $this->sin($scale + 2);
        if ($sin->isZero()) {
            throw new DecimalException('The cotangent of this \'angle\' is undefined.');
        }

        return $this->cos($scale + 2)->div($sin)->round($scale);
    }

    /**
     * Indicates if the passed parameter has the same sign as the method's bound object.
     *
     * @param DecimalSupport $b
     * @return bool
     */
    public function hasSameSign(DecimalSupport $b): bool
    {
        return ($this->isPositive() && $b->isPositive()) || ($this->isNegative() && $b->isNegative());
    }

    public function asFloat(): float
    {
        return (float)$this->value;
    }

    public function asInteger(): int
    {
        return (int)$this->value;
    }

    /**
     * WARNING: use with caution! Return the inner representation of the class.
     */
    public function innerValue(): string
    {
        return $this->value;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->value;
    }

    private static function fromExpNotationString(int $scale, string $sign, string $mantissa, int $nDecimals, string $expSign, int $expVal): array
    {
        $mantissaScale = max($nDecimals, 0);

        if (self::normalizeSign($expSign) === '') {
            $minScale = max($mantissaScale - $expVal, 0);
            $tmpMultiplier = bcpow('10', (string)$expVal);
        } else {
            $minScale = $mantissaScale + $expVal;
            $tmpMultiplier = bcpow('10', (string)-$expVal, $expVal);
        }

        $value = (
            self::normalizeSign($sign) .
            bcmul(
                $mantissa,
                $tmpMultiplier,
                max($minScale, $scale)
            )
        );

        return [$minScale, $value];
    }

    /**
     * "Rounds" the decimal string to have at most $scale digits after the point
     *
     * @param  string $value
     * @param  int    $scale
     * @return string
     */
    private static function innerRound(string $value, int $scale = 0): string
    {
        $rounded = bcadd($value, '0', $scale);

        $diffDigit = bcsub($value, $rounded, $scale+1);
        $diffDigit = (int)$diffDigit[strlen($diffDigit)-1];

        if ($diffDigit >= 5) {
            $rounded = ($diffDigit >= 5 && $value[0] !== '-')
                ? bcadd($rounded, bcpow('10', (string)-$scale, $scale), $scale)
                : bcsub($rounded, bcpow('10', (string)-$scale, $scale), $scale);
        }

        return $rounded;
    }

    /**
     * Calculates the logarithm (in base 10) of $value
     *
     * @param  string $value     The number we want to calculate its logarithm (only positive numbers)
     * @param  int    $in_scale  Expected scale used by $value (only positive numbers)
     * @param  int    $out_scale Scale used by the return value (only positive numbers)
     * @return string
     */
    private static function innerLog10(string $value, int $in_scale, int $out_scale): string
    {
        $value_len = strlen($value);

        $cmp = bccomp($value, '1', $in_scale);

        switch ($cmp) {
            case 1:
                $valueLog10Approx = $value_len - ($in_scale > 0 ? ($in_scale+2) : 1);
                $valueLog10Approx = max(0, $valueLog10Approx);

                return bcadd(
                    (string)$valueLog10Approx,
                    (string)log10((float)bcdiv(
                        $value,
                        bcpow('10', (string)$valueLog10Approx),
                        min($value_len, $out_scale)
                    )),
                    $out_scale
                );
            case -1:
                preg_match('/^0*\.(0*)[1-9][0-9]*$/', $value, $captures);
                $valueLog10Approx = -strlen($captures[1])-1;

                return bcadd(
                    (string)$valueLog10Approx,
                    (string)log10((float)bcmul(
                        $value,
                        bcpow('10', (string)-$valueLog10Approx),
                        $in_scale + $valueLog10Approx
                    )),
                    $out_scale
                );
            default: // case 0:
                return '0';
        }
    }

    /**
     * Returns $base^$exponent
     *
     * @param  string $base
     * @param  string $exponent   0 < $exponent < 1
     * @param  int    $expScale Number of $exponent's significative digits
     * @param  int    $outScale Number of significative digits that we want to compute
     * @return string
     */
    private static function innerPowWithLittleExponent(string $base, string $exponent, int $expScale, int $outScale): string
    {
        $innerScale = (int)ceil($expScale * log(10) / log(2)) + 1;

        $resultA = '1';
        $resultB = '0';

        $actualIndex = 0;
        $exponentRemaining = $exponent;

        while (bccomp($resultA, $resultB, $outScale) !== 0 && bccomp($exponentRemaining, '0', $innerScale) !== 0) {
            $resultB = $resultA;
            $indexInfo = self::computeSquareIndex($exponentRemaining, $actualIndex, $expScale, $innerScale);
            $exponentRemaining = $indexInfo[1];
            $resultA = bcmul(
                $resultA,
                self::compute2NRoot($base, $indexInfo[0], 2*($outScale+1)),
                2*($outScale+1)
            );
        }

        return self::innerRound($resultA, $outScale);
    }

    /**
     * Auxiliar method. It helps us to decompose the exponent into many summands.
     *
     * @param  string $exponentRemaining
     * @param  int    $actualIndex
     * @param  int    $expScale           Number of $exponent's significative digits
     * @param  int    $innerScale         ceil($exp_scale*log(10)/log(2))+1;
     * @return array
     */
    private static function computeSquareIndex(string $exponentRemaining, int $actualIndex, int $expScale, int $innerScale): array
    {
        $actual_rt = bcpow('0.5', (string)$actualIndex, $expScale);
        $r = bcsub($exponentRemaining, $actual_rt, $innerScale);

        while (bccomp($r, '0', $expScale) === -1) {
            ++$actualIndex;
            $actual_rt = bcmul('0.5', $actual_rt, $innerScale);
            $r = bcsub($exponentRemaining, $actual_rt, $innerScale);
        }

        return [$actualIndex, $r];
    }

    /**
     * Auxiliar method. Computes $base^((1/2)^$index)
     *
     * @param  string  $base
     * @param  integer $index
     * @param  integer $outScale
     * @return string
     */
    private static function compute2NRoot(string $base, int $index, int $outScale): string
    {
        $result = $base;

        for ($i = 0; $i < $index; $i++) {
            $result = bcsqrt($result, ($outScale + 1) * ($index - $i) + 1);
        }

        return self::innerRound($result, $outScale);
    }

    /**
     * Validates basic constructor's arguments
     * @param  mixed    $value
     * @param  null|int  $scale
     */
    protected static function paramsValidation($value, int $scale = null)
    {
        if (null === $value) {
            throw new DecimalException('value must be a non null number');
        }

        if (null !== $scale && $scale < 0) {
            throw new DecimalException('scale must be a positive integer');
        }
    }

    /**
     * @param string $sign
     * @return string
     */
    private static function normalizeSign(string $sign): string
    {
        if ('+' === $sign) {
            return '';
        }

        return $sign;
    }

    /**
     * Counts the number of significant digits of $val.
     * Assumes a consistent internal state (without zeros at the end or the start).
     *
     * @param  DecimalSupport $val
     * @param  DecimalSupport $abs $val->abs()
     * @return int
     */
    private static function countSignificativeDigits(DecimalSupport $val, DecimalSupport $abs): int
    {
        return strlen($val->value) - (
            ($abs->comp(DecimalConstants::One()) === -1) ? 2 : max($val->scale, 1)
            ) - ($val->isNegative() ? 1 : 0);
    }
}