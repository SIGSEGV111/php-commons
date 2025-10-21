<?php
declare(strict_types=1);
namespace phpcom\math;

function integrateTrapezoidal(callable $f, float $x0, float $x1, int $n = 1000): float
{
	if($n <= 0)
		throw new \InvalidArgumentException("n muss > 0 sein");

	$dx = ($x1 - $x0) / floatval($n);
	$sum = 0.5 * ($f($x0) + $f($x1));

	for ($i = 1; $i < $n; $i++)
		$sum += $f($x0 + floatval($i) * $dx);

	return $sum * $dx;
}

function createInterpolator(array $points): callable
{
	if(count($points) == 0)
		throw new \InvalidArgumentException("no points given");

	// Punkte nach x-Wert sortieren
	usort($points, function ($a, $b) {
		return $a[0] <=> $b[0];
	});

	return function (float $x) use ($points): float
	{
		$n = count($points);

		if($x < $points[0][0] || $x > $points[$n - 1][0]) {
			throw new \OutOfRangeException("x = $x liegt außerhalb der Messwerte");
		}

		// passenden Intervall suchen
		for ($i = 0; $i < $n - 1; $i++) {
			$x0 = $points[$i][0];
			$y0 = $points[$i][1];
			$x1 = $points[$i + 1][0];
			$y1 = $points[$i + 1][1];

			if($x >= $x0 && $x <= $x1) {
				if($x0 == $x1) {
					return $y0; // sollte praktisch nicht vorkommen
				}
				$t = ($x - $x0) / ($x1 - $x0);
				return $y0 + $t * ($y1 - $y0);
			}
		}

		// sollte eigentlich nie erreicht werden
		throw new \RuntimeException("Interpolation fehlgeschlagen für x = $x");
	};
}

/**
 * Scale a numeric value to an SI-prefixed unit string (engineering notation).
 *
 * Examples:
 *   scaleValue(0.0000007, 'A')     => "0.7µA"
 *   scaleValue(15320, 'Ohm')       => "15.32kΩ"
 *   scaleValue(-0.0025, 'm')       => "-2.5mm"
 *   scaleValue(0.0, 'V')           => "0V"
 *
 * @param float  $value       The numeric value to scale.
 * @param string $unit        Base unit (e.g., "A", "V", "m", "Ohm", "Ω").
 * @param int    $sig_digits  Significant digits for the formatted number (1–10).
 */
function scaleValue(float $value, string $unit, int $sig_digits = 3): string
{
	$unit = trim($unit);
	$unit_lower = mb_strtolower($unit, 'UTF-8');

	if($value == 0.0 || !is_finite($value))
	{
		$num = ($value == 0.0) ? '0' : (string)$value;
		return $num . $unit;
	}

	/** @var non-empty-array<int,string> */
	static $prefixes = [
		-30 => 'q',  // quecto
		-27 => 'r',  // ronto
		-24 => 'y',  // yocto
		-21 => 'z',  // zepto
		-18 => 'a',  // atto
		-15 => 'f',  // femto
		-12 => 'p',  // pico
		-9  => 'n',  // nano
		-6  => 'µ',  // micro
		-3  => 'm',  // milli
		 0  => '',   // none
		 3  => 'k',  // kilo
		 6  => 'M',  // mega
		 9  => 'G',  // giga
		12  => 'T',  // tera
		15  => 'P',  // peta
		18  => 'E',  // exa
		21  => 'Z',  // zetta
		24  => 'Y',  // yotta
		27  => 'R',  // ronna
		30  => 'Q',  // quetta
	];

	$abs = abs($value);

	$exp = intval(3.0 * floor(log10($abs) / 3.0));
	$min_exp = array_key_first($prefixes);
	$max_exp = array_key_last($prefixes);

	if($exp < $min_exp)
	{
		$exp = $min_exp;
	}
	elseif($exp > $max_exp)
	{
		$exp = $max_exp;
	}

	$scaled = $value / floatval(10 ** $exp);

	// if(abs($scaled) >= 1000.0 && $exp + 3 <= $max_exp)
	// {
	// 	$exp += 3;
	// 	$scaled = $value / floatval(10 ** $exp);
	// }
	// elseif(abs($scaled) < 1.0 && $exp - 3 >= $min_exp)
	// {
	// 	$exp -= 3;
	// 	$scaled = $value / floatval(10 ** $exp);
	// }

	$prefix = $prefixes[$exp];

	$sig_digits = max(1, min(10, $sig_digits));
	$abs_scaled = abs($scaled);
	$decimals = ($abs_scaled > 0)
		? max(0, $sig_digits - 1 - (int)floor(log10($abs_scaled)))
		: $sig_digits - 1;

	$formatted = number_format($scaled, $decimals, '.', '');
	if(str_contains($formatted, '.'))
		$formatted = rtrim($formatted, '0.');

	return $formatted . $prefix . $unit;
}
