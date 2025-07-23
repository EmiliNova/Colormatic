<?php
// index.php

// helper: convert “#abc” or “aabbcc” to [r,g,b]
function hexToRgb(string $hex): array {
	$hex = ltrim($hex, '#');
	if (strlen($hex) === 3) {
		$hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
	}
	$int = hexdec($hex);
	return [
		'r' => ($int >> 16) & 255,
		'g' => ($int >> 8)  & 255,
		'b' => $int & 255,
	];
}

$input = $_GET['color'] ?? '';
$colorData = null;
$suggestionText = '';
$nearestColors = [];

if ($input) {
	$q = trim($input);
	// strip leading “#”
	if (strpos($q, '#') === 0) {
		$q = substr($q, 1);
	}
	// is it a hex code?
	if (preg_match('/^[0-9a-fA-F]{3,6}$/', $q)) {
		// normalize to 6-digit
		$hex = strtolower($q);
		// fetch name from Color Name API
		$apiUrl = "https://api.color.pizza/v1/" . $hex;
		$resp   = @file_get_contents($apiUrl);
		if ($resp !== false) {
			$js       = json_decode($resp, true);
			$colorData = $js['colors'][0] ?? null;
		}
	} else {
		// treat as name
		// load the full list (30363 entries) from CDN
		$listJson  = @file_get_contents('https://unpkg.com/color-name-list@11.3.0/dist/colornames.json');
		$allColors = $listJson ? json_decode($listJson, true) : [];
		// exact match?
		$found = null;
		foreach ($allColors as $c) {
			if (strcasecmp($c['name'], $q) === 0) {
				$found = $c;
				break;
			}
		}
		if ($found) {
			// exact name → use its hex
			$hex = ltrim($found['hex'], '#');
			$colorData = json_decode(@file_get_contents("https://api.color.pizza/v1/{$hex}"), true)['colors'][0] ?? null;
		} else {
			// substring fallback
			$closest = null;
			foreach ($allColors as $c) {
				if (stripos($c['name'], $q) !== false) {
					$closest = $c;
					break;
				}
			}
			if ($closest) {
				$hex = ltrim($closest['hex'], '#');
				$colorData = json_decode(@file_get_contents("https://api.color.pizza/v1/{$hex}"), true)['colors'][0] ?? null;
				$suggestionText = "Closest match: " . htmlspecialchars($closest['name']);
			} else {
				// nothing close → random
				$rand = $allColors[array_rand($allColors)];
				$hex = ltrim($rand['hex'], '#');
				$colorData = json_decode(@file_get_contents("https://api.color.pizza/v1/{$hex}"), true)['colors'][0] ?? null;
				$suggestionText = "Random pick: " . htmlspecialchars($rand['name']);
			}
		}
	}

	// if we have a color, compute 5 nearest by RGB-distance
	if ($colorData) {
		// ensure $allColors loaded
		if (!isset($allColors)) {
			$listJson  = @file_get_contents('https://unpkg.com/color-name-list@11.3.0/dist/colornames.json');
			$allColors = $listJson ? json_decode($listJson, true) : [];
		}
		$baseRgb = hexToRgb($colorData['requestedHex']);
		$dists = [];
		foreach ($allColors as $c) {
			$rgb = hexToRgb($c['hex']);
			$dist = sqrt(
			  ($baseRgb['r'] - $rgb['r'])**2 +
			  ($baseRgb['g'] - $rgb['g'])**2 +
			  ($baseRgb['b'] - $rgb['b'])**2
			);
			$dists[] = ['name'=>$c['name'],'hex'=>$c['hex'],'dist'=>$dist];
		}
		usort($dists, fn($a,$b)=>$a['dist'] <=> $b['dist']);
		// drop the exact match itself
		$nearestColors = array_filter($dists, fn($c)=> strcasecmp($c['hex'],$colorData['hex'])!==0);
		$nearestColors = array_slice($nearestColors, 0, 5);
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
	<form method="GET" class="mb-6">
	  <input name="color"
			 class="jscolor px-4 py-2 border rounded"
			 placeholder="#ffffff or white"
			 value="<?= htmlspecialchars($input) ?>"/>
	  <button type="submit" class="px-4 py-2 ml-2 bg-blue-600 text-white rounded">Search</button>
	</form>

<?php if ($colorData): ?>
	<?php if ($suggestionText): ?>
	  <p class="text-sm text-gray-600 mb-2"><?= $suggestionText ?></p>
	<?php endif; ?>

	<h2 class="text-2xl font-semibold">
	  <?= htmlspecialchars($colorData['name']) ?> – <?= htmlspecialchars($colorData['requestedHex']) ?>
	</h2>
	<div class="w-16 h-16 mx-auto mt-2 mb-4"
		 style="background-color: <?= htmlspecialchars($colorData['hex']) ?>"></div>

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