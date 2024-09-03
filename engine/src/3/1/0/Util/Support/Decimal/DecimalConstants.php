<?php
namespace Ntuple\Synctree\Util\Support\Decimal;

final class DecimalConstants
{
    private static $ZERO;
    private static $ONE;
    private static $NEGATIVE_ONE;
    private static $PI;
    private static $EulerMascheroni;
    private static $GoldenRatio;
    private static $SilverRatio;
    private static $LightSpeed;

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    /**
     * @return DecimalSupport
     */
    public static function zero(): DecimalSupport
    {
        if (null === self::$ZERO) {
            self::$ZERO = DecimalSupport::fromInteger(0);
        }
        return self::$ZERO;
    }

    /**
     * @return DecimalSupport
     */
    public static function one(): DecimalSupport
    {
        if (null === self::$ONE) {
            self::$ONE = DecimalSupport::fromInteger(1);
        }
        return self::$ONE;
    }

    /**
     * @return DecimalSupport
     */
    public static function negativeOne(): DecimalSupport
    {
        if (null === self::$NEGATIVE_ONE) {
            self::$NEGATIVE_ONE = DecimalSupport::fromInteger(-1);
        }
        return self::$NEGATIVE_ONE;
    }

    /**
     * Returns the Pi number.
     * @return DecimalSupport
     */
    public static function pi(): DecimalSupport
    {
        if (null === self::$PI) {
            self::$PI = DecimalSupport::fromString(
                "3.14159265358979323846264338327950"
            );
        }
        return self::$PI;
    }

    /**
     * Returns the Euler's E number.
     * @param  integer $scale
     * @return DecimalSupport
     */
    public static function e(int $scale = 32): DecimalSupport
    {
        if ($scale < 0) {
            throw new \InvalidArgumentException("\$scale must be positive.");
        }

        return self::one()->exp($scale);
    }

    /**
     * Returns the Euler-Mascheroni constant.
     * @return DecimalSupport
     */
    public static function eulerMascheroni(): DecimalSupport
    {
        if (null === self::$EulerMascheroni) {
            self::$EulerMascheroni = DecimalSupport::fromString(
                "0.57721566490153286060651209008240"
            );
        }
        return self::$EulerMascheroni;
    }

    /**
     * Returns the Golden Ration, also named Phi.
     * @return DecimalSupport
     */
    public static function goldenRatio(): DecimalSupport
    {
        if (null === self::$GoldenRatio) {
            self::$GoldenRatio = DecimalSupport::fromString(
                "1.61803398874989484820458683436564"
            );
        }
        return self::$GoldenRatio;
    }

    /**
     * Returns the Silver Ratio.
     * @return DecimalSupport
     */
    public static function silverRatio(): DecimalSupport
    {
        if (null === self::$SilverRatio) {
            self::$SilverRatio = DecimalSupport::fromString(
                "2.41421356237309504880168872420970"
            );
        }
        return self::$SilverRatio;
    }

    /**
     * Returns the Light of Speed measured in meters / second.
     * @return DecimalSupport
     */
    public static function lightSpeed(): DecimalSupport
    {
        if (null === self::$LightSpeed) {
            self::$LightSpeed = DecimalSupport::fromInteger(299792458);
        }
        return self::$LightSpeed;
    }
}