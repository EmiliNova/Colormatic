<?php
// convert hex to RGB
function hexToRgb(string $hex): array {
	$hex = ltrim($hex, '#');
	if (strlen($hex) === 3) {
		$hex = str_repeat($hex[0], 2) . str_repeat($hex[1], 2) . str_repeat($hex[2], 2);
	}
	$int = hexdec($hex);
	return [
		($int >> 16) & 255,
		($int >> 8) & 255,
		$int & 255,
	];
}

$input = $_GET['color'] ?? '';
$useBg = isset($_GET['bg']);
$colorData = null;
$suggestionText = '';
$error = '';
$exactMiss = false;
$searchedHex = '';

if ($input !== '') {
	$q = trim($input);
	if (strpos($q, '#') === 0) {
		$q = substr($q, 1);
	}
	if (preg_match('/^[0-9a-fA-F]{3,6}$/', $q)) {
		$hex = strtolower($q);
		$searchedHex = '#' . $hex;
		$resp = @file_get_contents("https://api.color.pizza/v1/{$hex}");
		if ($resp !== false) {
			$js = json_decode($resp, true);
			$colorData = $js['colors'][0] ?? null;
			if ($colorData && ltrim(strtolower($colorData['hex']), '#') !== $hex) {
				$exactMiss = true;
				$suggestionText = 'Exact match not found';
			}
		}
	} else {
		if (strlen($q) < 3) {
			$error = 'Please enter at least 3 characters to search by name.';
		} else {
			$resp = @file_get_contents('https://api.color.pizza/v1/names?name=' . urlencode($q));
			if ($resp !== false) {
				$js = json_decode($resp, true);
				if (!empty($js['colors'])) {
					$colorData = $js['colors'][0];
					if (count($js['colors']) > 1) {
						$suggestionText = 'Closest match: ' . htmlspecialchars($colorData['name']);
					}
				}
			}
		}
		if (!$colorData) {
			$randHex = sprintf('%06x', random_int(0, 0xFFFFFF));
			$j = json_decode(@file_get_contents("https://api.color.pizza/v1/{$randHex}"), true);
			$colorData = $j['colors'][0] ?? null;
			$suggestionText = "30,000+ colors and we can't find that one?! #5h1T";
		}
	}
	if ($colorData) {
		$list = @file_get_contents('https://unpkg.com/color-name-list@11.3.0/dist/colornames.json');
		$all = $list ? json_decode($list, true) : [];
		[$r0, $g0, $b0] = hexToRgb(ltrim($colorData['hex'], '#'));
		$dists = [];
		foreach ($all as $c) {
			[$r, $g, $b] = hexToRgb(ltrim($c['hex'], '#'));
			$d = ($r0 - $r)**2 + ($g0 - $g)**2 + ($b0 - $b)**2;
			$dists[] = ['name'=>$c['name'], 'hex'=>$c['hex'], 'd'=>$d];
		}
		usort($dists, fn($a,$b)=>$a['d'] <=> $b['d']);
		$nearestColors = array_slice(array_filter($dists, fn($c)=>strcasecmp($c['hex'], $colorData['hex'])!==0), 0, 5);
	}
}

// Determine text color & logo based on light or darkness of background
$textColor = '#000';
if ($useBg && $colorData) {
	[$r, $g, $b] = hexToRgb(ltrim($colorData['hex'], '#'));
	$lum = (0.299*$r + 0.587*$g + 0.114*$b) / 255;
	$textColor = $lum < 0.5 ? '#fff' : '#000';
}
$logoFile = ($textColor === '#000') ? 'colormatic-logo-dark.svg' : 'colormatic-logo-light.svg';

// Body styling and layout
$bodyClass = 'flex flex-col min-h-screen p-4';
$bodyStyle = '';
if ($useBg && $colorData) {
	$bodyStyle = 'background-color:' . htmlspecialchars($colorData['hex']) . '; color:' . $textColor . ';';
} else {
	$bodyClass .= ' bg-gray-100';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Colormatic - A Super Simple Color Hex & Name Lookup Tool</title>
  <link rel="icon" type="image/png" href="favicon-96x96.png" sizes="96x96" />
  <link rel="icon" type="image/svg+xml" href="favicon.svg" />
  <link rel="shortcut icon" href="favicon.ico" />
  <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png" />
  <link rel="manifest" href="site.webmanifest" />
  <link href="tailwind.min.css" rel="stylesheet">
</head>
<body class="<?= $bodyClass ?>" style="<?= $bodyStyle ?>">
  <div class="flex-1 flex flex-col items-center justify-center w-full max-w-lg mx-auto text-center">
	<!-- Responsive logo -->
	<div class="mb-4">
	  <img src="<?= htmlspecialchars($logoFile) ?>" alt="Colormatic logo" style="width:512px; max-width:100%; height:auto;" class="mx-auto">
	</div>
	<p class="text-2xl font-bold mb-4">Search using a color hex code or word.</p>

	<?php if ($error && $input !== ''): ?>
	  <h4 class="text-red-500 mb-2"><?= htmlspecialchars($error) ?></h4>
	<?php endif; ?>

	<form method="GET" class="mb-6 flex flex-col sm:flex-row items-center justify-center space-y-2 sm:space-y-0 sm:space-x-2">
	  <input name="color" id="search" value="<?= htmlspecialchars($input) ?>" class="px-4 py-2 border rounded w-48" placeholder="#ffffff or color name">
	  <input type="color" id="picker" class="w-10 h-10 p-0 border-none rounded" title="Click to pick a color" onchange="document.getElementById('search').value = this.value">
	  <!-- Visible checkbox toggle -->
	  <div class="flex items-center">
		<input type="checkbox" id="bg" name="bg" class="form-checkbox h-8 w-8" <?= $useBg?'checked':'' ?>>
		<label for="bg" class="ml-2 cursor-pointer">Use as background</label>
	  </div>
	  <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Search</button>
	</form>

	<?php if ($colorData): ?>
	  <?php if ($suggestionText): ?>
		<p class="text-sm mb-2"><?= htmlspecialchars($suggestionText) ?></p>
	  <?php endif; ?>

	  <?php if ($exactMiss): ?>
		<h2 class="text-2xl font-semibold mb-2"><?= htmlspecialchars($searchedHex) ?></h2>
	  <?php else: ?>
		<h2 class="text-2xl font-semibold mb-2"><?= htmlspecialchars($colorData['name']) ?> – <?= htmlspecialchars($colorData['hex']) ?></h2>
	  <?php endif; ?>

	  <div class="w-16 h-16 mx-auto mb-4" style="background-color: <?= htmlspecialchars($colorData['hex']) ?>"></div>

	  <h3 class="font-medium mb-2">5 Closest Colors</h3>
	  <div class="space-y-2">
		<?php foreach ($nearestColors as $c): ?>
		  <div class="flex items-center justify-center space-x-2">
			<div class="w-8 h-8 border" style="background-color: <?= htmlspecialchars($c['hex']) ?>"></div>
			<span><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['hex']) ?>)</span>
		  </div>
		<?php endforeach; ?>
	  </div>
	<?php endif; ?>
  </div>

  <footer class="text-center text-sm py-4">
	<a href="https://github.com/EmiliNova/Colormatic" target="_blank" class="underline">View on GitHub</a>
  </footer>
</body>
</html>