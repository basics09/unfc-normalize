<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// gitlost removed namespace stuff, renamed to UNFC_BaseNormalizer to avoid conflicts.
// gitlost use unfc_is_valid_utf8() and generated regex alternatives, added full isNormalized() check.

// namespace Symfony\Polyfill\Intl\Normalizer; // gitlost

/**
 * BaseNormalizer is the parent of the Normalizer class provided by the intl extension.
 *
 * It has been validated with Unicode 9.0.0 Normalization Conformance Test. // gitlost
 * See http://www.unicode.org/reports/tr15/ for detailed info about Unicode normalizations.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
// gitlost begin
// To test UTF-8 validity, use PCRE UTF-8 mode if available and RFC 3629 compliant, or htmlspecialchars() if RFC 3629 compliant, or as a last resort one of the following regexs.
// NOTE: The more natural regexs checking positively for validity unfortunately blow up on large (~20K) strings due to unnecessary recursion in PCRE < 8.13.
define( 'UNFC_REGEX_IS_INVALID_UTF8', // Using (*SKIP) verbs doubles the speed, but verbs only available for PCRE >= 7.3.
	'/
	  (?> [\xc2-\xdf] (?: [^\x80-\xbf] | .(*SKIP)[\x80-\xbf] | \z ) )
	| (?> [\xe0-\xef] (?: [^\x80-\xbf] | (?<= \xe0 ) [\x80-\x9f] | (?<= \xed ) [\xa0-\xbf] | .(*SKIP) (?: [^\x80-\xbf] | .(*SKIP)[\x80-\xbf] | \z ) | \z ) )
	| (?> [\xf0-\xf4] (?: [^\x80-\xbf] | (?<= \xf0 ) [\x80-\x8f] | (?<= \xf4 ) [\x90-\xbf] | .(*SKIP) (?: [^\x80-\xbf] | .(*SKIP) (?: [^\x80-\xbf] | .(*SKIP)[\x80-\xbf] | \z ) | \z ) | \z ) )
	| [\x80-\xc1\xf5-\xff] (?<= [\x00-\x7f]. | \A. )
	| [\xc0\xc1\xf5-\xff]
	/sx'
);
define( 'UNFC_REGEX_IS_INVALID_UTF8_NOVERBS',
	'/
	  (?> [\xc2-\xdf] (?: [^\x80-\xbf] | .[\x80-\xbf] | \z ) )
	| (?> [\xe0-\xef] (?: [^\x80-\xbf] | (?<= \xe0 ) [\x80-\x9f] | (?<= \xed ) [\xa0-\xbf] | . (?: [^\x80-\xbf] | .[\x80-\xbf] | \z ) | \z ) )
	| (?> [\xf0-\xf4] (?: [^\x80-\xbf] | (?<= \xf0 ) [\x80-\x8f] | (?<= \xf4 ) [\x90-\xbf] | . (?: [^\x80-\xbf] | . (?: [^\x80-\xbf] | .[\x80-\xbf] | \z ) | \z ) | \z ) )
	| [\x80-\xc1\xf5-\xff] (?<= [\x00-\x7f]. | \A. )
	| [\xc0\xc1\xf5-\xff]
	/sx'
);

// PCRE UTF-8 mode was not RFC 3629 compliant until PCRE 7.3, and then there was a compliance regression for PCRE 8.32 due to an over-enthusiastic interpretation of noncharacters.
// See https://www.ietf.org/rfc/rfc3629.txt
// See http://vcs.pcre.org/pcre/code/tags/pcre-8.32/pcre_valid_utf8.c?r1=1032&r2=1098 for the regression.
// See http://www.unicode.org/versions/corrigendum9.html for the clarification.
$_unfc_pcre_version = substr( PCRE_VERSION, 0, strspn( PCRE_VERSION, '0123456789.' ) ); // Remove any trailing date stuff.

// If PCRE UTF-8 mode is not RFC 3629 compliant or is unavailable...
if ( version_compare( $_unfc_pcre_version, '7.3', '<' ) || version_compare( $_unfc_pcre_version, '8.32', '=' ) || false === @preg_match( '//u', '' ) ) {
	// If before htmlspecialchars() RFC 3629 compliance...
	if ( version_compare( PHP_VERSION, '5.3.4', '<' ) ) {
		// If verbs unavailable...
		if ( version_compare( $_unfc_pcre_version, '7.3', '<' ) ) {
			// Typically PHP 5.2.4 only (with or without PCRE UTF-8 mode).
			function unfc_is_valid_utf8( $str ) {
				return 0 === preg_match( UNFC_REGEX_IS_INVALID_UTF8_NOVERBS, $str ); // Very slow for PHP < 7.
			}
		} else {
			// Typically when PCRE UTF-8 mode unavailable and PHP < 5.3.4, ie 5.2.5 to 5.2.17 (last), and 5.3.0 to 5.3.3.
			function unfc_is_valid_utf8( $str ) {
				return 0 === preg_match( UNFC_REGEX_IS_INVALID_UTF8, $str ); // Very slow for PHP < 7.
			}
		}
	} else {
		// Typically when PCRE UTF-8 mode unavailable and PHP >= 5.3.4; or when built against PCRE 8.32, ie PHP 5.3.24 to 5.3.29 (last), 5.4.14 to 5.4.40, and 5.5.0 to 5.5.9.
		function unfc_is_valid_utf8( $str ) {
			// See https://core.trac.wordpress.org/ticket/29717#comment:11
			return '' === $str || '' !== htmlspecialchars( $str, ENT_NOQUOTES, 'UTF-8' ); // Fast but not as fast as PCRE UTF-8 mode.
		}
	}
} else {
	// Typically all PHPs with PCRE UTF-8 mode available except 5.2.4 and those built against PCRE 8.32.
	function unfc_is_valid_utf8( $str ) {
		return 1 === preg_match( '//u', $str ); // Fastest. Original Normalizer validity check.
	}
}
unset( $_unfc_pcre_version );

if ( ! defined( 'UNFC_REGEX_ALTS_NFC_NOES' ) ) {
	require dirname( __FILE__ ) . '/unfc_regex_alts.php';
}
// (Possibly) unstable code point(s) (or end of string) preceded by a stable code point (or start of string). See http://unicode.org/reports/tr15/#Stable_Code_Points
define( 'UNFC_REGEX_NFC_SUBNORMALIZE', '/(?:\A|[\x00-\x7f]|(?:[\xc2-\xdf]|(?:[\xe0-\xef]|[\xf0-\xf4].).).)(?:(?:' . UNFC_REGEX_ALTS_NFC_NOES_MAYBES_REORDERS . ')++|\z)/' );
// gitlost end
class UNFC_BaseNormalizer // gitlost
{
    private static $C;
    private static $D;
    private static $KD;
    private static $cC;
    private static $kcCF; // gitlost Array loaded from "kcCaseFolding.php" for NFKC_CF.
    private static $RD; // gitlost Array loaded from "rawDecomposition.php".
    private static $ulenMask = array("\x00" => 1, "\x10" => 1, "\x20" => 1, "\x30" => 1, "\x40" => 1, "\x50" => 1, "\x60" => 1, "\x70" => 1, "\xC0" => 2, "\xD0" => 2, "\xE0" => 3, "\xF0" => 4); // gitlost Use for ASCII as well.
    private static $ASCII = "\x20\x65\x69\x61\x73\x6E\x74\x72\x6F\x6C\x75\x64\x5D\x5B\x63\x6D\x70\x27\x0A\x67\x7C\x68\x76\x2E\x66\x62\x2C\x3A\x3D\x2D\x71\x31\x30\x43\x32\x2A\x79\x78\x29\x28\x4C\x39\x41\x53\x2F\x50\x22\x45\x6A\x4D\x49\x6B\x33\x3E\x35\x54\x3C\x44\x34\x7D\x42\x7B\x38\x46\x77\x52\x36\x37\x55\x47\x4E\x3B\x4A\x7A\x56\x23\x48\x4F\x57\x5F\x26\x21\x4B\x3F\x58\x51\x25\x59\x5C\x09\x5A\x2B\x7E\x5E\x24\x40\x60\x7F\x00\x01\x02\x03\x04\x05\x06\x07\x08\x0B\x0C\x0D\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F";
    protected static $s = null, $form = null, $normalize = null; // gitlost Cache various info discovered in isNormalized().
    protected static $mb_overload_string = null; // gitlost Set if mbstring extension loaded with string function overload set.
	protected static $forms = array(UNFC_Normalizer::NFD => 0, UNFC_Normalizer::NFKD => 0, UNFC_Normalizer::NFC => 0, UNFC_Normalizer::NFKC => 0, UNFC_Normalizer::NFKC_CF => 0);

    public static function isNormalized($s, $form = UNFC_Normalizer::NFC)
    {
        if (!isset(self::$forms[$form])) { // gitlost
            return false;
        }
        $s = (string) $s;
        if (UNFC_Normalizer::NFKC_CF !== $form && !isset($s[strspn($s, self::$ASCII)])) {
            return true;
        }
        // gitlost begin
        if (UNFC_Normalizer::NFC === $form) {
            if (!unfc_is_valid_utf8($s)) {
                return false;
            }
            if (0 === preg_match('/[\xcc-\xf4]/', $s) || 0 === preg_match(UNFC_REGEX_NFC_NOES_MAYBES_REORDERS, $s)) { // If contains no characters that could possibly need normalizing...
                return true;
            }

            if (null === self::$D) {
                self::$D = self::getData('canonicalDecomposition');
                self::$cC = self::getData('combiningClass');
            }

            if (null === self::$C) {
                self::$C = self::getData('canonicalComposition');
            }

            if (null === self::$mb_overload_string) {
                self::$mb_overload_string = defined('MB_OVERLOAD_STRING') && (ini_get('mbstring.func_overload') & MB_OVERLOAD_STRING);
            }
            if (self::$mb_overload_string) {
                $mbEncoding = mb_internal_encoding();
                mb_internal_encoding('8bit');
            }

            // Using this method is faster where percentage of normalization candidates < 30%, and significantly faster for < 5%. It's slower where percentage > 40%.
            $normalize = preg_replace_callback(UNFC_REGEX_NFC_SUBNORMALIZE, 'UNFC_Normalizer::subnormalize', $s);

            if (self::$mb_overload_string) {
                mb_internal_encoding($mbEncoding);
            }
        } else {
            $normalize = self::normalize($s, $form); // Give true answer by doing full normalize check.
        }
        $result = ($s === $normalize);

        if ($result) {
            self::$s = self::$form = self::$normalize = null; // Clear cache.
        } else {
            // Note assuming use of "if ! isNormalized() normalize()" pattern.
            self::$s = $s; self::$form = $form; self::$normalize = $normalize; // Cache for immediate use in normalize().
        }

        return $result;
        // gitlost end
    }

    public static function normalize($s, $form = UNFC_Normalizer::NFC)
    {
        $s = (string) $s;
        // gitlost begin
        if (null !== self::$normalize) {
            if ($s === self::$s && $form === self::$form) { //  Use cache if available.
                $result = self::$normalize;
                self::$s = self::$form = self::$normalize = null; // Clear cache (try to keep memory usage to a min).
                return $result;
            }
            self::$s = self::$form = self::$normalize = null; // Clear cache (try to keep memory usage to a min).
        }

        $C = $K = $kcCF = false;
        switch ($form) {
            case UNFC_Normalizer::NONE: return unfc_is_valid_utf8($s) ? $s : false; // Note must still check validity.
            case UNFC_Normalizer::NFC: $C = true; break;
            case UNFC_Normalizer::NFD: break;
            case UNFC_Normalizer::NFKC: $C = $K = true; break;
            case UNFC_Normalizer::NFKC_CF: $C = $K = $kcCF = true; break;
            case UNFC_Normalizer::NFKD: $K = true; break;
            default: return false;
        }

        if (!unfc_is_valid_utf8($s)) {
            return false;
        }
        // gitlost end

        if ('' === $s) {
            return '';
        }

        if ($K && null === self::$KD) {
            self::$KD = self::getData('compatibilityDecomposition');
        }

        if (null === self::$D) {
            self::$D = self::getData('canonicalDecomposition');
            self::$cC = self::getData('combiningClass');
        }

        if (null === self::$mb_overload_string) { // gitlost
            self::$mb_overload_string = defined('MB_OVERLOAD_STRING') && (ini_get('mbstring.func_overload') & MB_OVERLOAD_STRING); // gitlost
        } // gitlost
        if (self::$mb_overload_string) { // gitlost
            $mbEncoding = mb_internal_encoding(); // gitlost
            mb_internal_encoding('8bit');
        }

        // gitlost begin
        if ($kcCF) {
            if (null === self::$kcCF) {
                self::$kcCF = self::getData('kcCaseFolding');
            }
            $s = self::kcCasefold($s);
            if ('' === $s) {
                return '';
            }
        }
        // gitlost end

        $r = self::decompose($s, $K);

        if ($C) {
            if (null === self::$C) {
                self::$C = self::getData('canonicalComposition');
            }

            $r = self::recompose($r);
        }
        if (self::$mb_overload_string) { // gitlost
            mb_internal_encoding($mbEncoding);
        }

        return $r;
    }

	// gitlost begin
    public static function getRawDecomposition($c, $form = UNFC_Normalizer::NFC)
    {
        $c = (string) $c;
        if ('' === $c || !unfc_is_valid_utf8($c) || isset($c[self::$ulenMask[$c[0] & "\xF0"]])) { // Single UTF-8 char only.
            return null;
        }

        if (!isset(self::$forms[$form])) {
            return ''; // Undocumented and probably unintended but matches PHP behaviour as of 7.3.5.
        }

        if ($c < "\xEA\xB0\x80" || "\xED\x9E\xA3" < $c) { // If not Hangul.
            if (UNFC_Normalizer::NFKC_CF === $form) {
                if (null === self::$kcCF) {
                    self::$kcCF = self::getData('kcCaseFolding');
                }
                if (isset(self::$kcCF[$c])) {
                    return self::$kcCF[$c];
                }
                if ("\xf3\xa0\x80\x80" <= $c && $c <= "\xf3\xa0\xbf\xbf") { // U+E0000..E0FFF go to zero-length string, left out of mapping array to lessen size
                    return '';
                }
            }

            if (null === self::$RD) {
                self::$RD = self::getData('rawDecomposition');
            }
            if (!isset(self::$RD[$c])) {
                return null;
            }
            if (is_array(self::$RD[$c])) { // Canonical.
                return self::$RD[$c][0];
            }
            if (UNFC_Normalizer::NFC === $form || UNFC_Normalizer::NFD === $form) {
                return null;
            }
            return self::$RD[$c]; // Compatibility.
        }

        // Hangul chars

        $uchr = unpack('C*', $c);
        $j = (($uchr[1] - 224) << 12) + (($uchr[2] - 128) << 6) + $uchr[3] - 0xAC80;

        if ($c2 = $j % 28) { // A Hangul LVT syllable has a raw decomposition of an LV syllable + T.
            $LV = 0xAC00 + $j - $c2;
            $uchr = chr(0xE0 | $LV >> 12).chr(0x80 | $LV >> 6 & 0x3F).chr(0x80 | $LV & 0x3F)
                    .($c2 < 25
                        ? ("\xE1\x86".chr(0xA7 + $c2))
                        : ("\xE1\x87".chr(0x67 + $c2)));
        } else {
            $uchr = "\xE1\x84".chr(0x80 + (int) ($j / 588))
                   ."\xE1\x85".chr(0xA1 + (int) (($j % 588) / 28));
        }

        return $uchr;
    }
	// gitlost end

    private static function recompose($s)
    {
        $compMap = self::$C;
        $combClass = self::$cC;
        $ulenMask = self::$ulenMask;

        $result = $tail = '';

        $i = $ulenMask[$s[0] & "\xF0"];
        $len = strlen($s);

        $lastUchr = substr($s, 0, $i);
        $lastUcls = isset($combClass[$lastUchr]) ? 256 : 0;

        while ($i < $len) {
            // gitlost Don't bother treating ASCII specially. Note this is a subnormalize() biased change.

            $ulen = $ulenMask[$s[$i] & "\xF0"];
            $uchr = substr($s, $i, $ulen);

            if ($lastUcls || $lastUchr < "\xE1\x84\x80" || "\xE1\x84\x92" < $lastUchr // gitlost Seems to be slightly faster to check $lastUcls first, at least in subnormalize() case.
                || $uchr < "\xE1\x85\xA1" || "\xE1\x85\xB5" < $uchr
                ) {
                // Table lookup and combining chars composition

                $ucls = isset($combClass[$uchr]) ? $combClass[$uchr] : 0;

                if (isset($compMap[$lastUchr.$uchr]) && (!$lastUcls || $lastUcls < $ucls)) {
                    $lastUchr = $compMap[$lastUchr.$uchr];
                } elseif ($lastUcls = $ucls) {
                    $tail .= $uchr;
                } else {
                    $result .= $lastUchr.$tail; // gitlost Slightly faster than testing for $tail.
                    $tail = ''; // gitlost
                    $lastUchr = $uchr;
                }
            } else {
                // Hangul chars

                $L = ord($lastUchr[2]) - 0x80;
                $V = ord($uchr[2]) - 0xA1;
                $T = 0;

                $uchr = substr($s, $i + $ulen, 3);

                if ("\xE1\x86\xA7" <= $uchr && $uchr <= "\xE1\x87\x82") {
                    $T = ord($uchr[2]) - 0xA7;
                    0 > $T && $T += 0x40;
                    $ulen += 3;
                }

                $L = 0xAC00 + ($L * 21 + $V) * 28 + $T;
                $lastUchr = chr(0xE0 | $L >> 12).chr(0x80 | $L >> 6 & 0x3F).chr(0x80 | $L & 0x3F);
            }

            $i += $ulen;
        }

        return $result.$lastUchr.$tail;
    }

    private static function decompose($s, $c)
    {
        $result = '';

        $ASCII = self::$ASCII;
        $decompMap = self::$D;
        $combClass = self::$cC;
        $ulenMask = self::$ulenMask;
        if ($c) {
            $compatMap = self::$KD;
        }

        $c = array();
        $i = 0;
        $len = strlen($s);

        while ($i < $len) {
            if ($s[$i] < "\x80") {
                // ASCII chars

                if ($c) {
                    ksort($c);
                    $result .= implode('', $c);
                    $c = array();
                }

                $j = 1 + strspn($s, $ASCII, $i + 1);
                $result .= substr($s, $i, $j);
                $i += $j;
                continue;
            }

            $ulen = $ulenMask[$s[$i] & "\xF0"];
            $uchr = substr($s, $i, $ulen);
            $i += $ulen;

            if ($uchr < "\xEA\xB0\x80" || "\xED\x9E\xA3" < $uchr) {
                // Table lookup

                if ($uchr !== $j = isset($compatMap[$uchr]) ? $compatMap[$uchr] : (isset($decompMap[$uchr]) ? $decompMap[$uchr] : $uchr)) {
                    $uchr = $j;

                    $j = strlen($uchr);
                    $ulen = $ulenMask[$uchr[0] & "\xF0"]; // gitlost Use $ulenMask for ASCII as well.

                    if ($ulen != $j) {
                        // Put trailing chars in $s

                        $j -= $ulen;
                        $i -= $j;

                        if (0 > $i) {
                            $s = str_repeat(' ', -$i).$s;
                            $len -= $i;
                            $i = 0;
                        }

                        while ($j--) {
                            $s[$i + $j] = $uchr[$ulen + $j];
                        }

                        $uchr = substr($uchr, 0, $ulen);
                    }
                }
                if (isset($combClass[$uchr])) {
                    // Combining chars, for sorting

                    if (!isset($c[$combClass[$uchr]])) {
                        $c[$combClass[$uchr]] = '';
                    }
                    $c[$combClass[$uchr]] .= $uchr;
                    continue;
                }
            } else {
                // Hangul chars

                $uchr = unpack('C*', $uchr);
                $j = (($uchr[1] - 224) << 12) + (($uchr[2] - 128) << 6) + $uchr[3] - 0xAC80;

                $uchr = "\xE1\x84".chr(0x80 + (int) ($j / 588))
                       ."\xE1\x85".chr(0xA1 + (int) (($j % 588) / 28));

                if ($j %= 28) {
                    $uchr .= $j < 25
                        ? ("\xE1\x86".chr(0xA7 + $j))
                        : ("\xE1\x87".chr(0x67 + $j));
                }
            }
            if ($c) {
                ksort($c);
                $result .= implode('', $c);
                $c = array();
            }

            $result .= $uchr;
        }

        if ($c) {
            ksort($c);
            $result .= implode('', $c);
        }

        return $result;
    }

    // gitlost begin Optimized for subnormalize().
    private static function subdecompose($s)
    {
        $result = '';

        $decompMap = self::$D;
        $combClass = self::$cC;
        $ulenMask = self::$ulenMask;

        $c = array();
        $i = 0;
        $len = strlen($s);

        while ($i < $len) {
            $ulen = $ulenMask[$s[$i] & "\xF0"];
            $uchr = substr($s, $i, $ulen);
            $i += $ulen;

            if ($uchr < "\xEA\xB0\x80" || "\xED\x9E\xA3" < $uchr) {
                // Table lookup

                if ($uchr !== $j = isset($decompMap[$uchr]) ? $decompMap[$uchr] : $uchr) {
                    $uchr = $j;

                    $j = strlen($uchr);
                    $ulen = $ulenMask[$uchr[0] & "\xF0"];

                    if ($ulen != $j) {
                        // Put trailing chars in $s

                        $j -= $ulen;
                        $i -= $j;

                        if (0 > $i) {
                            $s = str_repeat(' ', -$i).$s;
                            $len -= $i;
                            $i = 0;
                        }

                        while ($j--) {
                            $s[$i + $j] = $uchr[$ulen + $j];
                        }

                        $uchr = substr($uchr, 0, $ulen);
                    }
                }
                if (isset($combClass[$uchr])) {
                    // Combining chars, for sorting

                    if (!isset($c[$combClass[$uchr]])) {
                        $c[$combClass[$uchr]] = $uchr;
                    } else {
                        $c[$combClass[$uchr]] .= $uchr;
                    }
                    continue;
                }
            } else {
                // Hangul chars

                $uchr = unpack('C*', $uchr);
                $j = (($uchr[1] - 224) << 12) + (($uchr[2] - 128) << 6) + $uchr[3] - 0xAC80;

                $uchr = "\xE1\x84".chr(0x80 + (int) ($j / 588))
                       ."\xE1\x85".chr(0xA1 + (int) (($j % 588) / 28));

                if ($j %= 28) {
                    $uchr .= $j < 25
                        ? ("\xE1\x86".chr(0xA7 + $j))
                        : ("\xE1\x87".chr(0x67 + $j));
                }
            }
            if ($c) {
                ksort($c);
                $result .= implode('', $c);
                $c = array();
            }

            $result .= $uchr;
        }

        if ($c) {
            ksort($c);
            $result .= implode('', $c);
        }

        return $result;
    }

    // gitlost Callback from preg_replace_callback().
    protected static function subnormalize($matches)
    {
        return self::recompose(self::subdecompose($matches[0]));
    }

    private static function kcCasefold($s)
    {
        $result = '';

        $kcCaseFold = self::$kcCF;
        $ulenMask = self::$ulenMask;

        $i = 0;
        $len = strlen($s);

        while ($i < $len) {
            $ulen = $ulenMask[$s[$i] & "\xF0"];
            $uchr = substr($s, $i, $ulen);
            $i += $ulen;

            if (isset($kcCaseFold[$uchr])) {
                $result .= $kcCaseFold[$uchr];
            } elseif ($uchr < "\xf3\xa0\x80\x80" || "\xf3\xa0\xbf\xbf" < $uchr) { // U+E0000..E0FFF go to zero-length string, left out of mapping array to lessen size.
                $result .= $uchr;
            }
        }

        return $result;
    }
    // gitlost end

    private static function getData($file)
    {
        return require dirname( __FILE__ ).'/Resources/unidata/'.$file.'.php'; // gitlost (__DIR__ is 5.3.0)
        /* gitlost
        if (file_exists($file = __DIR__.'/Resources/unidata/'.$file.'.php')) {
            return require $file;
        }

        return false;
        gitlost */
    }
}
