<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// gitlost removed namespace stuff, renamed to UNFC_Normalizer to avoid conflicts.
// gitlost moved body of code to UNFC_BaseNormalizer to cater for change of consts for PHP >= 7.3 with ICU >= 56.
// https://github.com/symfony/polyfill/tree/master/src/Intl/Normalizer

// namespace Symfony\Polyfill\Intl\Normalizer; // gitlost
require dirname( __FILE__ ) . '/BaseNormalizer.php';

/**
 * Normalizer is a PHP fallback implementation of the Normalizer class provided by the intl extension.
 *
 * It has been validated with Unicode 12.1.0 Normalization Conformance Test. // gitlost
 * See http://www.unicode.org/reports/tr15/ for detailed info about Unicode normalizations.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */

// PHP >= 7.3 with ICU >= 56 changed the values of the Normalizer consts for some reason, and also added new const NFKC_CF for case-folding.
if ( version_compare( PHP_VERSION, '7.3', '>=' ) && version_compare( INTL_ICU_VERSION, '56', '>=' ) ) {
	class UNFC_Normalizer extends UNFC_BaseNormalizer {
		const NONE = 0x2;
		const FORM_D = 0x4;
		const FORM_KD = 0x8;
		const FORM_C = 0x10;
		const FORM_KC = 0x20;
		const FORM_KC_CF = 0x30;
		const NFD = 0x4;
		const NFKD = 0x8;
		const NFC = 0x10;
		const NFKC = 0x20;
		const NFKC_CF = 0x30;
	}
} else {
	class UNFC_Normalizer extends UNFC_BaseNormalizer {
		const NONE = 1;
		const FORM_D = 2;
		const FORM_KD = 3;
		const FORM_C = 4;
		const FORM_KC = 5;
		const NFD = 2;
		const NFKD = 3;
		const NFC = 4;
		const NFKC = 5;
		const NFKC_CF = 0x30; // Define this anyway as functionality available.
	}
}
