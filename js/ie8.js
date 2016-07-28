// Array.prototype.reduceRight polyfill for IE8
// From https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Array/ReduceRight

// This version added April 4, 2014 https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Array/ReduceRight$compare?to=543427&from=510831
// based on previous version March 9, 2013 https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Array/ReduceRight$compare?to=364123&from=343617
// I.e. after August 20, 2010 so according to MDN licensing https://developer.mozilla.org/en-US/docs/MDN/About#Copyrights_and_licenses is in the public domain.
// Any copyright is dedicated to the Public Domain. http://creativecommons.org/publicdomain/zero/1.0/

// Production steps of ECMA-262, Edition 5, 15.4.4.22
// Reference: http://es5.github.io/#x15.4.4.22
if ('function' !== typeof Array.prototype.reduceRight) {
	Array.prototype.reduceRight = function(callback /*, initialValue*/) {
		'use strict';
		if (null === this || 'undefined' === typeof this) {
			throw new TypeError('Array.prototype.reduce called on null or undefined' );
		}
		if ('function' !== typeof callback) {
			throw new TypeError(callback + ' is not a function');
		}
		var t = Object(this), len = t.length >>> 0, k = len - 1, value;
		if (arguments.length >= 2) {
			value = arguments[1];
		} else {
			while (k >= 0 && !(k in t)) {
				k--;
			}
			if (k < 0) {
				throw new TypeError('Reduce of empty array with no initial value');
			}
			value = t[k--];
		}
		for (; k >= 0; k--) {
			if (k in t) {
				value = callback(value, t[k], k, t);
			}
		}
		return value;
	};
}
