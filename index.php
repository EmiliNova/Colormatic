} else {
	// Name search via Color Pizza API
	if (strlen($q) < 3) {
		$error = 'Please enter at least 3 characters to search by name.';
	} else {
		$endpoint = 'https://api.color.pizza/v1/names?name=' . urlencode($q);
		$resp     = @file_get_contents($endpoint);
		if ($resp !== false) {
			$js = json_decode($resp, true);
			if (!empty($js['colors'])) {
				// take the best match
				$colorData    = $js['colors'][0];
				if (count($js['colors']) > 1) {
					$suggestionText = 'Closest match: ' . htmlspecialchars($colorData['name']);
				}
			}
		}
	}

	// Fallback to a random color + cheeky message if nothing found
	if (!$colorData) {
		$randHex   = sprintf('%06x', random_int(0, 0xFFFFFF));
		$json      = @file_get_contents("https://api.color.pizza/v1/{$randHex}");
		$j         = json_decode($json, true);
		$colorData = $j['colors'][0] ?? null;
		$suggestionText = "30,000+ colors and we can't find that one?! #5h1T";
	}
}