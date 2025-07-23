<?php
// helper: convert “#abc” or “aabbcc” to [r,g,b]
function hexToRgb(string $hex): array {
	$hex = ltrim($hex, '#');
	if (strlen($hex) === 3) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	$int = hexdec($hex);
	return [
		'r' => ($int >> 16) & 255,
		'g' => ($int >> 8) & 255,
		'b' => $int & 255,
	];
}

$input = $_GET['color'] ?? '';
$colorData = null;
$suggestionText = '';
$error = '';

if ($input) {
	$q = trim($input);
	// strip leading “#”
	if (strpos($q, '#') === 0) {
		$q = substr($q, 1);
	}

	// HEX lookup
	if (preg_match('/^[0-9a-fA-F]{3,6}$/', $q)) {
		$hex = strtolower($q);
		$resp = @file_get_contents("https://api.color.pizza/v1/{$hex}");
		if ($resp !== false) {
			$js = json_decode($resp, true);
			$colorData = $js['colors'][0] ?? null;
			// if exact hex not found, API returns nearest; notify user
			if ($colorData && ltrim(strtolower($colorData['hex']), '#') !== $hex) {
				$suggestionText = 'Exact match not found; showing closest color ' . htmlspecialchars($colorData['hex']);
			}
		}
	} else {
		// Name search via Color Pizza API
		if (strlen($q) < 3) {
			$error = 'Please enter at least 3 characters to search by name.';
		} else {
			$endpoint = 'https://api.color.pizza/v1/names?name=' . urlencode($q);
			$resp = @file_get_contents($endpoint);
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
		// Fallback to random if none found
		if (!$colorData) {
			$randHex = sprintf('%06x', random_int(0, 0xFFFFFF));
			$j = json_decode(@file_get_contents("https://api.color.pizza/v1/{$randHex}"), true);
			$colorData = $j['colors'][0] ?? null;
			$suggestionText = "30,000+ colors and we can't find that one?! #5h1T";
		}
	}

	// compute nearest 5 colors if we have one
	if ($colorData) {
		if (!isset($allColors)) {
			$listJson = @file_get_contents('https://unpkg.com/color-name-list@11.3.0/dist/colornames.json');
			$allColors = $listJson ? json_decode($listJson, true) : [];
		}
		// determine base hex for distance calc
		$searchHex = $colorData['requestedHex'] ?? ltrim($colorData['hex'], '#');
		$baseRgb = hexToRgb($searchHex);
		$dists = [];
		foreach ($allColors as $c) {
			$rgb = hexToRgb($c['hex']);
			$dist = sqrt(
				($baseRgb['r'] - $rgb['r'])**2 +
				($baseRgb['g'] - $rgb['g'])**2 +
				($baseRgb['b'] - $rgb['b'])**2
			);
			$dists[] = ['name' => $c['name'], 'hex' => $c['hex'], 'dist' => $dist];
		}
		usort($dists, fn($a, $b) => $a['dist'] <=> $b['dist']);
		$filtered = array_filter($dists, fn($c) => strcasecmp($c['hex'], $colorData['hex']) !== 0);
		$nearestColors = array_slice($filtered, 0, 5);
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Color Search</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://jscolor.com/releases/2.4.6/jscolor.js"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
  <div class="text-center">
	<h1 class="text-4xl font-bold mb-4">Color Search</h1>
	<?php if ($error): ?>
	  <p class="text-red-500 mb-2"><?= htmlspecialchars($error) ?></p>
	<?php endif; ?>
	<form method="GET" class="mb-6 flex justify-center items-center space-x-2">
	  <input name="color"
			 id="search"
			 class="px-4 py-2 border rounded flex-1"
			 placeholder="#ffffff or color name"
			 value="<?= htmlspecialchars($input) ?>"/>
	  <input id="picker"
			 class="jscolor w-10 h-10 rounded border"
			 data-jscolor="{}"
			 title="Click to pick a color"
			 onchange="document.getElementById('search').value = this.jscolor.toString();"/>
	  <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Search</button>
	</form>

	<?php if (!empty($colorData)): ?>
	  <?php if ($suggestionText): ?>
		<p class="text-sm text-gray-600 mb-2"><?= $suggestionText ?></p>
	  <?php endif; ?>

	  <h2 class="text-2xl font-semibold">
		<?= htmlspecialchars($colorData['name']) ?> – <?= htmlspecialchars($colorData['hex']) ?>
	  </h2>
	  <div class="w-16 h-16 mx-auto mt-2 mb-4" style="background-color: <?= htmlspecialchars($colorData['hex']) ?>"></div>

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
</body>
</html>