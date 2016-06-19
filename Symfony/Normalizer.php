<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// gitlost removed namespace stuff, renamed to TLN_Normalizer to avoid conflicts, allowed to be included more than once.
// gitlost use single-byte preg_XXX()'s, and generated regex alternatives and preg_match_all(), added full isNormalized() check for NFC with fall back to normalize().
// https://github.com/symfony/polyfill/tree/master/src/Intl/Normalizer

// namespace Symfony\Polyfill\Intl\Normalizer; // gitlost

/**
 * Normalizer is a PHP fallback implementation of the Normalizer class provided by the intl extension.
 *
 * It has been validated with Unicode 8.0.0 Normalization Conformance Test. // gitlost
 * See http://www.unicode.org/reports/tr15/ for detailed info about Unicode normalizations.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
// gitlost begin
// See https://www.w3.org/International/questions/qa-forms-utf-8
define( 'TLN_REGEX_IS_VALID_UTF8',
			'/\A(?:
			  [\x00-\x7f]                                     # ASCII
			| [\xc2-\xdf][\x80-\xbf]                          # non-overlong 2-byte
			| \xe0[\xa0-\xbf][\x80-\xbf]                      # excluding overlongs
			| [\xe1-\xec\xee\xef][\x80-\xbf][\x80-\xbf]       # straight 3-byte
			| \xed[\x80-\x9f][\x80-\xbf]                      # excluding surrogates
			| \xf0[\x90-\xbf][\x80-\xbf][\x80-\xbf]           # planes 1-3
			| [\xf1-\xf3][\x80-\xbf][\x80-\xbf][\x80-\xbf]    # planes 4-15
			| \xf4[\x80-\x8f][\x80-\xbf][\x80-\xbf]           # plane 16
			)*+\z/x'
);

if ( ! defined( 'TLN_REGEX_ALTS_NFC_NOES' ) ) {
	require dirname( __FILE__ ) . '/tln_regex_alts.php';
}

// (Possibly) unstable code point(s) (or end of string) proceeded by a stable code point (or start of string). See http://unicode.org/reports/tr15/#Stable_Code_Points
define( 'TLN_REGEX_NFC_SUBNORMALIZE', '/(?:\A|[\x00-\x7f]|(?:[\xc2-\xdf]|(?:[\xe0-\xef]|[\xf0-\xf4].).).)(?:(?:' . TLN_REGEX_ALTS_NFC_NOES_MAYBES_REORDERS . ')++|\z)/' );
// gitlost end
class TLN_Normalizer // gitlost
{
    const NONE = 1;
    const FORM_D = 2;
    const FORM_KD = 3;
    const FORM_C = 4;
    const FORM_KC = 5;
    const NFD = 2;
    const NFKD = 3;
    const NFC = 4;
    const NFKC = 5;

    private static $C;
    private static $D;
    private static $KD;
    private static $cC;
    private static $ulenMask = array("\x00" => 1, "\x10" => 1, "\x20" => 1, "\x30" => 1, "\x40" => 1, "\x50" => 1, "\x60" => 1, "\x70" => 1, "\xC0" => 2, "\xD0" => 2, "\xE0" => 3, "\xF0" => 4); // gitlost Use for ASCII as well.
    private static $ASCII = "\x20\x65\x69\x61\x73\x6E\x74\x72\x6F\x6C\x75\x64\x5D\x5B\x63\x6D\x70\x27\x0A\x67\x7C\x68\x76\x2E\x66\x62\x2C\x3A\x3D\x2D\x71\x31\x30\x43\x32\x2A\x79\x78\x29\x28\x4C\x39\x41\x53\x2F\x50\x22\x45\x6A\x4D\x49\x6B\x33\x3E\x35\x54\x3C\x44\x34\x7D\x42\x7B\x38\x46\x77\x52\x36\x37\x55\x47\x4E\x3B\x4A\x7A\x56\x23\x48\x4F\x57\x5F\x26\x21\x4B\x3F\x58\x51\x25\x59\x5C\x09\x5A\x2B\x7E\x5E\x24\x40\x60\x7F\x00\x01\x02\x03\x04\x05\x06\x07\x08\x0B\x0C\x0D\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F";
	protected static $s = null, $form = null, $normalize = null; // gitlost Cache various info discovered in isNormalized().
	protected static $mb_overload_string = null; // gitlost Set if mbstring extension loaded with string function overload set.

    public static function isNormalized($s, $form = self::NFC)
    {
        if ($form <= self::NONE || self::NFKC < $form) {
			return false;
		}
        if (!isset($s[strspn($s .= '', self::$ASCII)])) {
            return true;
        }
		// gitlost begin
        if (self::NFC === $form) {
			if (1 !== preg_match(TLN_REGEX_IS_VALID_UTF8, $s)) {
				return false;
			}
			if (1 !== preg_match(TLN_REGEX_NFC_NOES_MAYBES_REORDERS, $s)) { // If contains no characters that could possibly need normalizing...
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
			$normalize = preg_replace_callback(TLN_REGEX_NFC_SUBNORMALIZE, 'TLN_Normalizer::subnormalize', $s);

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

    public static function normalize($s, $form = self::NFC)
    {
		// gitlost begin
		if (null !== self::$normalize ) {
			if ($s === self::$s && $form === self::$form) { //  Use cache if available.
				$result = self::$normalize;
				self::$s = self::$form = self::$normalize = null; // Clear cache (try to keep memory usage to a min).
				return $result;
			}
			self::$s = self::$form = self::$normalize = null; // Clear cache (try to keep memory usage to a min).
		}

        switch ($form) {
            case self::NONE: return 1 === preg_match(TLN_REGEX_IS_VALID_UTF8, $s .= '') ? $s : false; // Note must still check validity.
            case self::NFC: $C = true; $K = false; break;
            case self::NFD: $C = false; $K = false; break;
            case self::NFKC: $C = true; $K = true; break;
            case self::NFKD: $C = false; $K = true; break;
            default: return false;
        }

		if (1 !== preg_match(TLN_REGEX_IS_VALID_UTF8, $s .= '')) {
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
                ||   $uchr < "\xE1\x85\xA1" || "\xE1\x85\xB5" < $uchr
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
